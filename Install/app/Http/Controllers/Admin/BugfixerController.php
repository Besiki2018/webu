<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Process;
use Inertia\Inertia;
use Inertia\Response;
use ZipArchive;

class BugfixerController extends Controller
{
    /** Redirect /admin/qa to bugfixer dashboard (Tab 9 QA page). */
    public function redirectToBugfixer(): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route('admin.bugfixer', [], 302);
    }

    private function auditPath(string ...$parts): string
    {
        return base_path(implode(DIRECTORY_SEPARATOR, array_merge(['audit', 'bugfixer'], $parts)));
    }

    private function readEvents(): array
    {
        $dir = $this->auditPath('events');
        if (! is_dir($dir)) {
            return [];
        }
        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        $events = [];
        foreach ($files as $f) {
            if (! str_ends_with($f, '.json')) {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$f;
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $data = json_decode($content, true);
            if (is_array($data)) {
                $bugId = (string) ($data['bugId'] ?? pathinfo($f, PATHINFO_FILENAME));
                $data['bugId'] = $bugId;
                $data = array_merge($data, $this->bugFixState($bugId));
                $events[] = $data;
            }
        }
        usort($events, function ($a, $b) {
            $sev = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            $as = $sev[$a['severity'] ?? 'low'] ?? 0;
            $bs = $sev[$b['severity'] ?? 'low'] ?? 0;
            if ($as !== $bs) {
                return $bs <=> $as;
            }
            $af = (int) ($a['frequency'] ?? 0);
            $bf = (int) ($b['frequency'] ?? 0);
            return $bf <=> $af;
        });
        return $events;
    }

    private function readConfig(): array
    {
        $path = $this->auditPath('config.json');
        if (! file_exists($path)) {
            return [
                'autoFixEnabled' => true,
                'severityThreshold' => 'high',
                'humanApprovalRequired' => false,
            ];
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            return [
                'autoFixEnabled' => true,
                'severityThreshold' => 'high',
                'humanApprovalRequired' => false,
            ];
        }
        $data = json_decode($content, true);
        return array_merge([
            'autoFixEnabled' => true,
            'severityThreshold' => 'high',
            'humanApprovalRequired' => false,
        ], is_array($data) ? $data : []);
    }

    private function writeConfig(array $config): void
    {
        $dir = $this->auditPath();
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $this->auditPath('config.json'),
            json_encode($config, JSON_PRETTY_PRINT),
            LOCK_EX
        );
    }

    /**
     * Resolve current fix state for a bug from audit artifacts.
     *
     * @return array{fix_status: string, has_report: bool, has_ticket: bool}
     */
    private function bugFixState(string $bugId): array
    {
        $reportPath = $this->auditPath('reports', $bugId.'.json');
        $ticketPath = $this->auditPath('tickets', $bugId.'.md');

        $hasReport = file_exists($reportPath);
        $hasTicket = file_exists($ticketPath);
        $fixStatus = 'pending';

        if ($hasReport) {
            $fixStatus = 'fixed';
        } elseif ($hasTicket) {
            $fixStatus = 'ticket';
        }

        return [
            'fix_status' => $fixStatus,
            'has_report' => $hasReport,
            'has_ticket' => $hasTicket,
        ];
    }

    public function index(Request $request): Response
    {
        $events = $this->readEvents();
        $config = $this->readConfig();

        return Inertia::render('Admin/Bugfixer/Index', [
            'events' => $events,
            'config' => $config,
        ]);
    }

    public function show(Request $request, string $bugId): Response
    {
        $path = $this->auditPath('events', $bugId.'.json');
        $event = null;
        if (file_exists($path)) {
            $content = @file_get_contents($path);
            if ($content !== false) {
                $event = json_decode($content, true);
            }
        }
        if (! is_array($event)) {
            abort(404, 'Bug event not found');
        }

        $reproDir = $this->auditPath('repro', $bugId);
        $patchesDir = $this->auditPath('patches', $bugId);
        $verifyDir = $this->auditPath('verify', $bugId);
        $ticketPath = $this->auditPath('tickets', $bugId.'.md');
        $reportPath = $this->auditPath('reports', $bugId.'.json');

        $reproFiles = is_dir($reproDir) ? array_values(array_diff(scandir($reproDir) ?: [], ['.', '..'])) : [];
        $verifyLogs = is_dir($verifyDir) ? array_values(array_diff(scandir($verifyDir) ?: [], ['.', '..'])) : [];
        $hasPatch = is_dir($patchesDir) && file_exists($patchesDir.DIRECTORY_SEPARATOR.'applied.diff');
        $hasTicket = file_exists($ticketPath);
        $hasReport = file_exists($reportPath);

        return Inertia::render('Admin/Bugfixer/Show', [
            'event' => $event,
            'reproFiles' => $reproFiles,
            'verifyLogs' => $verifyLogs,
            'hasPatch' => $hasPatch,
            'hasTicket' => $hasTicket,
            'hasReport' => $hasReport,
            'reproDir' => $reproDir,
            'ticketPath' => $hasTicket ? $ticketPath : null,
        ]);
    }

    /**
     * Resolve PATH so npm/npx are findable (web server often has minimal PATH).
     */
    private function pathForNode(): array
    {
        $custom = config('bugfixer.npm_path') ?? env('BUGFIXER_NPM_PATH');
        if ($custom && is_string($custom) && $custom !== '' && (is_file($custom) || is_dir($custom))) {
            return ['PATH' => dirname($custom).':'.(getenv('PATH') ?: '')];
        }
        $prepend = [];
        $home = getenv('HOME') ?: '';
        if ($home !== '' && is_dir($home.'/.nvm/versions/node')) {
            $versions = glob($home.'/.nvm/versions/node/*/bin');
            if ($versions !== false && $versions !== []) {
                rsort($versions);
                $prepend = array_merge($prepend, $versions);
            }
        }
        $prepend = array_merge($prepend, ['/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin']);
        $prepend = array_filter($prepend, fn ($p) => is_dir($p));
        $existing = getenv('PATH') ?: '';
        $path = $prepend !== [] ? implode(':', array_unique($prepend)).':'.$existing : $existing;

        return ['PATH' => $path];
    }

    public function runAutoFix(Request $request)
    {
        $validated = $request->validate(['bugId' => 'required|string']);
        $bugId = (string) $validated['bugId'];
        $base = base_path();
        $command = 'npm run bugfixer:fix -- '.escapeshellarg($bugId);
        $result = Process::timeout(300)
            ->path($base)
            ->env($this->pathForNode())
            ->run($command);

        if (! $result->successful()) {
            $error = trim((string) $result->errorOutput());
            if ($error === '') {
                $error = trim((string) $result->output());
            }
            if ($error === '') {
                $error = 'Unknown process error.';
            }
            $error = substr($error, 0, 1200);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'bugId' => $bugId,
                    'message' => 'Auto-fix run failed: '.$error,
                    ...$this->bugFixState($bugId),
                ], 500);
            }

            return back()->with('error', 'Auto-fix run failed: '.$error);
        }

        $state = $this->bugFixState($bugId);
        $message = match ($state['fix_status']) {
            'fixed' => 'Auto-fix completed and marked as fixed.',
            'ticket' => 'Auto-fix completed. Manual ticket was created.',
            default => 'Auto-fix run completed.',
        };

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'bugId' => $bugId,
                'message' => $message,
                ...$state,
            ]);
        }

        return back()->with('success', $message);
    }

    public function updateSettings(Request $request)
    {
        $config = $this->readConfig();
        $config['autoFixEnabled'] = (bool) $request->input('autoFixEnabled', $config['autoFixEnabled']);
        $config['severityThreshold'] = (string) $request->input('severityThreshold', $config['severityThreshold']);
        $config['humanApprovalRequired'] = (bool) $request->input('humanApprovalRequired', $config['humanApprovalRequired']);
        $this->writeConfig($config);
        return back()->with('success', 'Settings saved.');
    }

    public function downloadReproPack(string $bugId): \Illuminate\Http\Response
    {
        $reproDir = $this->auditPath('repro', $bugId);
        if (! is_dir($reproDir)) {
            abort(404, 'Repro pack not found');
        }
        $eventPath = $this->auditPath('events', $bugId.'.json');
        if (! file_exists($eventPath)) {
            abort(404, 'Bug event not found');
        }
        $tmpZip = sys_get_temp_dir().DIRECTORY_SEPARATOR.'bugfixer_repro_'.$bugId.'.zip';
        $zip = new ZipArchive;
        if ($zip->open($tmpZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            abort(500, 'Cannot create zip');
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($reproDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            if (! $file->isDir()) {
                $path = $file->getRealPath();
                $relative = substr($path, strlen($reproDir) + 1);
                $zip->addFile($path, $relative);
            }
        }
        $zip->close();
        $response = response()->download($tmpZip, "repro-{$bugId}.zip", [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
        return $response;
    }

    public function downloadPatch(string $bugId): \Illuminate\Http\Response
    {
        $patchFile = $this->auditPath('patches', $bugId, 'applied.diff');
        if (! file_exists($patchFile)) {
            abort(404, 'Patch not found');
        }
        $content = file_get_contents($patchFile);
        return response($content, 200, [
            'Content-Type' => 'text/x-diff',
            'Content-Disposition' => 'attachment; filename="applied-'.$bugId.'.diff"',
        ]);
    }

    public function downloadTicket(string $bugId): \Illuminate\Http\Response
    {
        $ticketFile = $this->auditPath('tickets', $bugId.'.md');
        if (! file_exists($ticketFile)) {
            abort(404, 'Ticket not found');
        }
        $content = file_get_contents($ticketFile);
        return response($content, 200, [
            'Content-Type' => 'text/markdown',
            'Content-Disposition' => 'attachment; filename="ticket-'.$bugId.'.md"',
        ]);
    }

    public function downloadVerifyLog(string $bugId, string $step): \Illuminate\Http\Response
    {
        $logFile = $this->auditPath('verify', $bugId, $step.'.log');
        if (! file_exists($logFile)) {
            abort(404, 'Verification log not found');
        }
        $content = file_get_contents($logFile);
        return response($content, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'inline; filename="'.$step.'.log"',
        ]);
    }
}
