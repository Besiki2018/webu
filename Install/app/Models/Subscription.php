<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    // Subscription statuses
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_GRACE = 'grace';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    // Payment methods
    public const PAYMENT_PAYPAL = 'paypal';

    public const PAYMENT_BANK_TRANSFER = 'bank_transfer';

    public const PAYMENT_MANUAL = 'manual';

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'amount',
        'payment_method',
        'external_subscription_id',
        'billing_info',
        'approved_by',
        'approved_at',
        'admin_notes',
        'payment_proof',
        'starts_at',
        'renewal_at',
        'renewal_retry_count',
        'renewal_retry_limit',
        'last_renewal_attempt_at',
        'next_retry_at',
        'grace_ends_at',
        'suspended_at',
        'last_renewal_error',
        'ends_at',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'billing_info' => 'array',
            'metadata' => 'array',
            'approved_at' => 'datetime',
            'starts_at' => 'datetime',
            'renewal_at' => 'datetime',
            'renewal_retry_count' => 'integer',
            'renewal_retry_limit' => 'integer',
            'last_renewal_attempt_at' => 'datetime',
            'next_retry_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'suspended_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Scope to active subscriptions only.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope to billable subscriptions (still considered current for entitlement/billing purposes).
     */
    public function scopeBillable($query)
    {
        return $query->whereIn('status', self::billableStatuses());
    }

    /**
     * Scope to pending subscriptions (awaiting approval).
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to past due subscriptions.
     */
    public function scopePastDue($query)
    {
        return $query->where('status', self::STATUS_PAST_DUE);
    }

    /**
     * Scope to grace period subscriptions.
     */
    public function scopeGrace($query)
    {
        return $query->where('status', self::STATUS_GRACE);
    }

    /**
     * Scope to suspended subscriptions.
     */
    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    /**
     * Scope to expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('status', self::STATUS_EXPIRED);
    }

    /**
     * Scope to cancelled subscriptions.
     */
    public function scopeCancelled($query)
    {
        return $query->where('status', self::STATUS_CANCELLED);
    }

    /**
     * Scope to subscriptions expiring soon (within X days).
     */
    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereNotNull('renewal_at')
            ->whereBetween('renewal_at', [now(), now()->addDays($days)]);
    }

    /**
     * Scope to subscriptions due for renewal.
     */
    public function scopeDueForRenewal($query)
    {
        return $query->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_PAST_DUE])
            ->whereNotNull('renewal_at')
            ->where('renewal_at', '<=', now());
    }

    /**
     * Scope to bank transfer subscriptions awaiting approval.
     */
    public function scopeAwaitingApproval($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where('payment_method', self::PAYMENT_BANK_TRANSFER)
            ->whereNull('approved_at');
    }

    /**
     * Scope by payment method.
     */
    public function scopeByPaymentMethod($query, string $method)
    {
        return $query->where('payment_method', $method);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Check if subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if subscription is in past due status.
     */
    public function isPastDue(): bool
    {
        return $this->status === self::STATUS_PAST_DUE;
    }

    /**
     * Check if subscription is in grace status.
     */
    public function isGrace(): bool
    {
        return $this->status === self::STATUS_GRACE;
    }

    /**
     * Check if subscription is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if subscription is still billable/current.
     */
    public function isBillable(): bool
    {
        return in_array($this->status, self::billableStatuses(), true);
    }

    /**
     * Check if subscription is pending approval.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if subscription requires admin approval.
     */
    public function requiresApproval(): bool
    {
        return $this->payment_method === self::PAYMENT_BANK_TRANSFER
            && $this->status === self::STATUS_PENDING
            && $this->approved_at === null;
    }

    /**
     * Check if subscription is expired.
     */
    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->ends_at && $this->ends_at->isPast());
    }

    /**
     * Check if subscription is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if this is a PayPal subscription.
     */
    public function isPayPal(): bool
    {
        return $this->payment_method === self::PAYMENT_PAYPAL;
    }

    /**
     * Check if this is a bank transfer subscription.
     */
    public function isBankTransfer(): bool
    {
        return $this->payment_method === self::PAYMENT_BANK_TRANSFER;
    }

    /**
     * Get days until renewal.
     */
    public function getDaysUntilRenewalAttribute(): ?int
    {
        if (! $this->renewal_at || ! $this->isActive()) {
            return null;
        }

        return max(0, (int) now()->diffInDays($this->renewal_at, false));
    }

    /**
     * Get human-readable status.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PENDING => 'Pending Approval',
            self::STATUS_PAST_DUE => 'Past Due',
            self::STATUS_GRACE => 'Grace Period',
            self::STATUS_SUSPENDED => 'Suspended',
            self::STATUS_EXPIRED => 'Expired',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get human-readable payment method.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_PAYPAL => 'PayPal',
            self::PAYMENT_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_MANUAL => 'Manual',
            default => ucfirst($this->payment_method ?? 'Unknown'),
        };
    }

    /**
     * Approve the subscription (for bank transfers).
     */
    public function approve(User $admin, ?string $notes = null): bool
    {
        if (! $this->requiresApproval()) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACTIVE,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'admin_notes' => $notes,
            'starts_at' => now(),
            'renewal_at' => $this->calculateNextRenewal(),
            'renewal_retry_count' => 0,
            'next_retry_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'last_renewal_error' => null,
        ]);

        // Update user's plan
        $this->user->update(['plan_id' => $this->plan_id]);

        return true;
    }

    /**
     * Cancel the subscription.
     */
    public function cancel(?int $cancelledById = null, bool $immediate = false, ?string $reason = null): bool
    {
        $metadata = $this->metadata ?? [];
        $metadata['cancellation_reason'] = $reason;
        $metadata['cancelled_by'] = $cancelledById;

        $updates = [
            'status' => self::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'next_retry_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'metadata' => $metadata,
        ];

        if ($immediate) {
            $updates['ends_at'] = now();
        }

        $this->update($updates);

        return true;
    }

    /**
     * Expire the subscription.
     */
    public function expire(): bool
    {
        $this->update([
            'status' => self::STATUS_EXPIRED,
            'next_retry_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'ends_at' => $this->ends_at ?? now(),
        ]);

        return true;
    }

    /**
     * Extend the subscription by a number of days.
     */
    public function extend(int $days): bool
    {
        $currentRenewal = $this->renewal_at ?? now();

        $this->update([
            'renewal_at' => $currentRenewal->addDays($days),
            'status' => self::STATUS_ACTIVE,
            'renewal_retry_count' => 0,
            'next_retry_at' => null,
            'grace_ends_at' => null,
            'suspended_at' => null,
            'last_renewal_error' => null,
        ]);

        return true;
    }

    /**
     * Calculate next renewal date based on plan billing period.
     */
    public function calculateNextRenewal(): \Carbon\Carbon
    {
        $billingPeriod = $this->plan->billing_period ?? 'monthly';

        return match ($billingPeriod) {
            'yearly' => now()->addYear(),
            'lifetime' => now()->addYears(100), // Effectively never
            default => now()->addMonth(),
        };
    }

    /**
     * Get all available statuses.
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PENDING,
            self::STATUS_PAST_DUE,
            self::STATUS_GRACE,
            self::STATUS_SUSPENDED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ];
    }

    /**
     * Statuses that still represent an ongoing (current) subscription lifecycle.
     */
    public static function billableStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_PAST_DUE,
            self::STATUS_GRACE,
        ];
    }

    /**
     * Get all available payment methods.
     */
    public static function getPaymentMethods(): array
    {
        return [
            self::PAYMENT_PAYPAL,
            self::PAYMENT_BANK_TRANSFER,
            self::PAYMENT_MANUAL,
        ];
    }
}
