<?php

namespace Database\Seeders;

use App\Models\AiProvider;
use App\Models\Builder;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    /**
     * Seed demo mode configuration.
     *
     * Only runs when APP_ENV=local AND APP_DEMO=true.
     * Configures:
     * - Demo ZhipuAI provider with glm-5 model
     * - Pusher broadcast settings
     * - System default AI provider and builder
     * - Internal AI provider with glm-4.5-air model
     * - All templates assigned to all plans
     * - Referral system enabled
     * - Demo transactions for admin user
     */
    public function run(): void
    {
        // Guard: Only run when APP_ENV=local AND APP_DEMO=true
        if (! config('app.demo')) {
            $this->command?->info('DemoSeeder: Skipped (not in demo mode)');

            return;
        }

        $this->command?->info('DemoSeeder: Configuring demo mode...');

        // 1. Create Demo AI Provider (ZhipuAI)
        $aiProvider = $this->seedDemoAiProvider();

        // 2. Seed Pusher broadcast settings
        $this->seedBroadcastSettings();

        // 3. Set demo AI provider as system default
        $this->setDefaultAiProvider($aiProvider);

        // 4. Set default builder
        $this->setDefaultBuilder();

        // 5. Configure internal AI provider
        $this->seedInternalAiProvider($aiProvider);

        // 6. Assign all templates to all plans
        $this->assignTemplatesToPlans();

        // 7. Enable referral system
        $this->enableReferralSystem();

        // 8. Create demo transactions for admin
        $this->seedDemoTransactions();

        $this->command?->info('DemoSeeder: Demo mode configuration complete!');
    }

    /**
     * Create or update the demo ZhipuAI provider.
     */
    private function seedDemoAiProvider(): AiProvider
    {
        $apiKey = env('APP_DEMO_AI_KEY');

        if (empty($apiKey)) {
            $this->command?->warn('APP_DEMO_AI_KEY not set - AI provider will have empty credentials');
        }

        $provider = AiProvider::updateOrCreate(
            ['type' => AiProvider::TYPE_ZHIPU, 'name' => 'Demo ZhipuAI'],
            [
                'credentials' => ['api_key' => $apiKey],
                'config' => [
                    'base_url' => AiProvider::DEFAULT_BASE_URLS[AiProvider::TYPE_ZHIPU],
                    'default_model' => 'glm-5',
                    'max_tokens' => 16384,
                    'summarizer_max_tokens' => 1000,
                ],
                'available_models' => AiProvider::DEFAULT_MODELS[AiProvider::TYPE_ZHIPU],
                'status' => 'active',
                'is_default' => false,
            ]
        );

        $this->command?->info("Created/updated Demo ZhipuAI provider (ID: {$provider->id})");

        return $provider;
    }

    /**
     * Seed Pusher broadcast settings from demo environment variables.
     */
    private function seedBroadcastSettings(): void
    {
        $settings = [
            'broadcast_driver' => 'pusher',
            'pusher_app_id' => env('APP_DEMO_PUSHER_APPID', ''),
            'pusher_key' => env('APP_DEMO_PUSHER_KEY', ''),
            'pusher_secret' => env('APP_DEMO_PUSHER_SECRET', ''),
            'pusher_cluster' => env('APP_DEMO_PUSHER_CLUSTER', 'mt1'),
        ];

        if (empty($settings['pusher_app_id']) || empty($settings['pusher_key'])) {
            $this->command?->warn('Demo Pusher credentials not fully configured');
        }

        SystemSetting::setMany($settings, 'integrations');
        $this->command?->info('Configured Pusher broadcast settings');
    }

    /**
     * Set the demo AI provider as the system default.
     */
    private function setDefaultAiProvider(AiProvider $aiProvider): void
    {
        SystemSetting::set('default_ai_provider_id', $aiProvider->id, 'integer', 'plans');
        $this->command?->info("Set default AI provider: {$aiProvider->name} (ID: {$aiProvider->id})");
    }

    /**
     * Set the Local Builder as the system default builder.
     */
    private function setDefaultBuilder(): void
    {
        $builder = Builder::where('name', 'Local Builder')->first();

        if (! $builder) {
            $this->command?->warn('Local Builder not found - default builder not set');

            return;
        }

        SystemSetting::set('default_builder_id', $builder->id, 'integer', 'plans');
        $this->command?->info("Set default builder: {$builder->name} (ID: {$builder->id})");
    }

    /**
     * Configure the internal AI provider for landing page content generation.
     */
    private function seedInternalAiProvider(AiProvider $aiProvider): void
    {
        SystemSetting::set('internal_ai_provider_id', $aiProvider->id, 'integer', 'integrations');
        SystemSetting::set('internal_ai_model', 'glm-4.5-air', 'string', 'integrations');
        $this->command?->info('Configured internal AI provider with glm-4.5-air model');
    }

    /**
     * Assign all templates to all plans.
     */
    private function assignTemplatesToPlans(): void
    {
        $templateIds = Template::pluck('id')->toArray();
        $plans = Plan::all();

        if (empty($templateIds)) {
            $this->command?->warn('No templates found to assign');

            return;
        }

        if ($plans->isEmpty()) {
            $this->command?->warn('No plans found to assign templates to');

            return;
        }

        $plans->each(fn ($plan) => $plan->templates()->syncWithoutDetaching($templateIds));

        $this->command?->info(
            'Assigned '.count($templateIds).' templates to '.$plans->count().' plans'
        );
    }

    /**
     * Enable the referral system for demo mode.
     */
    private function enableReferralSystem(): void
    {
        SystemSetting::set('referral_enabled', true, 'boolean', 'referral');
        $this->command?->info('Enabled referral system');
    }

    /**
     * Create demo transactions for the admin user.
     */
    private function seedDemoTransactions(): void
    {
        $admin = User::where('email', 'admin@webby.com')->first();

        if (! $admin) {
            $this->command?->warn('Admin user not found - demo transactions not created');

            return;
        }

        $subscription = Subscription::where('user_id', $admin->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->first();

        if (! $subscription) {
            $this->command?->warn('Admin subscription not found - demo transactions not created');

            return;
        }

        $currency = SystemSetting::get('default_currency', 'USD');

        // Sample transactions with different dates and types
        $transactions = [
            [
                'user_id' => $admin->id,
                'subscription_id' => $subscription->id,
                'amount' => 29.00,
                'currency' => $currency,
                'status' => Transaction::STATUS_COMPLETED,
                'type' => Transaction::TYPE_SUBSCRIPTION_NEW,
                'payment_method' => 'stripe',
                'transaction_date' => now()->subMonths(3),
                'external_transaction_id' => 'pi_demo_'.uniqid(),
                'notes' => 'Initial Pro plan subscription',
            ],
            [
                'user_id' => $admin->id,
                'subscription_id' => $subscription->id,
                'amount' => 29.00,
                'currency' => $currency,
                'status' => Transaction::STATUS_COMPLETED,
                'type' => Transaction::TYPE_SUBSCRIPTION_RENEWAL,
                'payment_method' => 'stripe',
                'transaction_date' => now()->subMonths(2),
                'external_transaction_id' => 'pi_demo_'.uniqid(),
                'notes' => 'Monthly renewal',
            ],
            [
                'user_id' => $admin->id,
                'subscription_id' => $subscription->id,
                'amount' => 29.00,
                'currency' => $currency,
                'status' => Transaction::STATUS_COMPLETED,
                'type' => Transaction::TYPE_SUBSCRIPTION_RENEWAL,
                'payment_method' => 'stripe',
                'transaction_date' => now()->subMonth(),
                'external_transaction_id' => 'pi_demo_'.uniqid(),
                'notes' => 'Monthly renewal',
            ],
            [
                'user_id' => $admin->id,
                'subscription_id' => $subscription->id,
                'amount' => 29.00,
                'currency' => $currency,
                'status' => Transaction::STATUS_COMPLETED,
                'type' => Transaction::TYPE_SUBSCRIPTION_RENEWAL,
                'payment_method' => 'stripe',
                'transaction_date' => now()->subDays(5),
                'external_transaction_id' => 'pi_demo_'.uniqid(),
                'notes' => 'Monthly renewal',
            ],
        ];

        $created = 0;
        foreach ($transactions as $txnData) {
            // Use firstOrCreate to avoid duplicates on re-run
            $existing = Transaction::where('user_id', $txnData['user_id'])
                ->where('type', $txnData['type'])
                ->whereDate('transaction_date', $txnData['transaction_date'])
                ->first();

            if (! $existing) {
                Transaction::create($txnData);
                $created++;
            }
        }

        $this->command?->info("Created {$created} demo transactions for admin user");
    }
}
