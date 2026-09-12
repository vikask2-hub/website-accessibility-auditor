# Website Accessibility Auditor

[![Live demo](https://img.shields.io/badge/Live_Demo-tech4projects.online-2563eb?style=for-the-badge)](https://tech4projects.online/accessibility-auditor)
[![WCAG](https://img.shields.io/badge/Guidance-WCAG_2.2-005a9c)](https://www.w3.org/TR/WCAG22/)

A lightweight website audit tool that turns a URL into prioritized accessibility findings, plain-language impact, and concrete remediation guidance.

## Product highlights

- URL-based audit workflow with score, severity, and category summaries
- Checks for document semantics, language, headings, labels, links, images, and common ARIA problems
- WCAG-informed remediation steps that explain both impact and a practical fix
- Re-run, report history, and deletion flows in one responsive interface
- SSRF-aware URL validation, bounded downloads, redirects, and timeouts
- Feature tests with isolated HTTP responses for repeatable audits

## Stack

PHP 8.3+ · Laravel 13 · DOMDocument · Blade · Tailwind CSS 4 · PHPUnit

## Run locally

```bash
git clone https://github.com/vikask2-hub/website-accessibility-auditor.git
cd website-accessibility-auditor
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
php artisan serve
```

Only public HTTP/HTTPS pages are accepted; private and reserved network targets are rejected.

---

Built by [Vikas Kaithia](https://github.com/vikask2-hub) · [View the complete product portfolio](https://tech4projects.online/)
