<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccessibilityAuditTest extends TestCase
{
    private const PUBLIC_URL = 'https://93.184.216.34';

    public function test_audit_page_renders_the_url_scanner(): void
    {
        $this->get(route('accessibility-auditor.index'))
            ->assertSee('A11y Scan')
            ->assertSee('Run accessibility audit')
            ->assertSee('Enter a public URL');
    }

    public function test_private_network_url_is_rejected_before_any_request_is_sent(): void
    {
        Http::preventStrayRequests();

        $this->from(route('accessibility-auditor.index'))
            ->post(route('accessibility-auditor.audits.store'), ['url' => 'http://127.0.0.1/admin'])
            ->assertRedirect(route('accessibility-auditor.index'))
            ->assertSessionHasErrors(['url' => 'Private or local network addresses cannot be audited.']);

        Http::assertNothingSent();
    }

    public function test_unsafe_protocol_is_rejected_by_validation(): void
    {
        $this->from(route('accessibility-auditor.index'))
            ->post(route('accessibility-auditor.audits.store'), ['url' => 'file:///etc/passwd'])
            ->assertRedirect(route('accessibility-auditor.index'))
            ->assertSessionHasErrors(['url']);
    }

    public function test_accessible_page_receives_a_full_score_and_is_saved(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLIC_URL => Http::response($this->accessibleHtml(), 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);

        $response = $this->post(route('accessibility-auditor.audits.store'), [
            'url' => self::PUBLIC_URL,
            'audit_name' => '<script>alert(1)</script>',
        ]);

        $response
            ->assertRedirectContains('/accessibility-auditor?report=')
            ->assertSessionHas('accessibility_audits.0.score', 100)
            ->assertSessionHas('accessibility_audits.0.status', 'Excellent');
        Http::assertSent(fn (Request $request): bool => $request->url() === self::PUBLIC_URL
            && $request->hasHeader('User-Agent', 'Tech4Projects-Accessibility-Auditor/1.0'));

        $this->get(route('accessibility-auditor.index'))
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_inaccessible_elements_are_scored_and_return_actionable_findings(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLIC_URL => Http::response(<<<'HTML'
                <!doctype html><html lang="en"><head><title>Broken Shop</title></head><body>
                <nav><a href="/help"></a></nav><main><h1>Broken Shop</h1><img src="hero.jpg">
                <form><input id="email" type="email"></form><button></button></main></body></html>
                HTML, 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->post(route('accessibility-auditor.audits.store'), ['url' => self::PUBLIC_URL]);

        $response
            ->assertRedirectContains('/accessibility-auditor?report=')
            ->assertSessionHas('accessibility_audits.0.score', 71)
            ->assertSessionHas('accessibility_audits.0.counts.critical', 3)
            ->assertSessionHas('accessibility_audits.0.counts.serious', 1)
            ->assertSessionHas('accessibility_audits.0.issues.0.recommendation');

        $this->get(route('accessibility-auditor.index'))
            ->assertSee('Image is missing alternative text')
            ->assertSee('Form field has no accessible label')
            ->assertSee('Button has no accessible name')
            ->assertSee('Link has no accessible name')
            ->assertSee('WCAG 1.1.1');
    }

    public function test_redirect_to_private_network_is_blocked(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLIC_URL => Http::response('', 302, ['Location' => 'http://127.0.0.1/private']),
        ]);

        $this->from(route('accessibility-auditor.index'))
            ->post(route('accessibility-auditor.audits.store'), ['url' => self::PUBLIC_URL])
            ->assertRedirect(route('accessibility-auditor.index'))
            ->assertSessionHasErrors(['url' => 'Private or local network addresses cannot be audited.']);

        Http::assertSentCount(1);
    }

    public function test_saved_audit_can_be_rerun_and_deleted(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            self::PUBLIC_URL => Http::response($this->accessibleHtml(), 200, ['Content-Type' => 'text/html']),
        ]);
        $storedAudit = ['id' => 'audit-one', 'url' => self::PUBLIC_URL, 'name' => 'Homepage audit'];

        $this->withSession(['accessibility_audits' => [$storedAudit]])
            ->post(route('accessibility-auditor.audits.rerun', 'audit-one'))
            ->assertRedirectContains('/accessibility-auditor?report=')
            ->assertSessionHas('accessibility_audits', fn (array $audits): bool => count($audits) === 2);

        $this->delete(route('accessibility-auditor.audits.destroy', 'audit-one'))
            ->assertRedirect(route('accessibility-auditor.index'))
            ->assertSessionHas('accessibility_audits', fn (array $audits): bool => collect($audits)->doesntContain('id', 'audit-one'));
    }

    private function accessibleHtml(): string
    {
        return <<<'HTML'
            <!doctype html><html lang="en"><head><title>Accessible Shop</title></head><body>
            <nav aria-label="Primary"><a href="/products">View products</a></nav><main><h1>Accessible Shop</h1>
            <img src="hero.jpg" alt="A customer browsing products"><form><label for="email">Email</label>
            <input id="email" type="email"><button type="submit">Subscribe</button></form>
            <table><tr><th scope="col">Product</th></tr><tr><td>Notebook</td></tr></table></main></body></html>
            HTML;
    }
}
