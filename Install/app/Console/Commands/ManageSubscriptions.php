<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use App\Models\OperationLog;
use App\Models\Subscription;
use App\Notifications\SubscriptionRenewalReminderNotification;
use App\Services\OperationLogService;
use App\Services\SubscriptionLifecycleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ManageSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:manage
                            {--triggered-by=cron : Who triggered this command (cron or manual:user_id)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage subscription lifecycle: renewal attempts, retries, grace/suspension, and reminders';

    protected int $due = 0;

    protected int $renewed = 0;

    protected int $retryScheduled = 0;

    protected int $movedToGrace = 0;

    protected int $suspended = 0;

    protected int $remindersSent = 0;

    protected int $errors = 0;

    /**
     * Execute the console command.
     */
    public function handle(SubscriptionLifecycleService $lifecycle): int
    {
        $cronLog = CronLog::startLog(
            'Manage Subscriptions',
            self::class,
            $this->option('triggered-by')
        );

        try {
            $this->info('Managing subscriptions lifecycle...');

            $summary = $lifecycle->processScheduledLifecycle();
            $this->due = (int) ($summary['due'] ?? 0);
            $this->renewed = (int) ($summary['renewed'] ?? 0);
            $this->retryScheduled = (int) ($summary['retry_scheduled'] ?? 0);
            $this->movedToGrace = (int) ($summary['moved_to_grace'] ?? 0);
            $this->suspended = (int) ($summary['suspended'] ?? 0);
            $this->errors += (int) ($summary['errors'] ?? 0);

            $this->info(sprintf(
                'Renewal processing: due=%d, renewed=%d, retry_scheduled=%d, moved_to_grace=%d, suspended=%d, errors=%d',
                $this->due,
                $this->renewed,
                $this->retryScheduled,
                $this->movedToGrace,
                $this->suspended,
                $this->errors
            ));

            // Send renewal reminders
            $this->sendRenewalReminders();

            $message = sprintf(
                'Due: %d, Renewed: %d, Retry scheduled: %d, Grace: %d, Suspended: %d, Reminders: %d, Errors: %d',
                $this->due,
                $this->renewed,
                $this->retryScheduled,
                $this->movedToGrace,
                $this->suspended,
                $this->remindersSent,
                $this->errors
            );
            $this->info("Completed. {$message}");

            $cronLog->markSuccess($message);

            Log::info('Subscription management completed', [
                'due' => $this->due,
                'renewed' => $this->renewed,
                'retry_scheduled' => $this->retryScheduled,
                'moved_to_grace' => $this->movedToGrace,
                'suspended' => $this->suspended,
                'reminders_sent' => $this->remindersSent,
                'errors' => $this->errors,
            ]);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to manage subscriptions: {$e->getMessage()}");

            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());

            Log::error('Subscription management failed', [
                'error' => $e->getMessage(),
            ]);

            app(OperationLogService::class)->logSubscription(
                event: 'subscriptions_manage_failed',
                status: OperationLog::STATUS_ERROR,
                message: $e->getMessage(),
                attributes: [
                    'source' => self::class,
                    'context' => [
                        'due' => $this->due,
                        'renewed' => $this->renewed,
                        'retry_scheduled' => $this->retryScheduled,
                        'moved_to_grace' => $this->movedToGrace,
                        'suspended' => $this->suspended,
                        'reminders_sent' => $this->remindersSent,
                        'errors' => $this->errors,
                    ],
                ]
            );

            return self::FAILURE;
        }
    }

    /**
     * Send renewal reminders.
     */
    protected function sendRenewalReminders(): void
    {
        $this->info('Sending renewal reminders...');

        // Send 3-day reminder
        $this->sendRemindersForDays(3);

        // Send 1-day reminder (final reminder)
        $this->sendRemindersForDays(1);

        $this->info("  Sent {$this->remindersSent} renewal reminders");
    }

    /**
     * Send reminders for subscriptions expiring in N days.
     */
    protected function sendRemindersForDays(int $days): void
    {
        $startOfDay = now()->addDays($days)->startOfDay();
        $endOfDay = now()->addDays($days)->endOfDay();

        $subscriptions = Subscription::query()
            ->whereIn('status', Subscription::billableStatuses())
            ->whereNotNull('renewal_at')
            ->whereBetween('renewal_at', [$startOfDay, $endOfDay])
            ->with(['user', 'plan'])
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $user = $subscription->user;

                if (! $user) {
                    continue;
                }

                $user->notify(new SubscriptionRenewalReminderNotification($subscription, $days));

                app(OperationLogService::class)->logSubscription(
                    event: 'subscription_renewal_reminder_sent',
                    status: OperationLog::STATUS_INFO,
                    message: "{$days}-day renewal reminder sent to {$user->email}.",
                    attributes: [
                        'source' => self::class,
                        'user_id' => $user->id,
                        'identifier' => (string) $subscription->id,
                        'context' => [
                            'subscription_id' => $subscription->id,
                            'days_before_renewal' => $days,
                            'renewal_at' => $subscription->renewal_at?->toISOString(),
                        ],
                    ]
                );

                $this->line("  Sent {$days}-day reminder to {$user->email}");
                $this->remindersSent++;
            } catch (\Exception $e) {
                $this->error("  Failed to send reminder for subscription #{$subscription->id}: {$e->getMessage()}");
                $this->errors++;

                Log::error('Failed to send renewal reminder', [
                    'subscription_id' => $subscription->id,
                    'days' => $days,
                    'error' => $e->getMessage(),
                ]);

                app(OperationLogService::class)->logSubscription(
                    event: 'subscription_renewal_reminder_failed',
                    status: OperationLog::STATUS_ERROR,
                    message: $e->getMessage(),
                    attributes: [
                        'source' => self::class,
                        'user_id' => $subscription->user_id,
                        'identifier' => (string) $subscription->id,
                        'context' => [
                            'subscription_id' => $subscription->id,
                            'days_before_renewal' => $days,
                        ],
                    ]
                );
            }
        }
    }
}
