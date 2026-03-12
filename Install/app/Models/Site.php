<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Site extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'project_id',
        'name',
        'primary_domain',
        'subdomain',
        'status',
        'locale',
        'theme_settings',
    ];

    protected function casts(): array
    {
        return [
            'theme_settings' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    public function forms(): HasMany
    {
        return $this->hasMany(SiteForm::class);
    }

    public function formLeads(): HasMany
    {
        return $this->hasMany(SiteFormLead::class);
    }

    public function notificationTemplates(): HasMany
    {
        return $this->hasMany(SiteNotificationTemplate::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(SiteNotificationLog::class);
    }

    public function customFonts(): HasMany
    {
        return $this->hasMany(SiteCustomFont::class);
    }

    public function globalSettings(): HasOne
    {
        return $this->hasOne(GlobalSetting::class);
    }

    public function ecommerceCategories(): HasMany
    {
        return $this->hasMany(EcommerceCategory::class);
    }

    public function ecommerceProducts(): HasMany
    {
        return $this->hasMany(EcommerceProduct::class);
    }

    public function ecommerceOrders(): HasMany
    {
        return $this->hasMany(EcommerceOrder::class);
    }

    public function ecommerceCarts(): HasMany
    {
        return $this->hasMany(EcommerceCart::class);
    }

    public function ecommerceShipments(): HasMany
    {
        return $this->hasMany(EcommerceShipment::class);
    }

    public function ecommerceInventoryItems(): HasMany
    {
        return $this->hasMany(EcommerceInventoryItem::class);
    }

    public function ecommerceInventoryLocations(): HasMany
    {
        return $this->hasMany(EcommerceInventoryLocation::class);
    }

    public function ecommerceInventoryReservations(): HasMany
    {
        return $this->hasMany(EcommerceInventoryReservation::class);
    }

    public function ecommerceStockMovements(): HasMany
    {
        return $this->hasMany(EcommerceStockMovement::class);
    }

    public function ecommerceAccountingEntries(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntry::class);
    }

    public function ecommerceAccountingEntryLines(): HasMany
    {
        return $this->hasMany(EcommerceAccountingEntryLine::class);
    }

    public function ecommerceRsExports(): HasMany
    {
        return $this->hasMany(EcommerceRsExport::class);
    }

    public function ecommerceRsSyncs(): HasMany
    {
        return $this->hasMany(EcommerceRsSync::class);
    }

    public function ecommerceRsSyncAttempts(): HasMany
    {
        return $this->hasMany(EcommerceRsSyncAttempt::class);
    }

    public function bookingServices(): HasMany
    {
        return $this->hasMany(BookingService::class);
    }

    public function bookingStaffResources(): HasMany
    {
        return $this->hasMany(BookingStaffResource::class);
    }

    public function bookingStaffRoles(): HasMany
    {
        return $this->hasMany(BookingStaffRole::class);
    }

    public function bookingRoleAssignments(): HasMany
    {
        return $this->hasMany(BookingStaffRoleAssignment::class);
    }

    public function bookingAvailabilityRules(): HasMany
    {
        return $this->hasMany(BookingAvailabilityRule::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function bookingInvoices(): HasMany
    {
        return $this->hasMany(BookingInvoice::class);
    }

    public function bookingPayments(): HasMany
    {
        return $this->hasMany(BookingPayment::class);
    }

    public function bookingRefunds(): HasMany
    {
        return $this->hasMany(BookingRefund::class);
    }

    public function bookingFinancialEntries(): HasMany
    {
        return $this->hasMany(BookingFinancialEntry::class);
    }

    public function bookingFinancialEntryLines(): HasMany
    {
        return $this->hasMany(BookingFinancialEntryLine::class);
    }

    public function bookingAssignments(): HasMany
    {
        return $this->hasMany(BookingAssignment::class);
    }

    public function bookingEvents(): HasMany
    {
        return $this->hasMany(BookingEvent::class);
    }

    public function bookingStaffTimeOff(): HasMany
    {
        return $this->hasMany(BookingStaffTimeOff::class);
    }

    public function bookingStaffWorkSchedules(): HasMany
    {
        return $this->hasMany(BookingStaffWorkSchedule::class);
    }

    public function paymentGatewaySettings(): HasMany
    {
        return $this->hasMany(SitePaymentGatewaySetting::class);
    }

    public function courierSettings(): HasMany
    {
        return $this->hasMany(SiteCourierSetting::class);
    }
}
