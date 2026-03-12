<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Run all tests that verify the 8-part production readiness (test sites, design, templates,
 * AI engine, CMS binding, checkout webhooks, AI editing, performance-related coverage).
 */
class VerifyProductionReadinessCommand extends Command
{
    protected $signature = 'webu:verify-production-readiness';

    protected $description = 'Run all 8-parts production readiness tests (GenerateAiTestSites, CMS binding, webhooks, template catalog, AI patch, requirement flow)';

    public function handle(): int
    {
        $this->info('Running production readiness test set (8 parts)…');

        $tests = [
            'tests/Feature/Commands/GenerateAiTestSitesCommandTest.php',
            'tests/Feature/Ecommerce/CmsToStorefrontBindingTest.php',
            'tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php',
            'tests/Feature/Ecommerce/EcommerceTemplateCatalogTest.php',
            'tests/Feature/Cms/AiContentPatchFlowTest.php',
            'tests/Feature/RequirementCollectionFlowTest.php',
        ];

        $php = defined('PHP_BINARY') ? PHP_BINARY : 'php';
        $baseEnv = is_array(getenv() ?: null) ? getenv() : [];
        $env = array_merge($baseEnv, [
            'APP_ENV' => 'testing',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'BROADCAST_CONNECTION' => 'null',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'PULSE_ENABLED' => 'false',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ]);
        $process = new Process(
            array_merge([$php, 'artisan', 'test'], $tests),
            base_path(),
            $env,
            null,
            120
        );

        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $process->isSuccessful()) {
            $this->error('Production readiness tests failed.');
            return self::FAILURE;
        }

        $this->info('All production readiness tests passed.');
        return self::SUCCESS;
    }
}
