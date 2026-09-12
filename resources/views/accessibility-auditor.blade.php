<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Audit a public webpage for common accessibility issues and practical fixes.">
    <title>A11y Scan · Website Accessibility Auditor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>body, button, input, select { font-family: Inter, ui-sans-serif, system-ui, sans-serif !important; }</style>
</head>
<body class="min-h-screen bg-[#f5f7fb] pt-16 text-slate-950 antialiased">
    <header class="fixed inset-x-0 top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur-xl">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
            <x-portfolio-back-button />
            <div class="flex items-center gap-2">
                <span class="hidden rounded-full bg-emerald-50 px-3 py-1.5 text-[9px] font-bold text-emerald-700 sm:block">● WCAG-informed checks</span>
                <span class="hidden text-right min-[390px]:block"><strong class="block text-[11px] font-extrabold text-slate-800">A11y Scan</strong><small class="block text-[8px] font-bold tracking-[.1em] text-slate-400 uppercase">Accessibility auditor</small></span>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <section class="relative overflow-hidden rounded-[2rem] bg-[#071521] px-5 py-7 text-white shadow-[0_24px_70px_rgba(2,132,199,.15)] sm:px-8 sm:py-9">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_90%_0%,rgba(34,211,238,.24),transparent_38%),radial-gradient(circle_at_0%_120%,rgba(14,165,233,.18),transparent_42%)]"></div>
            <div class="relative grid items-end gap-7 lg:grid-cols-[minmax(0,1fr)_minmax(480px,.9fr)]">
                <div>
                    <span class="inline-flex items-center gap-2 rounded-full border border-cyan-300/20 bg-cyan-300/10 px-3 py-1.5 text-[9px] font-bold tracking-[.12em] text-cyan-200 uppercase"><span class="size-1.5 rounded-full bg-cyan-300"></span> Inclusive web quality</span>
                    <h1 class="mt-5 max-w-2xl text-3xl font-extrabold tracking-[-.055em] text-white sm:text-5xl">See what your website<br><span class="text-cyan-300">leaves inaccessible.</span></h1>
                    <p class="mt-4 max-w-xl text-xs leading-5 text-slate-400 sm:text-sm">Scan a public webpage for common accessibility barriers and get prioritised, developer-ready fixes in seconds.</p>
                </div>

                <form method="POST" action="{{ route('accessibility-auditor.audits.store') }}" class="rounded-[1.4rem] border border-white/10 bg-white/[.07] p-3 backdrop-blur" id="audit-form">
                    @csrf
                    <label for="audit-url" class="sr-only">Website URL</label>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <div class="flex min-w-0 flex-1 items-center gap-2 rounded-xl bg-white px-3.5">
                            <span class="text-sm text-slate-400">⌁</span>
                            <input id="audit-url" name="url" value="{{ old('url') }}" placeholder="https://yourwebsite.com" required inputmode="url" autocomplete="url" class="h-12 min-w-0 flex-1 border-0 bg-transparent px-0 text-xs font-semibold text-slate-900 outline-none ring-0 placeholder:text-slate-400 focus:border-0 focus:ring-0">
                        </div>
                        <button id="audit-button" class="h-12 shrink-0 rounded-xl bg-cyan-400 px-5 text-[11px] font-extrabold text-slate-950 shadow-lg shadow-cyan-400/15 transition hover:bg-cyan-300 disabled:opacity-60">Run accessibility audit</button>
                    </div>
                    <p class="mt-2 px-1 text-[8px] font-medium text-slate-500">Public HTTP/HTTPS pages only · private networks are blocked</p>
                </form>
            </div>
        </section>

        @if($errors->any())
            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-700">{{ $errors->first() }}</div>
        @endif
        @if(session('status'))
            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-700">{{ session('status') }}</div>
        @endif

        @if($audit)
            <section class="mt-5 grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
                <aside class="space-y-4">
                    <article class="rounded-[1.6rem] border border-slate-200 bg-white p-5 shadow-[0_12px_35px_rgba(15,23,42,.05)]">
                        <div class="mx-auto grid size-36 place-items-center rounded-full" style="background:conic-gradient(#0891b2 {{ $audit['score'] }}%,#e2e8f0 0)">
                            <div class="grid size-[7.4rem] place-items-center rounded-full bg-white text-center"><div><strong class="block text-4xl font-extrabold tracking-[-.06em]">{{ $audit['score'] }}</strong><span class="text-[9px] font-bold tracking-[.14em] text-slate-400 uppercase">out of 100</span></div></div>
                        </div>
                        <div class="mt-4 text-center"><span class="rounded-full {{ $audit['score'] >= 75 ? 'bg-emerald-50 text-emerald-700' : ($audit['score'] >= 50 ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }} px-3 py-1.5 text-[9px] font-extrabold uppercase">{{ $audit['status'] }}</span><p class="mt-3 truncate text-xs font-extrabold">{{ $audit['name'] }}</p><a href="{{ $audit['url'] }}" target="_blank" rel="noopener noreferrer" class="mt-1 block truncate text-[9px] font-medium text-cyan-700">{{ $audit['host'] }} ↗</a></div>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-center"><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-sm">{{ count($audit['issues']) }}</strong><span class="text-[8px] font-bold text-slate-400">Issues</span></div><div class="rounded-xl bg-slate-50 p-3"><strong class="block text-sm">{{ $audit['passed_count'] }}</strong><span class="text-[8px] font-bold text-slate-400">Passed</span></div></div>
                        <form method="POST" action="{{ route('accessibility-auditor.audits.rerun', $audit['id']) }}" class="mt-3">@csrf<button class="w-full rounded-xl bg-slate-950 px-4 py-3 text-[10px] font-extrabold text-white transition hover:bg-cyan-700">Re-run this audit</button></form>
                        <p class="mt-3 text-center text-[8px] text-slate-400">Completed {{ $audit['audited_at'] }} · {{ number_format($audit['duration_ms'] / 1000, 1) }}s</p>
                    </article>

                    @if($audits)
                        <article class="rounded-[1.6rem] border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="flex items-center justify-between"><h2 class="text-[11px] font-extrabold">Recent audits</h2><span class="text-[8px] font-bold text-slate-400">{{ count($audits) }} saved</span></div>
                            <div class="mt-3 space-y-2">
                                @foreach(array_slice($audits, 0, 5) as $storedAudit)
                                    <div class="group flex items-center gap-2 rounded-xl border {{ $storedAudit['id'] === $audit['id'] ? 'border-cyan-200 bg-cyan-50' : 'border-slate-100' }} p-2.5">
                                        <a href="{{ route('accessibility-auditor.index', ['report' => $storedAudit['id']]) }}" class="min-w-0 flex-1"><strong class="block truncate text-[9px] text-slate-700">{{ $storedAudit['host'] }}</strong><span class="mt-0.5 block text-[8px] text-slate-400">Score {{ $storedAudit['score'] }} · {{ $storedAudit['audited_at'] }}</span></a>
                                        <form method="POST" action="{{ route('accessibility-auditor.audits.destroy', $storedAudit['id']) }}">@csrf @method('DELETE')<button aria-label="Delete {{ $storedAudit['host'] }} report" class="px-1 text-xs text-slate-300 transition hover:text-rose-600">×</button></form>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endif
                </aside>

                <div class="min-w-0 space-y-4">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                        @foreach([
                            ['Critical', $audit['counts']['critical'], 'bg-rose-50 text-rose-700'],
                            ['Serious', $audit['counts']['serious'], 'bg-orange-50 text-orange-700'],
                            ['Moderate', $audit['counts']['moderate'], 'bg-amber-50 text-amber-700'],
                            ['Minor', $audit['counts']['minor'], 'bg-blue-50 text-blue-700'],
                            ['Passed', $audit['passed_count'], 'bg-emerald-50 text-emerald-700'],
                        ] as [$label, $value, $colour])
                            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm"><span class="grid size-7 place-items-center rounded-lg {{ $colour }} text-[10px] font-black">{{ $value }}</span><strong class="mt-2 block text-[9px] text-slate-500">{{ $label }}</strong></div>
                        @endforeach
                    </div>

                    <section class="rounded-[1.6rem] border border-slate-200 bg-white shadow-[0_12px_35px_rgba(15,23,42,.05)]">
                        <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-[8px] font-extrabold tracking-[.14em] text-cyan-700 uppercase">Audit findings</p><h2 class="mt-1 text-base font-extrabold tracking-[-.03em]">Prioritised issues and fixes</h2></div>
                            <div class="flex gap-2">
                                <select id="severity-filter" aria-label="Filter by severity" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[9px] font-bold text-slate-600 outline-none"><option value="all">All severities</option><option value="critical">Critical</option><option value="serious">Serious</option><option value="moderate">Moderate</option><option value="minor">Minor</option></select>
                                <select id="category-filter" aria-label="Filter by category" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-[9px] font-bold text-slate-600 outline-none"><option value="all">All categories</option>@foreach(collect($audit['issues'])->pluck('category')->unique()->sort()->values() as $category)<option value="{{ str($category)->lower() }}">{{ $category }}</option>@endforeach</select>
                            </div>
                        </div>
                        <div class="divide-y divide-slate-100" id="issue-list">
                            @forelse($audit['issues'] as $issue)
                                @php($severityClasses = ['critical' => 'bg-rose-50 text-rose-700', 'serious' => 'bg-orange-50 text-orange-700', 'moderate' => 'bg-amber-50 text-amber-700', 'minor' => 'bg-blue-50 text-blue-700'][$issue['severity']])
                                <details class="issue-item group p-4 sm:p-5" data-severity="{{ $issue['severity'] }}" data-category="{{ str($issue['category'])->lower() }}">
                                    <summary class="flex cursor-pointer list-none items-start gap-3">
                                        <span class="mt-0.5 grid size-8 shrink-0 place-items-center rounded-xl {{ $severityClasses }} text-xs font-black">!</span>
                                        <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="text-[11px] font-extrabold text-slate-800">{{ $issue['title'] }}</h3><span class="rounded-full {{ $severityClasses }} px-2 py-1 text-[7px] font-extrabold uppercase">{{ $issue['severity'] }}</span><span class="text-[8px] font-semibold text-slate-400">{{ $issue['category'] }}</span></div><p class="mt-1.5 text-[9px] leading-4 text-slate-500">{{ $issue['description'] }}</p></div>
                                        <span class="text-sm text-slate-300 transition group-open:rotate-45">+</span>
                                    </summary>
                                    <div class="mt-4 grid gap-3 pl-11 xl:grid-cols-2">
                                        <div><p class="text-[8px] font-extrabold tracking-wider text-slate-400 uppercase">Affected element · {{ $issue['selector'] }}</p><code class="mt-2 block overflow-x-auto rounded-xl bg-slate-950 p-3 text-[9px] leading-4 text-cyan-200">{{ $issue['element'] }}</code></div>
                                        <div class="rounded-xl bg-emerald-50 p-3"><p class="text-[8px] font-extrabold tracking-wider text-emerald-700 uppercase">Recommended fix · {{ $issue['wcag'] }}</p><p class="mt-2 text-[9px] font-medium leading-4 text-emerald-900">{{ $issue['recommendation'] }}</p></div>
                                    </div>
                                </details>
                            @empty
                                <div class="p-10 text-center"><span class="text-2xl">✓</span><h3 class="mt-3 text-sm font-extrabold">No automated issues detected</h3><p class="mt-2 text-[10px] text-slate-400">Manual accessibility testing is still recommended.</p></div>
                            @endforelse
                        </div>
                        <div id="empty-filter" class="hidden p-8 text-center text-[10px] font-semibold text-slate-400">No issues match these filters.</div>
                        @if($audit['truncated'])<p class="border-t border-slate-100 px-5 py-3 text-[9px] text-amber-700">Showing the first 100 findings. Fix these issues and run the audit again.</p>@endif
                    </section>

                    <p class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-[9px] leading-4 text-slate-400"><strong class="text-slate-600">Important:</strong> This is an indicative automated audit, not a WCAG certification. Keyboard, screen-reader, content, and visual contrast testing should also be completed manually.</p>
                </div>
            </section>
        @else
            <section class="mt-5 grid gap-4 md:grid-cols-3">
                @foreach([
                    ['01', 'Enter a public URL', 'Add any public homepage or landing page. The scanner blocks private networks and unsafe protocols.'],
                    ['02', 'Review prioritised issues', 'See critical, serious, moderate and minor findings with affected HTML elements.'],
                    ['03', 'Apply practical fixes', 'Use clear WCAG-informed recommendations, then re-run the page to measure improvement.'],
                ] as [$number, $title, $copy])
                    <article class="rounded-[1.4rem] border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[9px] font-extrabold text-cyan-700">{{ $number }}</span><h2 class="mt-3 text-sm font-extrabold tracking-[-.03em]">{{ $title }}</h2><p class="mt-2 text-[10px] leading-5 text-slate-400">{{ $copy }}</p></article>
                @endforeach
            </section>
        @endif
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const auditForm = document.getElementById('audit-form');
            const auditButton = document.getElementById('audit-button');
            const severityFilter = document.getElementById('severity-filter');
            const categoryFilter = document.getElementById('category-filter');
            const issues = [...document.querySelectorAll('.issue-item')];
            const emptyFilter = document.getElementById('empty-filter');

            auditForm?.addEventListener('submit', () => { auditButton.disabled = true; auditButton.textContent = 'Scanning webpage…'; });

            const filterIssues = () => {
                let visible = 0;
                issues.forEach((issue) => {
                    const show = (severityFilter.value === 'all' || issue.dataset.severity === severityFilter.value) && (categoryFilter.value === 'all' || issue.dataset.category === categoryFilter.value);
                    issue.classList.toggle('hidden', ! show);
                    if (show) visible++;
                });
                emptyFilter?.classList.toggle('hidden', visible > 0);
            };
            severityFilter?.addEventListener('change', filterIssues);
            categoryFilter?.addEventListener('change', filterIssues);
        });
    </script>
</body>
</html>
