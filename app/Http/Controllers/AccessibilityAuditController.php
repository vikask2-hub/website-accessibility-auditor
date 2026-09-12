<?php

namespace App\Http\Controllers;

use App\Http\Requests\RunAccessibilityAuditRequest;
use App\Services\AccessibilityAuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccessibilityAuditController extends Controller
{
    public function index(Request $request): View
    {
        $audits = $request->session()->get('accessibility_audits', []);
        $selectedId = (string) $request->query('report', '');
        $selectedAudit = collect($audits)->firstWhere('id', $selectedId) ?? (end($audits) ?: null);

        return view('accessibility-auditor', [
            'audit' => $selectedAudit,
            'audits' => array_reverse($audits),
        ]);
    }

    public function store(RunAccessibilityAuditRequest $request, AccessibilityAuditService $auditor): RedirectResponse
    {
        return $this->runAudit(
            $request,
            $auditor,
            $request->string('url')->toString(),
            $request->string('audit_name')->trim()->toString() ?: null,
        );
    }

    public function rerun(Request $request, AccessibilityAuditService $auditor, string $audit): RedirectResponse
    {
        $previousAudit = collect($request->session()->get('accessibility_audits', []))->firstWhere('id', $audit);

        abort_unless($previousAudit, 404);

        return $this->runAudit($request, $auditor, $previousAudit['url'], $previousAudit['name']);
    }

    public function destroy(Request $request, string $audit): RedirectResponse
    {
        $audits = collect($request->session()->get('accessibility_audits', []))
            ->reject(fn (array $storedAudit): bool => $storedAudit['id'] === $audit)
            ->values()
            ->all();

        $request->session()->put('accessibility_audits', $audits);

        return redirect()->route('accessibility-auditor.index')->with('status', 'Report removed.');
    }

    private function runAudit(
        Request $request,
        AccessibilityAuditService $auditor,
        string $url,
        ?string $name,
    ): RedirectResponse {
        try {
            $audit = $auditor->audit($url, $name);
        } catch (\DomainException $exception) {
            return back()->withInput()->withErrors(['url' => $exception->getMessage()]);
        } catch (\Throwable) {
            return back()->withInput()->withErrors([
                'url' => 'We could not audit this website. Verify that it is publicly accessible and try again.',
            ]);
        }

        $audits = $request->session()->get('accessibility_audits', []);
        $audits[] = $audit;
        $request->session()->put('accessibility_audits', array_slice($audits, -8));

        return redirect()->route('accessibility-auditor.index', ['report' => $audit['id']]);
    }
}
