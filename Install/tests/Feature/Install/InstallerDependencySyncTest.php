<?php

namespace Tests\Feature\Install;

use App\Services\InstallerService;
use App\Services\PlatformRequirementService;
use Tests\TestCase;

class InstallerDependencySyncTest extends TestCase
{
    public function test_installer_marks_grpc_as_required_when_runtime_inspector_requires_it(): void
    {
        $requirements = new class extends PlatformRequirementService
        {
            public function phpVersionStatus(): array
            {
                return [
                    'minimum' => '8.4.0',
                    'current' => '8.4.1',
                    'ok' => true,
                ];
            }

            public function extensionStatus(string $extension): array
            {
                return [
                    'extension' => 'ext-grpc',
                    'loaded' => false,
                    'required' => true,
                    'required_by' => ['vendor/package (^1.0)'],
                ];
            }
        };

        $installer = new InstallerService($requirements);
        $details = collect($installer->getDependencyDetailsForUi())->keyBy('id');

        $this->assertTrue($details->has('ext_grpc'));
        $this->assertTrue((bool) $details['ext_grpc']['required']);
        $this->assertFalse((bool) $details['ext_grpc']['status']);
        $this->assertFalse($installer->isDependencyPassed('ext_grpc'));
    }

    public function test_installer_treats_grpc_as_conditional_when_not_required_by_dependencies(): void
    {
        $requirements = new class extends PlatformRequirementService
        {
            public function phpVersionStatus(): array
            {
                return [
                    'minimum' => '8.4.0',
                    'current' => '8.4.1',
                    'ok' => true,
                ];
            }

            public function extensionStatus(string $extension): array
            {
                return [
                    'extension' => 'ext-grpc',
                    'loaded' => false,
                    'required' => false,
                    'required_by' => [],
                ];
            }
        };

        $installer = new InstallerService($requirements);
        $details = collect($installer->getDependencyDetailsForUi())->keyBy('id');

        $this->assertTrue($details->has('ext_grpc'));
        $this->assertFalse((bool) $details['ext_grpc']['required']);
        $this->assertTrue((bool) $details['ext_grpc']['status']);
        $this->assertTrue($installer->isDependencyPassed('ext_grpc'));
    }
}
