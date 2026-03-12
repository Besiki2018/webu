<?php

namespace Tests\Unit;

use App\Services\RepoEditMode;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class RepoEditModeTest extends TestCase
{
    public function test_disabled_mode_returns_workspace(): void
    {
        $service = new RepoEditMode(false, [], []);
        $this->assertSame('workspace', $service->getMode());
        $this->assertFalse($service->isEnabled());
    }

    public function test_enabled_mode_returns_repo(): void
    {
        $service = new RepoEditMode(true, ['/allowed'], []);
        $this->assertSame('repo', $service->getMode());
        $this->assertTrue($service->isEnabled());
    }

    public function test_validate_path_rejects_path_outside_allowed_roots(): void
    {
        $service = new RepoEditMode(true, ['/var/www/projects'], []);
        $this->assertFalse($service->validatePath('/tmp/other/file.txt'));
    }

    public function test_validate_path_rejects_forbidden_patterns(): void
    {
        $tmpDir = realpath(sys_get_temp_dir());
        if ($tmpDir === false) {
            $this->markTestSkipped('Could not resolve temp dir');
        }
        $service = new RepoEditMode(true, [$tmpDir], ['.env', '.git']);
        $envPath = $tmpDir.DIRECTORY_SEPARATOR.'webu_test_env_'.uniqid().DIRECTORY_SEPARATOR.'.env';
        @mkdir(dirname($envPath), 0755, true);
        file_put_contents($envPath, '');
        $this->assertFalse($service->validatePath($envPath));
        @unlink($envPath);
        @rmdir(dirname($envPath));
    }

    public function test_audit_log_writes_to_stack(): void
    {
        Log::shouldReceive('channel')->with('stack')->andReturnSelf();
        Log::shouldReceive('info')->with('repo_edit_audit', \Mockery::on(function ($context) {
            return isset($context['operation'], $context['path'], $context['timestamp'])
                && $context['operation'] === 'write'
                && $context['path'] === '/path/to/file';
        }));

        $service = new RepoEditMode(true, [], []);
        $service->auditLog('write', '/path/to/file');
    }

    public function test_get_rollback_snapshot_path_format(): void
    {
        $service = new RepoEditMode(true, [], []);
        $path = $service->getRollbackSnapshotPath('/var/project/src/App.tsx');
        $this->assertStringStartsWith('/var/project/src/App.tsx.webu_rollback_', $path);
        $suffix = substr($path, strrpos($path, 'webu_rollback_') + strlen('webu_rollback_'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $suffix);
    }
}
