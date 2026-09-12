<?php

namespace App\Services;

use DomainException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AccessibilityAuditService
{
    private const MAX_REDIRECTS = 3;

    private const MAX_HTML_BYTES = 2_000_000;

    /** @return array<string, mixed> */
    public function audit(string $url, ?string $name = null): array
    {
        $startedAt = microtime(true);
        [$finalUrl, $html] = $this->fetchPage($url);
        $report = $this->inspectHtml($html);
        $title = $this->pageTitle($html) ?: parse_url($finalUrl, PHP_URL_HOST);

        return [
            'id' => (string) Str::uuid(),
            'name' => $name ?: $title.' accessibility audit',
            'url' => $finalUrl,
            'host' => parse_url($finalUrl, PHP_URL_HOST),
            'title' => $title,
            'score' => $report['score'],
            'status' => $this->statusFor($report['score']),
            'counts' => $report['counts'],
            'passed_count' => $report['passed_count'],
            'issues' => $report['issues'],
            'truncated' => $report['truncated'],
            'audited_at' => now()->format('d M Y · H:i'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function fetchPage(string $url): array
    {
        $currentUrl = $url;

        for ($redirectCount = 0; $redirectCount <= self::MAX_REDIRECTS; $redirectCount++) {
            [$host, $port, $address] = $this->publicEndpoint($currentUrl);
            $response = $this->httpClient($host, $port, $address)->get($currentUrl);

            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $location = $response->header('Location');

                if ($location === '' || $redirectCount === self::MAX_REDIRECTS) {
                    throw new DomainException('The website redirected too many times.');
                }

                $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));

                continue;
            }

            if (! $response->successful()) {
                throw new DomainException('The website returned HTTP '.$response->status().'.');
            }

            $html = $response->body();
            $contentType = Str::lower($response->header('Content-Type'));

            if (strlen($html) > self::MAX_HTML_BYTES) {
                throw new DomainException('The webpage is too large to audit safely.');
            }

            if (! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml+xml') && ! str_starts_with(ltrim($html), '<')) {
                throw new DomainException('The URL does not return an HTML webpage.');
            }

            return [$currentUrl, $html];
        }

        throw new DomainException('The website could not be loaded.');
    }

    /** @return array{0: string, 1: int, 2: string} */
    private function publicEndpoint(string $url): array
    {
        $parts = parse_url($url);
        $scheme = Str::lower((string) ($parts['scheme'] ?? ''));
        $host = trim(Str::lower(rtrim((string) ($parts['host'] ?? ''), '.')), '[]');

        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new DomainException('Enter a valid public HTTP or HTTPS URL.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new DomainException('URLs containing credentials cannot be audited.');
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($port, [80, 443], true)) {
            throw new DomainException('Only standard website ports 80 and 443 are allowed.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new DomainException('Private or local network addresses cannot be audited.');
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveHost($host);

        if ($addresses === [] || collect($addresses)->contains(fn (string $address): bool => ! $this->isPublicAddress($address))) {
            throw new DomainException('Private or local network addresses cannot be audited.');
        }

        $address = collect($addresses)->first(fn (string $candidate): bool => filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
            ?? $addresses[0];

        return [$host, $port, $address];
    }

    /** @return array<int, string> */
    private function resolveHost(string $host): array
    {
        $records = dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return collect($records)
            ->map(fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var(
            $address,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    private function httpClient(string $host, int $port, string $address): PendingRequest
    {
        $options = [
            'allow_redirects' => false,
            'verify' => true,
        ];

        if (defined('CURLOPT_RESOLVE')) {
            $pinnedAddress = str_contains($address, ':') ? '['.$address.']' : $address;
            $options['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$pinnedAddress}"]];
        }

        return Http::connectTimeout(3)
            ->timeout(12)
            ->withOptions($options)
            ->withHeaders([
                'Accept' => 'text/html,application/xhtml+xml',
                'User-Agent' => 'Tech4Projects-Accessibility-Auditor/1.0',
            ]);
    }

    /** @return array{score: int, counts: array<string, int>, passed_count: int, issues: array<int, array<string, string>>, truncated: bool} */
    private function inspectHtml(string $html): array
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $loaded) {
            throw new DomainException('The webpage HTML could not be analysed.');
        }

        $xpath = new DOMXPath($document);
        $issues = [];
        $violatedRules = [];
        $issueTotal = 0;
        $ruleIds = [
            'html-lang', 'document-title', 'main-landmark', 'navigation-landmark',
            'page-has-heading-one', 'heading-order', 'image-alt', 'form-label',
            'button-name', 'link-name', 'duplicate-id', 'table-header',
            'tabindex', 'media-caption', 'interactive-semantics', 'aria-reference',
        ];

        $addIssue = function (
            string $ruleId,
            string $category,
            string $severity,
            string $title,
            string $description,
            ?DOMNode $node,
            string $wcag,
            string $recommendation,
        ) use (&$issues, &$violatedRules, &$issueTotal): void {
            $violatedRules[$ruleId] = true;
            $issueTotal++;

            if (count($issues) >= 100) {
                return;
            }

            $issues[] = [
                'rule_id' => $ruleId,
                'category' => $category,
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'element' => $node ? $this->elementHtml($node) : 'Page document',
                'selector' => $node instanceof DOMElement ? $this->selector($node) : 'html',
                'wcag' => $wcag,
                'recommendation' => $recommendation,
            ];
        };

        $htmlElement = $document->documentElement;
        if (! $htmlElement?->hasAttribute('lang') || trim($htmlElement->getAttribute('lang')) === '') {
            $addIssue('html-lang', 'Structure', 'serious', 'Page language is not defined', 'Screen readers may pronounce content using the wrong language.', $htmlElement, 'WCAG 3.1.1', 'Add a valid lang attribute to the html element, such as <html lang="en">.');
        }

        $titleNode = $xpath->query('//title')->item(0);
        if (! $titleNode || trim($titleNode->textContent) === '') {
            $addIssue('document-title', 'Structure', 'serious', 'Page title is missing', 'Users cannot easily identify the page in browser tabs or assistive technology.', $titleNode, 'WCAG 2.4.2', 'Add a concise, descriptive <title> inside the document head.');
        }

        if ($xpath->query('//main | //*[@role="main"]')->length === 0) {
            $addIssue('main-landmark', 'Structure', 'moderate', 'Main landmark is missing', 'Keyboard and screen-reader users cannot jump directly to the primary content.', null, 'WCAG 1.3.1', 'Wrap the primary page content in a <main> element.');
        }

        if ($xpath->query('//nav | //*[@role="navigation"]')->length === 0) {
            $addIssue('navigation-landmark', 'Structure', 'minor', 'Navigation landmark is missing', 'A navigation landmark helps assistive technology users understand page regions.', null, 'WCAG 1.3.1', 'Use a <nav> element for the page’s primary navigation when navigation is present.');
        }

        $headings = $xpath->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        $headingOnes = $xpath->query('//h1');
        if ($headingOnes->length === 0) {
            $addIssue('page-has-heading-one', 'Headings', 'serious', 'Page has no H1 heading', 'A clear H1 helps users understand the page’s primary purpose.', null, 'WCAG 1.3.1', 'Add one descriptive H1 for the main page topic.');
        } elseif ($headingOnes->length > 1) {
            $addIssue('page-has-heading-one', 'Headings', 'moderate', 'Page has multiple H1 headings', 'Multiple primary headings can make the content hierarchy harder to understand.', $headingOnes->item(1), 'WCAG 1.3.1', 'Use one primary H1 and organise subsections with H2–H6 headings.');
        }

        $previousLevel = 0;
        foreach ($headings as $heading) {
            $level = (int) substr($heading->nodeName, 1);
            if ($previousLevel > 0 && $level > $previousLevel + 1) {
                $addIssue('heading-order', 'Headings', 'moderate', 'Heading level is skipped', 'Skipped heading levels can make the content outline confusing.', $heading, 'WCAG 1.3.1', 'Move through heading levels sequentially without skipping levels.');
            }
            $previousLevel = $level;
        }

        foreach ($xpath->query('//img') as $image) {
            if ($image instanceof DOMElement && ! $image->hasAttribute('alt')) {
                $addIssue('image-alt', 'Images', 'critical', 'Image is missing alternative text', 'Screen-reader users may not understand the image’s content or purpose.', $image, 'WCAG 1.1.1', 'Add meaningful alt text, or alt="" when the image is purely decorative.');
            }
        }

        $labelledIds = [];
        foreach ($xpath->query('//label[@for]') as $label) {
            if ($label instanceof DOMElement) {
                $labelledIds[$label->getAttribute('for')] = true;
            }
        }
        foreach ($xpath->query('//input | //select | //textarea') as $control) {
            if (! $control instanceof DOMElement || in_array(Str::lower($control->getAttribute('type')), ['hidden', 'submit', 'reset', 'button', 'image'], true)) {
                continue;
            }
            $id = $control->getAttribute('id');
            if (! $this->hasAccessibleName($control) && ($id === '' || ! isset($labelledIds[$id])) && ! $this->hasLabelAncestor($control)) {
                $addIssue('form-label', 'Forms', 'critical', 'Form field has no accessible label', 'Screen-reader users may not know what information to enter.', $control, 'WCAG 1.3.1 · 3.3.2', 'Associate a visible <label> using for and id, or provide an appropriate accessible name.');
            }
        }

        foreach ($xpath->query('//button') as $button) {
            if ($button instanceof DOMElement && ! $this->hasAccessibleName($button) && ! $this->hasImageAlternative($button)) {
                $addIssue('button-name', 'Buttons', 'critical', 'Button has no accessible name', 'Assistive technology cannot communicate the button’s action.', $button, 'WCAG 4.1.2', 'Add visible button text or a descriptive aria-label for an icon-only button.');
            }
        }

        foreach ($xpath->query('//a[@href]') as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }
            $text = Str::lower(trim(preg_replace('/\s+/u', ' ', $link->textContent) ?? ''));
            if (! $this->hasAccessibleName($link) && ! $this->hasImageAlternative($link)) {
                $addIssue('link-name', 'Links', 'serious', 'Link has no accessible name', 'Screen-reader users cannot determine where the link goes.', $link, 'WCAG 2.4.4 · 4.1.2', 'Add meaningful link text or an accessible name that describes the destination.');
            } elseif (in_array($text, ['click here', 'read more', 'more', 'link'], true)) {
                $addIssue('link-name', 'Links', 'moderate', 'Link text is unclear', 'Generic link text does not explain the destination when read out of context.', $link, 'WCAG 2.4.4', 'Replace generic wording with text that describes the destination.');
            }
        }

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            $id = $element->getAttribute('id');
            if ($id !== '' && isset($ids[$id])) {
                $addIssue('duplicate-id', 'ARIA', 'serious', 'Duplicate element ID found', 'Duplicate IDs can break labels, ARIA references, and scripted interactions.', $element, 'WCAG 4.1.1', 'Give every element ID a unique value.');
            }
            $ids[$id] = true;
        }

        foreach ($xpath->query('//table') as $table) {
            if ($table instanceof DOMElement && (new DOMXPath($document))->query('.//th', $table)->length === 0) {
                $addIssue('table-header', 'Tables', 'serious', 'Data table has no header cells', 'Screen readers need headers to relate each data cell to its meaning.', $table, 'WCAG 1.3.1', 'Mark row or column headers with <th> and add scope where appropriate.');
            }
        }

        foreach ($xpath->query('//*[@tabindex]') as $element) {
            if ($element instanceof DOMElement && (int) $element->getAttribute('tabindex') > 0) {
                $addIssue('tabindex', 'Keyboard', 'moderate', 'Positive tabindex changes focus order', 'A custom focus order can be unpredictable for keyboard users.', $element, 'WCAG 2.4.3', 'Use tabindex="0" for custom controls and preserve the natural DOM focus order.');
            }
        }

        foreach ($xpath->query('//video | //audio') as $media) {
            if ($media instanceof DOMElement && (new DOMXPath($document))->query('.//track[@kind="captions"]', $media)->length === 0) {
                $addIssue('media-caption', 'Media', 'moderate', 'Media has no captions track', 'People who are deaf or hard of hearing may miss spoken information.', $media, 'WCAG 1.2.2', 'Provide synchronised captions and reference them with a captions track.');
            }
        }

        foreach ($xpath->query('//*[@onclick]') as $interactive) {
            if (! $interactive instanceof DOMElement || in_array($interactive->tagName, ['a', 'button', 'input', 'select', 'textarea'], true)) {
                continue;
            }
            if (! in_array($interactive->getAttribute('role'), ['button', 'link'], true) || ! $interactive->hasAttribute('tabindex')) {
                $addIssue('interactive-semantics', 'Keyboard', 'serious', 'Clickable element is not keyboard accessible', 'Mouse-only controls prevent keyboard users from activating the action.', $interactive, 'WCAG 2.1.1 · 4.1.2', 'Use a native button or link, or add keyboard support, role, and focusability.');
            }
        }

        foreach ($xpath->query('//*[@aria-labelledby]') as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            foreach (preg_split('/\s+/', trim($element->getAttribute('aria-labelledby'))) ?: [] as $reference) {
                if ($reference !== '' && ! isset($ids[$reference])) {
                    $addIssue('aria-reference', 'ARIA', 'serious', 'ARIA label references a missing element', 'The accessible name may be empty because aria-labelledby points to an unknown ID.', $element, 'WCAG 4.1.2', 'Point aria-labelledby to the ID of an existing element with descriptive text.');
                    break;
                }
            }
        }

        $weights = ['critical' => 8, 'serious' => 5, 'moderate' => 2, 'minor' => 1];
        $counts = ['critical' => 0, 'serious' => 0, 'moderate' => 0, 'minor' => 0];
        $penalty = 0;
        foreach ($issues as $issue) {
            $counts[$issue['severity']]++;
            $penalty += $weights[$issue['severity']];
        }

        return [
            'score' => max(0, 100 - $penalty),
            'counts' => $counts,
            'passed_count' => count($ruleIds) - count($violatedRules),
            'issues' => $issues,
            'truncated' => $issueTotal > count($issues),
        ];
    }

    private function hasAccessibleName(DOMElement $element): bool
    {
        return trim(preg_replace('/\s+/u', ' ', $element->textContent) ?? '') !== ''
            || trim($element->getAttribute('aria-label')) !== ''
            || trim($element->getAttribute('aria-labelledby')) !== ''
            || trim($element->getAttribute('title')) !== '';
    }

    private function hasImageAlternative(DOMElement $element): bool
    {
        foreach ($element->getElementsByTagName('img') as $image) {
            if (trim($image->getAttribute('alt')) !== '') {
                return true;
            }
        }

        return false;
    }

    private function hasLabelAncestor(DOMElement $element): bool
    {
        $parent = $element->parentNode;
        while ($parent instanceof DOMElement) {
            if ($parent->tagName === 'label') {
                return true;
            }
            $parent = $parent->parentNode;
        }

        return false;
    }

    private function elementHtml(DOMNode $node): string
    {
        $html = $node->ownerDocument?->saveHTML($node) ?: $node->textContent;

        return Str::limit(Str::squish($html), 260);
    }

    private function selector(DOMElement $element): string
    {
        if ($element->getAttribute('id') !== '') {
            return $element->tagName.'#'.preg_replace('/[^A-Za-z0-9_-]/', '-', $element->getAttribute('id'));
        }

        $class = preg_split('/\s+/', trim($element->getAttribute('class')))[0] ?? '';

        return $element->tagName.($class !== '' ? '.'.preg_replace('/[^A-Za-z0-9_-]/', '-', $class) : '');
    }

    private function pageTitle(string $html): ?string
    {
        $document = new DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);
        $title = trim($document->getElementsByTagName('title')->item(0)?->textContent ?? '');

        return $title !== '' ? Str::limit(Str::squish($title), 100) : null;
    }

    private function statusFor(int $score): string
    {
        return match (true) {
            $score >= 90 => 'Excellent',
            $score >= 75 => 'Good',
            $score >= 50 => 'Needs improvement',
            default => 'Poor',
        };
    }
}
