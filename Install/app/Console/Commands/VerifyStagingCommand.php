<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Staging verify runner (one command).
 *
 * Runs the canonical critical path: requirement flow, template catalog, CMS binding,
 * AI validator, demo seeder. Use before promoting staging to production.
 *
 * @see new tasks.txt — Testing Strategy: "Add: staging verify runner (one command)"
 * @see docs/deployment/RELEASE_CHECKLIST.md — Run staging verification
 */
class VerifyStagingCommand extends Command
{
    protected $signature = 'verify:staging
                            {--seed : Run database seed (e.g. TemplateSeeder) before tests when using non-memory DB}
                            {--url= : Base URL for tests that hit HTTP (overrides APP_URL)}';

    protected $description = 'Run staging verification (critical path: requirement flow, template catalog, binding, AI patch)';

    public function handle(): int
    {
        $this->info('Running staging verification (critical path tests)…');

        $tests = [
            'tests/Feature/RequirementCollectionFlowTest.php',
            'tests/Feature/Ecommerce/EcommerceTemplateCatalogTest.php',
            'tests/Feature/Ecommerce/CmsToStorefrontBindingTest.php',
            'tests/Feature/Cms/AiContentPatchFlowTest.php',
            'tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php',
            'tests/Feature/Commands/GenerateAiTestSitesCommandTest.php',
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

        $url = $this->option('url');
        if (is_string($url) && $url !== '') {
            $env['APP_URL'] = $url;
        }

        if ($this->option('seed') && config('database.default') !== 'sqlite') {
            $this->info('Running database seed (--seed)…');
            $seedProcess = new Process([$php, 'artisan', 'db:seed', '--class=TemplateSeeder'], base_path(), $baseEnv, null, 60);
            $seedProcess->run();
            if (! $seedProcess->isSuccessful()) {
                $this->warn('Seed completed with warnings or non-zero exit.');
            }
        }

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
            $this->error('Staging verification failed.');
            return self::FAILURE;
        }

        $this->info('Staging verification passed.');
        return self::SUCCESS;
    }
}
