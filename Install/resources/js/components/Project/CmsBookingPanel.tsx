import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import axios, { AxiosError } from 'axios';
import { toast } from 'sonner';
import { CalendarDays, CheckCircle2, ChevronLeft, ChevronRight, Clock3, Loader2, Menu, Pencil, Plus, RefreshCw, Save, Trash2, UserRound, Wrench } from 'lucide-react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import type { DatesSetArg, EventClickArg, EventInput } from '@fullcalendar/core';
import { useTranslation } from '@/contexts/LanguageContext';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { RichTextField } from '@/components/ui/rich-text-field';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
/* Booking calendar styles: design-system/components.css (.webu-booking-calendar-*) */

interface CmsBookingPanelProps {
    siteId: string;
    activeSection?: BookingTab;
    hideTabs?: boolean;
}

interface ApiErrorPayload {
    message?: string;
    error?: string;
    errors?: Record<string, string[]>;
}

interface BookingServiceItem {
    id: number;
    site_id: string;
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    description: string | null;
    duration_minutes: number;
    buffer_before_minutes: number;
    buffer_after_minutes: number;
    max_parallel_bookings: number;
    requires_staff: boolean;
    allow_online_payment: boolean;
    price: string;
    currency: string;
}

interface BookingStaffItem {
    id: number;
    site_id: string;
    name: string;
    slug: string;
    type: 'staff' | 'resource';
    status: 'active' | 'inactive';
    email: string | null;
    phone: string | null;
    timezone: string;
    max_parallel_bookings: number;
    buffer_minutes: number;
}

interface BookingSummaryItem {
    id: number;
    booking_number: string;
    status: 'pending' | 'confirmed' | 'in_progress' | 'completed' | 'cancelled' | 'no_show';
    customer_name: string | null;
    customer_email: string | null;
    customer_phone: string | null;
    starts_at: string | null;
    ends_at: string | null;
    duration_minutes: number;
    service: {
        id: number | null;
        name: string | null;
    };
    staff_resource: {
        id: number | null;
        name: string | null;
    };
}

interface CalendarEventItem {
    id: number;
    booking_number: string;
    status: BookingSummaryItem['status'];
    starts_at: string | null;
    ends_at: string | null;
    customer_name: string | null;
    service: {
        id: number | null;
        name: string | null;
    };
    staff_resource: {
        id: number | null;
        name: string | null;
    };
}

interface StaffScheduleItem {
    id: number;
    site_id: string;
    staff_resource_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    is_available: boolean;
    timezone: string;
    effective_from: string | null;
    effective_to: string | null;
}

interface StaffTimeOffItem {
    id: number;
    site_id: string;
    staff_resource_id: number;
    starts_at: string | null;
    ends_at: string | null;
    status: 'pending' | 'approved' | 'rejected' | 'cancelled';
    reason: string | null;
}

interface ServiceListResponse {
    site_id: string;
    services: BookingServiceItem[];
}

interface ServiceMutationResponse {
    message: string;
    service: BookingServiceItem;
}

interface StaffListResponse {
    site_id: string;
    staff: BookingStaffItem[];
}

interface StaffMutationResponse {
    message: string;
    staff_resource: BookingStaffItem;
}

interface BookingListResponse {
    site_id: string;
    bookings: BookingSummaryItem[];
    inbox_counts: Record<string, number>;
}

interface BookingMutationResponse {
    message: string;
    booking: BookingSummaryItem;
}

interface BookingDetailsItem extends BookingSummaryItem {
    collision_starts_at: string | null;
    collision_ends_at: string | null;
    buffer_before_minutes: number;
    buffer_after_minutes: number;
    customer_notes: string | null;
    internal_notes: string | null;
    meta_json: Record<string, unknown>;
    confirmed_at: string | null;
    cancelled_at: string | null;
    completed_at: string | null;
    assignments: Array<{
        id: number;
        staff_resource_id: number | null;
        assignment_type: string | null;
        status: string | null;
        starts_at: string | null;
        ends_at: string | null;
        meta_json: Record<string, unknown>;
        created_at: string | null;
    }>;
    events: Array<{
        id: number;
        event_type: string;
        event_key: string | null;
        payload_json: Record<string, unknown>;
        occurred_at: string | null;
        created_at: string | null;
    }>;
}

interface BookingShowResponse {
    site_id: string;
    booking: BookingDetailsItem;
}

interface CalendarResponse {
    site_id: string;
    from: string;
    to: string;
    events: CalendarEventItem[];
    staff_schedule_blocks?: Array<{
        id: string;
        type: 'work_schedule';
        day_of_week: number;
        is_available: boolean;
        starts_at: string | null;
        ends_at: string | null;
        timezone: string;
        staff_resource: {
            id: number | null;
            name: string | null;
            type: string | null;
        };
    }>;
    time_off_blocks?: Array<{
        id: number;
        type: 'time_off';
        status: string;
        starts_at: string | null;
        ends_at: string | null;
        reason: string | null;
        staff_resource: {
            id: number | null;
            name: string | null;
            type: string | null;
        };
    }>;
}

interface ServiceFormState {
    name: string;
    slug: string;
    status: 'active' | 'inactive';
    duration_minutes: string;
    max_parallel_bookings: string;
    requires_staff: 'true' | 'false';
    price: string;
    currency: string;
    description: string;
}

interface StaffFormState {
    name: string;
    slug: string;
    type: 'staff' | 'resource';
    status: 'active' | 'inactive';
    email: string;
    phone: string;
    timezone: string;
    max_parallel_bookings: string;
}

interface BookingFormState {
    service_id: string;
    staff_resource_id: string;
    starts_at: string;
    duration_minutes: string;
    customer_name: string;
    customer_email: string;
    customer_phone: string;
    customer_notes: string;
}

interface BookingCustomerSearchUser {
    id: number;
    name: string;
    email: string;
}

interface BookingCustomerSearchResponse {
    site_id: string;
    users: BookingCustomerSearchUser[];
}

interface BookingFinanceDimensionRow {
    key: string;
    label: string;
    bookings_count: number;
    revenue_total: string;
    paid_total: string;
    outstanding_total: string;
    refunds_total: string;
    net_collected_total: string;
}

interface BookingFinanceReportResponse {
    site_id: string;
    filters: {
        date_from: string | null;
        date_to: string | null;
        service_id: number | null;
        staff_resource_id: number | null;
        source: string | null;
        top: number;
    };
    summary: {
        bookings_count: number;
        revenue_total: string;
        paid_total: string;
        outstanding_total: string;
        discount_total: string;
        tax_total: string;
        settled_payments_total: string;
        refunds_total: string;
        net_collected_total: string;
        average_booking_value: string;
    };
    groups: {
        services: BookingFinanceDimensionRow[];
        staff: BookingFinanceDimensionRow[];
        channels: BookingFinanceDimensionRow[];
    };
}

interface BookingFinanceReconciliationResponse {
    site_id: string;
    filters: {
        date_from: string | null;
        date_to: string | null;
        service_id: number | null;
        staff_resource_id: number | null;
        source: string | null;
        top?: number;
    };
    summary: {
        entries_count: number;
        total_debit: string;
        total_credit: string;
        difference: string;
        is_balanced: boolean;
        accounts_receivable_net: string;
        bookings_outstanding_total: string;
        invoices_outstanding_total: string;
        outstanding_gap: string;
        settled_payments_total: string;
        settled_refunds_total: string;
        net_collected_total: string;
        uninvoiced_bookings_count: number;
        uninvoiced_revenue_total: string;
    };
    accounts: Array<{
        account_code: string;
        account_name: string;
        debit_total: string;
        credit_total: string;
        net: string;
    }>;
}

type BookingTab = 'inbox' | 'calendar' | 'services' | 'team' | 'finance';

function getApiErrorMessage(error: unknown, fallback: string): string {
    const axiosError = error as AxiosError<ApiErrorPayload>;
    const payload = axiosError.response?.data;

    if (payload?.error) {
        return payload.error;
    }

    if (payload?.message) {
        return payload.message;
    }

    const firstValidationError = payload?.errors
        ? Object.values(payload.errors).flat()[0]
        : null;

    return firstValidationError ?? fallback;
}

function toInputDateTime(value: string | null): string {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function formatDateTime(value: string | null, locale?: string): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString(locale);
}

function statusVariant(status: BookingSummaryItem['status']): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'completed') {
        return 'default';
    }

    if (status === 'cancelled' || status === 'no_show') {
        return 'destructive';
    }

    if (status === 'confirmed' || status === 'in_progress') {
        return 'secondary';
    }

    return 'outline';
}

const GEORGIAN_TO_LATIN_MAP: Record<string, string> = {
    ა: 'a', ბ: 'b', გ: 'g', დ: 'd', ე: 'e', ვ: 'v', ზ: 'z',
    თ: 'th', ი: 'i', კ: 'k', ლ: 'l', მ: 'm', ნ: 'n', ო: 'o',
    პ: 'p', ჟ: 'zh', რ: 'r', ს: 's', ტ: 't', უ: 'u', ფ: 'f',
    ქ: 'q', ღ: 'gh', ყ: 'y', შ: 'sh', ჩ: 'ch', ც: 'ts', ძ: 'dz',
    წ: 'ts', ჭ: 'tch', ხ: 'kh', ჯ: 'j', ჰ: 'h',
};

function slugify(value: string): string {
    const transliterated = value
        .toLowerCase()
        .split('')
        .map((char) => GEORGIAN_TO_LATIN_MAP[char] ?? char)
        .join('')
        .normalize('NFKD')
        .replace(/[\u0300-\u036f]/g, '');

    return transliterated
        .trim()
        .replace(/[^a-z0-9\s-]/g, '')
        .replace(/\s+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '');
}

const DAY_OF_WEEK_OPTIONS = [
    { value: 0, label: 'Sunday' },
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
] as const;

const BOOKING_CALENDAR_STATUS_OPTIONS: Array<BookingSummaryItem['status']> = [
    'pending',
    'confirmed',
    'in_progress',
    'completed',
    'cancelled',
    'no_show',
];

function bookingCalendarEventColorClass(status: BookingSummaryItem['status']): string {
    if (status === 'completed') return 'event-bg-success';
    if (status === 'confirmed') return 'event-bg-primary';
    if (status === 'in_progress') return 'event-bg-info';
    if (status === 'cancelled' || status === 'no_show') return 'event-bg-error';
    return 'event-bg-warning';
}

function parseLocalDateInput(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map((part) => Number.parseInt(part, 10));
    if (!year || !month || !day) {
        return null;
    }

    const date = new Date(year, month - 1, day);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date;
}

function toLocalDateInput(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function startOfMonthDate(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function endOfMonthDate(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

function addDaysDate(date: Date, amount: number): Date {
    const next = new Date(date);
    next.setDate(next.getDate() + amount);
    return next;
}

function addMonthsDate(date: Date, amount: number): Date {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1);
}

function startOfWeekSunday(date: Date): Date {
    return addDaysDate(date, -date.getDay());
}

function endOfWeekSaturday(date: Date): Date {
    return addDaysDate(date, 6 - date.getDay());
}

function isSameDayDate(a: Date | null, b: Date | null): boolean {
    if (!a || !b) {
        return false;
    }

    return a.getFullYear() === b.getFullYear()
        && a.getMonth() === b.getMonth()
        && a.getDate() === b.getDate();
}

interface ScheduleFormRow {
    day_of_week: number;
    is_available: boolean;
    start_time: string;
    end_time: string;
}

function defaultScheduleRows(): ScheduleFormRow[] {
    return DAY_OF_WEEK_OPTIONS.map((entry) => ({
        day_of_week: entry.value,
        is_available: entry.value >= 1 && entry.value <= 5,
        start_time: '09:00',
        end_time: '18:00',
    }));
}

function defaultBookingFormState(): BookingFormState {
    return {
        service_id: '',
        staff_resource_id: '',
        starts_at: '',
        duration_minutes: '60',
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        customer_notes: '',
    };
}

export function CmsBookingPanel({ siteId, activeSection, hideTabs = false }: CmsBookingPanelProps) {
    const { t, locale } = useTranslation();

    const [activeTab, setActiveTab] = useState<BookingTab>(activeSection ?? 'inbox');
    const bookingCalendarRef = useRef<FullCalendar | null>(null);
    const previousSiteIdRef = useRef<string | null>(null);
    const servicesLoadedForSiteRef = useRef<string | null>(null);
    const staffLoadedForSiteRef = useRef<string | null>(null);
    const bookingsLoadedKeyRef = useRef<string | null>(null);
    const calendarLoadedKeyRef = useRef<string | null>(null);

    const [services, setServices] = useState<BookingServiceItem[]>([]);
    const [staff, setStaff] = useState<BookingStaffItem[]>([]);
    const [bookings, setBookings] = useState<BookingSummaryItem[]>([]);
    const [inboxCounts, setInboxCounts] = useState<Record<string, number>>({});
    const [calendarEvents, setCalendarEvents] = useState<CalendarEventItem[]>([]);
    const [calendarTimeOffBlocks, setCalendarTimeOffBlocks] = useState<CalendarResponse['time_off_blocks']>([]);
    const [selectedCalendarEventId, setSelectedCalendarEventId] = useState<number | null>(null);
    const [isCalendarDetailsSheetOpen, setIsCalendarDetailsSheetOpen] = useState(false);
    const [selectedInboxBookingId, setSelectedInboxBookingId] = useState<number | null>(null);
    const [isInboxBookingDetailsSheetOpen, setIsInboxBookingDetailsSheetOpen] = useState(false);
    const [selectedInboxBookingDetails, setSelectedInboxBookingDetails] = useState<BookingDetailsItem | null>(null);
    const [isLoadingInboxBookingDetails, setIsLoadingInboxBookingDetails] = useState(false);
    const [inboxBookingDetailsError, setInboxBookingDetailsError] = useState<string | null>(null);
    const [calendarServiceFilter, setCalendarServiceFilter] = useState<string>('all');
    const [calendarStaffFilter, setCalendarStaffFilter] = useState<string>('all');
    const [calendarStatusFilters, setCalendarStatusFilters] = useState<BookingSummaryItem['status'][]>(BOOKING_CALENDAR_STATUS_OPTIONS);
    const [isCalendarSidebarOpen, setIsCalendarSidebarOpen] = useState(true);

    const [isLoadingServices, setIsLoadingServices] = useState(false);
    const [isLoadingStaff, setIsLoadingStaff] = useState(false);
    const [isLoadingBookings, setIsLoadingBookings] = useState(false);
    const [isLoadingCalendar, setIsLoadingCalendar] = useState(false);
    const [isLoadingSchedules, setIsLoadingSchedules] = useState(false);
    const [isLoadingTimeOff, setIsLoadingTimeOff] = useState(false);
    const [isLoadingFinance, setIsLoadingFinance] = useState(false);

    const [isSubmittingService, setIsSubmittingService] = useState(false);
    const [isSubmittingStaff, setIsSubmittingStaff] = useState(false);
    const [isSubmittingBooking, setIsSubmittingBooking] = useState(false);
    const [updatingBookingId, setUpdatingBookingId] = useState<number | null>(null);
    const [isSavingSchedules, setIsSavingSchedules] = useState(false);
    const [isSavingTimeOff, setIsSavingTimeOff] = useState(false);

    const [servicesError, setServicesError] = useState<string | null>(null);
    const [staffError, setStaffError] = useState<string | null>(null);
    const [bookingsError, setBookingsError] = useState<string | null>(null);
    const [calendarError, setCalendarError] = useState<string | null>(null);
    const [financeError, setFinanceError] = useState<string | null>(null);
    const [schedulesError, setSchedulesError] = useState<string | null>(null);
    const [timeOffError, setTimeOffError] = useState<string | null>(null);

    const [editingServiceId, setEditingServiceId] = useState<number | null>(null);
    const [editingStaffId, setEditingStaffId] = useState<number | null>(null);
    const [isServiceSheetOpen, setIsServiceSheetOpen] = useState(false);
    const [isStaffSheetOpen, setIsStaffSheetOpen] = useState(false);

    const [calendarFrom, setCalendarFrom] = useState<string>(() => {
        const date = new Date();
        date.setDate(1);
        return date.toISOString().slice(0, 10);
    });
    const [calendarTo, setCalendarTo] = useState<string>(() => {
        const date = new Date();
        date.setMonth(date.getMonth() + 1, 0);
        return date.toISOString().slice(0, 10);
    });
    const [calendarMiniMonthCursor, setCalendarMiniMonthCursor] = useState<Date>(() => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1);
    });
    const [financeDateFrom, setFinanceDateFrom] = useState<string>(() => {
        const date = new Date();
        date.setDate(1);
        return date.toISOString().slice(0, 10);
    });
    const [financeDateTo, setFinanceDateTo] = useState<string>(() => {
        const date = new Date();
        date.setMonth(date.getMonth() + 1, 0);
        return date.toISOString().slice(0, 10);
    });
    const [financeSourceFilter, setFinanceSourceFilter] = useState<string>('all');
    const [financeServiceFilter, setFinanceServiceFilter] = useState<string>('all');
    const [financeStaffFilter, setFinanceStaffFilter] = useState<string>('all');
    const [financeTop, setFinanceTop] = useState<string>('10');
    const [financeReport, setFinanceReport] = useState<BookingFinanceReportResponse | null>(null);
    const [financeReconciliation, setFinanceReconciliation] = useState<BookingFinanceReconciliationResponse | null>(null);

    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | BookingSummaryItem['status']>('all');
    const [rescheduleDrafts, setRescheduleDrafts] = useState<Record<number, string>>({});
    const [selectedStaffForSchedule, setSelectedStaffForSchedule] = useState<string>('');
    const [, setStaffSchedules] = useState<StaffScheduleItem[]>([]);
    const [scheduleRows, setScheduleRows] = useState<ScheduleFormRow[]>(defaultScheduleRows);
    const [staffTimeOffEntries, setStaffTimeOffEntries] = useState<StaffTimeOffItem[]>([]);
    const [timeOffForm, setTimeOffForm] = useState({
        starts_at: '',
        ends_at: '',
        status: 'approved' as StaffTimeOffItem['status'],
        reason: '',
    });

    const [serviceForm, setServiceForm] = useState<ServiceFormState>({
        name: '',
        slug: '',
        status: 'active',
        duration_minutes: '60',
        max_parallel_bookings: '1',
        requires_staff: 'true',
        price: '0',
        currency: 'GEL',
        description: '',
    });

    const [staffForm, setStaffForm] = useState<StaffFormState>({
        name: '',
        slug: '',
        type: 'staff',
        status: 'active',
        email: '',
        phone: '',
        timezone: 'Asia/Tbilisi',
        max_parallel_bookings: '1',
    });

    const [bookingForm, setBookingForm] = useState<BookingFormState>(defaultBookingFormState());
    const [isBookingDrawerOpen, setIsBookingDrawerOpen] = useState(false);
    const [bookingDrawerMode, setBookingDrawerMode] = useState<'create' | 'edit'>('create');
    const [bookingDrawerBookingId, setBookingDrawerBookingId] = useState<number | null>(null);
    const [bookingDrawerStatus, setBookingDrawerStatus] = useState<BookingSummaryItem['status']>('pending');
    const [bookingDrawerDetails, setBookingDrawerDetails] = useState<BookingDetailsItem | null>(null);
    const [bookingDrawerError, setBookingDrawerError] = useState<string | null>(null);
    const [isLoadingBookingDrawer, setIsLoadingBookingDrawer] = useState(false);
    const [bookingCustomerSearchInput, setBookingCustomerSearchInput] = useState('');
    const [bookingCustomerSearchResults, setBookingCustomerSearchResults] = useState<BookingCustomerSearchUser[]>([]);
    const [isLoadingBookingCustomerSearch, setIsLoadingBookingCustomerSearch] = useState(false);
    const [bookingCustomerSearchError, setBookingCustomerSearchError] = useState<string | null>(null);
    const [selectedBookingCustomerUser, setSelectedBookingCustomerUser] = useState<BookingCustomerSearchUser | null>(null);

    const areAllCalendarStatusFiltersSelected = calendarStatusFilters.length === BOOKING_CALENDAR_STATUS_OPTIONS.length;
    const filteredCalendarEvents = useMemo(() => {
        return calendarEvents.filter((event) => {
            if (calendarServiceFilter !== 'all' && String(event.service.id ?? '') !== calendarServiceFilter) {
                return false;
            }
            if (calendarStaffFilter !== 'all' && String(event.staff_resource.id ?? '') !== calendarStaffFilter) {
                return false;
            }
            if (!calendarStatusFilters.includes(event.status)) {
                return false;
            }
            return true;
        });
    }, [calendarEvents, calendarServiceFilter, calendarStaffFilter, calendarStatusFilters]);
    const selectedCalendarEvent = useMemo(() => (
        filteredCalendarEvents.find((event) => event.id === selectedCalendarEventId)
        ?? calendarEvents.find((event) => event.id === selectedCalendarEventId)
        ?? null
    ), [calendarEvents, filteredCalendarEvents, selectedCalendarEventId]);
    const selectedInboxBookingSummary = useMemo(() => (
        selectedInboxBookingId === null
            ? null
            : bookings.find((booking) => booking.id === selectedInboxBookingId) ?? null
    ), [bookings, selectedInboxBookingId]);
    const selectedInboxBookingDisplay = useMemo(
        () => selectedInboxBookingDetails ?? selectedInboxBookingSummary,
        [selectedInboxBookingDetails, selectedInboxBookingSummary]
    );
    const selectedStaffScheduleResource = useMemo(() => (
        staff.find((resource) => String(resource.id) === selectedStaffForSchedule) ?? null
    ), [staff, selectedStaffForSchedule]);
    const workScheduleSummary = useMemo(() => {
        const activeRows = scheduleRows.filter((row) => row.is_available);
        const totalMinutes = activeRows.reduce((sum, row) => {
            const [startHour = 0, startMinute = 0] = row.start_time.split(':').map((value) => Number.parseInt(value, 10));
            const [endHour = 0, endMinute = 0] = row.end_time.split(':').map((value) => Number.parseInt(value, 10));
            const start = (Number.isFinite(startHour) ? startHour : 0) * 60 + (Number.isFinite(startMinute) ? startMinute : 0);
            const end = (Number.isFinite(endHour) ? endHour : 0) * 60 + (Number.isFinite(endMinute) ? endMinute : 0);

            return sum + Math.max(0, end - start);
        }, 0);

        return {
            activeDays: activeRows.length,
            inactiveDays: scheduleRows.length - activeRows.length,
            totalHours: Number((totalMinutes / 60).toFixed(1)),
        };
    }, [scheduleRows]);
    const serviceCatalogSummary = useMemo(() => {
        const activeServices = services.filter((service) => service.status === 'active');
        const totalDuration = services.reduce((sum, service) => sum + (Number.isFinite(service.duration_minutes) ? service.duration_minutes : 0), 0);

        return {
            total: services.length,
            active: activeServices.length,
            requiresStaff: services.filter((service) => service.requires_staff).length,
            avgDuration: services.length > 0 ? Math.round(totalDuration / services.length) : 0,
        };
    }, [services]);
    const calendarEventCountsByStatus = useMemo(() => {
        const counts: Record<BookingSummaryItem['status'], number> = {
            pending: 0,
            confirmed: 0,
            in_progress: 0,
            completed: 0,
            cancelled: 0,
            no_show: 0,
        };

        filteredCalendarEvents.forEach((event) => {
            counts[event.status] += 1;
        });

        return counts;
    }, [filteredCalendarEvents]);
    const calendarEventCountsByDay = useMemo(() => {
        const counts = new Map<string, number>();

        calendarEvents.forEach((event) => {
            if (!event.starts_at) {
                return;
            }

            const key = event.starts_at.slice(0, 10);
            counts.set(key, (counts.get(key) ?? 0) + 1);
        });

        return counts;
    }, [calendarEvents]);
    const parsedCalendarFromDate = useMemo(() => parseLocalDateInput(calendarFrom), [calendarFrom]);
    const parsedCalendarToDate = useMemo(() => parseLocalDateInput(calendarTo), [calendarTo]);
    const miniCalendarMonthLabel = useMemo(() => (
        new Intl.DateTimeFormat(locale || undefined, { month: 'long', year: 'numeric' }).format(calendarMiniMonthCursor)
    ), [calendarMiniMonthCursor, locale]);
    const miniCalendarWeekdayLabels = useMemo(() => {
        const formatter = new Intl.DateTimeFormat(locale || undefined, { weekday: 'short' });
        const sunday = new Date(2024, 0, 7);

        return Array.from({ length: 7 }, (_, index) => formatter.format(addDaysDate(sunday, index)));
    }, [locale]);
    const miniCalendarDays = useMemo(() => {
        const today = new Date();
        const monthStart = startOfMonthDate(calendarMiniMonthCursor);
        const monthEnd = endOfMonthDate(calendarMiniMonthCursor);
        const gridStart = startOfWeekSunday(monthStart);
        const gridEnd = endOfWeekSaturday(monthEnd);
        const days: Array<{
            iso: string;
            date: Date;
            day: number;
            isCurrentMonth: boolean;
            isToday: boolean;
            isInRange: boolean;
            isRangeStart: boolean;
            isRangeEnd: boolean;
            eventCount: number;
        }> = [];

        for (let cursor = new Date(gridStart); cursor <= gridEnd; cursor = addDaysDate(cursor, 1)) {
            const current = new Date(cursor);
            const iso = toLocalDateInput(current);
            const inRange = Boolean(
                parsedCalendarFromDate
                && parsedCalendarToDate
                && current >= parsedCalendarFromDate
                && current <= parsedCalendarToDate
            );

            days.push({
                iso,
                date: current,
                day: current.getDate(),
                isCurrentMonth: current.getMonth() === calendarMiniMonthCursor.getMonth(),
                isToday: isSameDayDate(current, today),
                isInRange: inRange,
                isRangeStart: isSameDayDate(current, parsedCalendarFromDate),
                isRangeEnd: isSameDayDate(current, parsedCalendarToDate),
                eventCount: calendarEventCountsByDay.get(iso) ?? 0,
            });
        }

        return days;
    }, [calendarEventCountsByDay, calendarMiniMonthCursor, parsedCalendarFromDate, parsedCalendarToDate]);
    const fullCalendarEvents = useMemo<EventInput[]>(() => {
        const bookingInputs: EventInput[] = filteredCalendarEvents.map((event) => {
            const staffLabel = event.staff_resource.name ?? t('Unassigned');
            const serviceLabel = event.service.name ?? '—';
            const customerLabel = event.customer_name ?? t('Guest');

            return {
                id: `booking-${event.id}`,
                title: `${serviceLabel} · ${customerLabel}`,
                start: event.starts_at ?? undefined,
                end: event.ends_at ?? undefined,
                classNames: [bookingCalendarEventColorClass(event.status), `booking-status-${event.status}`],
                extendedProps: {
                    kind: 'booking',
                    bookingId: event.id,
                    status: event.status,
                    bookingNumber: event.booking_number,
                    customerName: customerLabel,
                    serviceName: serviceLabel,
                    staffName: staffLabel,
                },
            };
        });

        const timeOffInputs: EventInput[] = (calendarTimeOffBlocks ?? [])
            .filter((block) => block.starts_at && block.ends_at)
            .map((block) => ({
                id: `timeoff-${block.id}`,
                title: `${block.staff_resource.name ?? t('Unassigned')} · ${t('Time Off')}`,
                start: block.starts_at ?? undefined,
                end: block.ends_at ?? undefined,
                display: 'background',
                classNames: ['booking-timeoff-bg'],
                extendedProps: {
                    kind: 'time_off',
                    timeOffId: block.id,
                    status: block.status,
                    reason: block.reason,
                },
            }));

        return [...bookingInputs, ...timeOffInputs];
    }, [calendarTimeOffBlocks, filteredCalendarEvents, t]);
    const financeSourceOptions = useMemo(() => {
        const defaults = ['panel', 'public', 'api', 'admin', 'unknown'];
        const dynamic = financeReport?.groups.channels
            ?.map((row) => row.label)
            .filter((value) => value && value.trim() !== '') ?? [];

        return Array.from(new Set([...defaults, ...dynamic]));
    }, [financeReport]);

    const loadServices = useCallback(async () => {
        setIsLoadingServices(true);
        setServicesError(null);
        try {
            const response = await axios.get<ServiceListResponse>(`/panel/sites/${siteId}/booking/services`);
            setServices(response.data.services ?? []);
            servicesLoadedForSiteRef.current = siteId;
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load booking services'));
            setServicesError(message);
            toast.error(message);
        } finally {
            setIsLoadingServices(false);
        }
    }, [siteId, t]);

    const loadStaff = useCallback(async () => {
        setIsLoadingStaff(true);
        setStaffError(null);
        try {
            const response = await axios.get<StaffListResponse>(`/panel/sites/${siteId}/booking/staff`);
            setStaff(response.data.staff ?? []);
            staffLoadedForSiteRef.current = siteId;
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load booking staff'));
            setStaffError(message);
            toast.error(message);
        } finally {
            setIsLoadingStaff(false);
        }
    }, [siteId, t]);

    const loadStaffSchedules = useCallback(async (staffResourceId: string) => {
        if (!staffResourceId) {
            setStaffSchedules([]);
            setScheduleRows(defaultScheduleRows());
            return;
        }

        setIsLoadingSchedules(true);
        setSchedulesError(null);
        try {
            const response = await axios.get<{
                site_id: string;
                schedules: StaffScheduleItem[];
            }>(`/panel/sites/${siteId}/booking/staff/${staffResourceId}/work-schedules`);

            const rows = response.data.schedules ?? [];
            setStaffSchedules(rows);

            const grouped = defaultScheduleRows().map((row) => {
                const match = rows.find((item) => item.day_of_week === row.day_of_week);
                if (!match) {
                    return row;
                }

                return {
                    day_of_week: row.day_of_week,
                    is_available: Boolean(match.is_available),
                    start_time: match.start_time.slice(0, 5),
                    end_time: match.end_time.slice(0, 5),
                };
            });

            setScheduleRows(grouped);
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load staff schedules'));
            setSchedulesError(message);
            toast.error(message);
        } finally {
            setIsLoadingSchedules(false);
        }
    }, [siteId, t]);

    const loadStaffTimeOff = useCallback(async (staffResourceId: string) => {
        if (!staffResourceId) {
            setStaffTimeOffEntries([]);
            return;
        }

        setIsLoadingTimeOff(true);
        setTimeOffError(null);
        try {
            const response = await axios.get<{
                site_id: string;
                time_off: StaffTimeOffItem[];
            }>(`/panel/sites/${siteId}/booking/staff/${staffResourceId}/time-off`, {
                params: {
                    limit: 100,
                },
            });

            setStaffTimeOffEntries(response.data.time_off ?? []);
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load staff time-off entries'));
            setTimeOffError(message);
            toast.error(message);
        } finally {
            setIsLoadingTimeOff(false);
        }
    }, [siteId, t]);

    const loadBookings = useCallback(async () => {
        const requestKey = `${siteId}:${statusFilter}:${searchTerm}`;
        setIsLoadingBookings(true);
        setBookingsError(null);
        try {
            const response = await axios.get<BookingListResponse>(`/panel/sites/${siteId}/booking/bookings`, {
                params: {
                    status: statusFilter === 'all' ? undefined : statusFilter,
                    search: searchTerm || undefined,
                },
            });

            const rows = response.data.bookings ?? [];
            setBookings(rows);
            setInboxCounts(response.data.inbox_counts ?? {});

            const drafts: Record<number, string> = {};
            rows.forEach((booking) => {
                drafts[booking.id] = toInputDateTime(booking.starts_at);
            });
            setRescheduleDrafts((current) => ({ ...drafts, ...current }));
            bookingsLoadedKeyRef.current = requestKey;
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load bookings'));
            setBookingsError(message);
            if (bookingsLoadedKeyRef.current === requestKey) {
                bookingsLoadedKeyRef.current = null;
            }
            toast.error(message);
        } finally {
            setIsLoadingBookings(false);
        }
    }, [searchTerm, siteId, statusFilter, t]);

    const loadCalendar = useCallback(async () => {
        const requestKey = `${siteId}:${calendarFrom}:${calendarTo}`;
        setIsLoadingCalendar(true);
        setCalendarError(null);
        try {
            const response = await axios.get<CalendarResponse>(`/panel/sites/${siteId}/booking/calendar`, {
                params: {
                    from: calendarFrom,
                    to: calendarTo,
                },
            });
            setCalendarEvents(response.data.events ?? []);
            setCalendarTimeOffBlocks(response.data.time_off_blocks ?? []);
            calendarLoadedKeyRef.current = requestKey;
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load booking calendar'));
            setCalendarError(message);
            if (calendarLoadedKeyRef.current === requestKey) {
                calendarLoadedKeyRef.current = null;
            }
            toast.error(message);
        } finally {
            setIsLoadingCalendar(false);
        }
    }, [calendarFrom, calendarTo, siteId, t]);

    const loadFinanceData = useCallback(async () => {
        setIsLoadingFinance(true);
        setFinanceError(null);
        try {
            const topValue = Math.min(50, Math.max(1, Number(financeTop) || 10));
            const params = {
                date_from: financeDateFrom || undefined,
                date_to: financeDateTo || undefined,
                service_id: financeServiceFilter === 'all' ? undefined : Number(financeServiceFilter),
                staff_resource_id: financeStaffFilter === 'all' ? undefined : Number(financeStaffFilter),
                source: financeSourceFilter === 'all' ? undefined : financeSourceFilter,
                top: topValue,
            };

            const [reportResponse, reconciliationResponse] = await Promise.all([
                axios.get<BookingFinanceReportResponse>(`/panel/sites/${siteId}/booking/finance/reports`, { params }),
                axios.get<BookingFinanceReconciliationResponse>(`/panel/sites/${siteId}/booking/finance/reconciliation`, { params }),
            ]);

            setFinanceReport(reportResponse.data);
            setFinanceReconciliation(reconciliationResponse.data);
        } catch (error) {
            setFinanceReport(null);
            setFinanceReconciliation(null);
            const message = getApiErrorMessage(error, t('Failed to load booking finance analytics'));
            setFinanceError(message);
            toast.error(message);
        } finally {
            setIsLoadingFinance(false);
        }
    }, [financeDateFrom, financeDateTo, financeServiceFilter, financeSourceFilter, financeStaffFilter, financeTop, siteId, t]);

    const handleCalendarDatesSet = useCallback((arg: DatesSetArg) => {
        const nextFrom = arg.start.toISOString().slice(0, 10);
        const inclusiveEnd = new Date(arg.end);
        inclusiveEnd.setDate(inclusiveEnd.getDate() - 1);
        const nextTo = inclusiveEnd.toISOString().slice(0, 10);

        setCalendarFrom((current) => (current === nextFrom ? current : nextFrom));
        setCalendarTo((current) => (current === nextTo ? current : nextTo));
    }, []);

    const handleCalendarEventClick = useCallback((arg: EventClickArg) => {
        const kind = String(arg.event.extendedProps?.kind ?? '');
        if (kind !== 'booking') {
            return;
        }

        const bookingId = Number(arg.event.extendedProps?.bookingId ?? 0);
        if (Number.isInteger(bookingId) && bookingId > 0) {
            setSelectedCalendarEventId(bookingId);
            setIsCalendarDetailsSheetOpen(true);
        }
    }, []);
    const handleMiniCalendarPickDate = useCallback((date: Date) => {
        setCalendarMiniMonthCursor(startOfMonthDate(date));

        const calendarApi = bookingCalendarRef.current?.getApi?.();
        if (calendarApi) {
            calendarApi.gotoDate(date);
            return;
        }

        setCalendarFrom(toLocalDateInput(startOfMonthDate(date)));
        setCalendarTo(toLocalDateInput(endOfMonthDate(date)));
    }, []);

    const handleMiniCalendarPrevMonth = useCallback(() => {
        setCalendarMiniMonthCursor((current) => addMonthsDate(current, -1));
    }, []);

    const handleMiniCalendarNextMonth = useCallback(() => {
        setCalendarMiniMonthCursor((current) => addMonthsDate(current, 1));
    }, []);

    useEffect(() => {
        if (!['inbox', 'services', 'finance', 'calendar'].includes(activeTab)) {
            return;
        }

        if (servicesLoadedForSiteRef.current === siteId || isLoadingServices) {
            return;
        }

        void loadServices();
    }, [activeTab, isLoadingServices, loadServices, siteId]);

    useEffect(() => {
        if (!['inbox', 'team', 'finance', 'calendar'].includes(activeTab)) {
            return;
        }

        if (staffLoadedForSiteRef.current === siteId || isLoadingStaff) {
            return;
        }

        void loadStaff();
    }, [activeTab, isLoadingStaff, loadStaff, siteId]);

    useEffect(() => {
        if (activeTab !== 'inbox') {
            return;
        }

        const key = `${siteId}:${statusFilter}:${searchTerm}`;
        if (bookingsLoadedKeyRef.current === key || isLoadingBookings) {
            return;
        }

        void loadBookings();
    }, [activeTab, isLoadingBookings, loadBookings, searchTerm, siteId, statusFilter]);

    useEffect(() => {
        if (!['calendar', 'team'].includes(activeTab)) {
            return;
        }

        const key = `${siteId}:${calendarFrom}:${calendarTo}`;
        if (calendarLoadedKeyRef.current === key || isLoadingCalendar) {
            return;
        }

        void loadCalendar();
    }, [activeTab, calendarFrom, calendarTo, isLoadingCalendar, loadCalendar, siteId]);

    useEffect(() => {
        const parsed = parseLocalDateInput(calendarFrom);
        if (!parsed) {
            return;
        }

        setCalendarMiniMonthCursor((current) => (
            current.getFullYear() === parsed.getFullYear() && current.getMonth() === parsed.getMonth()
                ? current
                : new Date(parsed.getFullYear(), parsed.getMonth(), 1)
        ));
    }, [calendarFrom]);

    useEffect(() => {
        if (activeTab !== 'finance') {
            return;
        }

        void loadFinanceData();
    }, [activeTab, loadFinanceData]);

    useEffect(() => {
        if (!activeSection || activeSection === activeTab) {
            return;
        }

        setActiveTab(activeSection);
    }, [activeSection, activeTab]);

    useEffect(() => {
        if (selectedCalendarEventId !== null && !calendarEvents.some((event) => event.id === selectedCalendarEventId)) {
            setSelectedCalendarEventId(null);
        }
    }, [calendarEvents, selectedCalendarEventId]);

    useEffect(() => {
        if (selectedCalendarEventId !== null && !filteredCalendarEvents.some((event) => event.id === selectedCalendarEventId)) {
            setSelectedCalendarEventId(null);
        }
    }, [filteredCalendarEvents, selectedCalendarEventId]);

    useEffect(() => {
        if (selectedCalendarEventId === null && filteredCalendarEvents.length > 0) {
            setSelectedCalendarEventId(filteredCalendarEvents[0]?.id ?? null);
        }
    }, [filteredCalendarEvents, selectedCalendarEventId]);

    useEffect(() => {
        if (!isCalendarDetailsSheetOpen) {
            return;
        }

        if (selectedCalendarEvent === null) {
            setIsCalendarDetailsSheetOpen(false);
        }
    }, [isCalendarDetailsSheetOpen, selectedCalendarEvent]);

    useEffect(() => {
        if (!isBookingDrawerOpen) {
            setBookingCustomerSearchResults([]);
            setIsLoadingBookingCustomerSearch(false);
            setBookingCustomerSearchError(null);
            return;
        }

        const query = bookingCustomerSearchInput.trim();
        if (query.length < 2) {
            setBookingCustomerSearchResults([]);
            setIsLoadingBookingCustomerSearch(false);
            setBookingCustomerSearchError(null);
            return;
        }

        let cancelled = false;
        setIsLoadingBookingCustomerSearch(true);
        setBookingCustomerSearchError(null);

        const timer = window.setTimeout(async () => {
            try {
                const response = await axios.get<BookingCustomerSearchResponse>(`/panel/sites/${siteId}/booking/customers/search`, {
                    params: { search: query },
                });

                if (cancelled) {
                    return;
                }

                setBookingCustomerSearchResults(response.data.users ?? []);
            } catch (error) {
                if (cancelled) {
                    return;
                }

                const message = getApiErrorMessage(error, t('Failed to search customers'));
                setBookingCustomerSearchError(message);
                setBookingCustomerSearchResults([]);
            } finally {
                if (!cancelled) {
                    setIsLoadingBookingCustomerSearch(false);
                }
            }
        }, 280);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [bookingCustomerSearchInput, isBookingDrawerOpen, siteId, t]);

    useEffect(() => {
        if (previousSiteIdRef.current === null) {
            previousSiteIdRef.current = siteId;
            return;
        }

        if (previousSiteIdRef.current === siteId) {
            return;
        }

        previousSiteIdRef.current = siteId;
        servicesLoadedForSiteRef.current = null;
        staffLoadedForSiteRef.current = null;
        bookingsLoadedKeyRef.current = null;
        calendarLoadedKeyRef.current = null;
        setServices([]);
        setStaff([]);
        setBookings([]);
        setInboxCounts({});
        setCalendarEvents([]);
        setCalendarTimeOffBlocks([]);
        setFinanceReport(null);
        setFinanceReconciliation(null);
        setServicesError(null);
        setStaffError(null);
        setBookingsError(null);
        setCalendarError(null);
        setFinanceError(null);
        setSchedulesError(null);
        setTimeOffError(null);
        setSelectedInboxBookingId(null);
        setIsInboxBookingDetailsSheetOpen(false);
        setSelectedInboxBookingDetails(null);
        setInboxBookingDetailsError(null);
    }, [siteId]);

    useEffect(() => {
        if (staff.length === 0) {
            setSelectedStaffForSchedule('');
            setStaffSchedules([]);
            setScheduleRows(defaultScheduleRows());
            setStaffTimeOffEntries([]);
            return;
        }

        const currentExists = staff.some((resource) => String(resource.id) === selectedStaffForSchedule);
        if (!currentExists) {
            setSelectedStaffForSchedule(String(staff[0].id));
        }
    }, [staff, selectedStaffForSchedule]);

    useEffect(() => {
        if (!selectedStaffForSchedule) {
            return;
        }

        void Promise.all([
            loadStaffSchedules(selectedStaffForSchedule),
            loadStaffTimeOff(selectedStaffForSchedule),
        ]);
    }, [loadStaffSchedules, loadStaffTimeOff, selectedStaffForSchedule]);

    const resetServiceForm = () => {
        setEditingServiceId(null);
        setServiceForm({
            name: '',
            slug: '',
            status: 'active',
            duration_minutes: '60',
            max_parallel_bookings: '1',
            requires_staff: 'true',
            price: '0',
            currency: 'GEL',
            description: '',
        });
    };

    const openCreateServiceSheet = () => {
        resetServiceForm();
        setIsServiceSheetOpen(true);
    };

    const closeServiceSheet = () => {
        setIsServiceSheetOpen(false);
        resetServiceForm();
    };

    const resetStaffForm = () => {
        setEditingStaffId(null);
        setStaffForm({
            name: '',
            slug: '',
            type: 'staff',
            status: 'active',
            email: '',
            phone: '',
            timezone: 'Asia/Tbilisi',
            max_parallel_bookings: '1',
        });
    };

    const openCreateStaffSheet = () => {
        resetStaffForm();
        setIsStaffSheetOpen(true);
    };

    const closeStaffSheet = () => {
        setIsStaffSheetOpen(false);
        resetStaffForm();
    };

    const updateScheduleRow = (dayOfWeek: number, patch: Partial<ScheduleFormRow>) => {
        setScheduleRows((current) => current.map((item) => (
            item.day_of_week === dayOfWeek
                ? { ...item, ...patch }
                : item
        )));
    };

    const applyWorkSchedulePreset = (preset: 'weekdays' | 'all-days' | 'all-off') => {
        setScheduleRows((current) => current.map((row) => {
            if (preset === 'all-off') {
                return { ...row, is_available: false };
            }

            if (preset === 'all-days') {
                return {
                    ...row,
                    is_available: true,
                    start_time: row.start_time || '09:00',
                    end_time: row.end_time || '18:00',
                };
            }

            return {
                ...row,
                is_available: row.day_of_week >= 1 && row.day_of_week <= 5,
                start_time: row.start_time || '09:00',
                end_time: row.end_time || '18:00',
            };
        }));
    };

    const handleSubmitService = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setIsSubmittingService(true);

        const payload = {
            name: serviceForm.name.trim(),
            slug: (serviceForm.slug.trim() || slugify(serviceForm.name)),
            status: serviceForm.status,
            duration_minutes: Number(serviceForm.duration_minutes),
            max_parallel_bookings: Number(serviceForm.max_parallel_bookings),
            requires_staff: serviceForm.requires_staff === 'true',
            price: serviceForm.price,
            currency: serviceForm.currency.trim().toUpperCase(),
            description: serviceForm.description.trim() || null,
        };

        try {
            if (editingServiceId === null) {
                await axios.post<ServiceMutationResponse>(`/panel/sites/${siteId}/booking/services`, payload);
                toast.success(t('Booking service created'));
            } else {
                await axios.put<ServiceMutationResponse>(`/panel/sites/${siteId}/booking/services/${editingServiceId}`, payload);
                toast.success(t('Booking service updated'));
            }

            setIsServiceSheetOpen(false);
            resetServiceForm();
            await loadServices();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save booking service')));
        } finally {
            setIsSubmittingService(false);
        }
    };

    const handleEditService = (service: BookingServiceItem) => {
        setEditingServiceId(service.id);
        setServiceForm({
            name: service.name,
            slug: service.slug,
            status: service.status,
            duration_minutes: String(service.duration_minutes),
            max_parallel_bookings: String(service.max_parallel_bookings),
            requires_staff: service.requires_staff ? 'true' : 'false',
            price: service.price,
            currency: service.currency,
            description: service.description ?? '',
        });
        setIsServiceSheetOpen(true);
    };

    const handleDeleteService = async (service: BookingServiceItem) => {
        if (!window.confirm(t('Delete this service?'))) {
            return;
        }

        try {
            await axios.delete(`/panel/sites/${siteId}/booking/services/${service.id}`);
            toast.success(t('Booking service deleted'));
            await loadServices();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete booking service')));
        }
    };

    const handleSubmitStaff = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setIsSubmittingStaff(true);

        const payload = {
            name: staffForm.name.trim(),
            slug: (staffForm.slug.trim() || slugify(staffForm.name)),
            type: staffForm.type,
            status: staffForm.status,
            email: staffForm.email.trim() || null,
            phone: staffForm.phone.trim() || null,
            timezone: staffForm.timezone.trim() || 'Asia/Tbilisi',
            max_parallel_bookings: Number(staffForm.max_parallel_bookings),
        };

        try {
            let savedStaffId: number | null = null;

            if (editingStaffId === null) {
                const response = await axios.post<StaffMutationResponse>(`/panel/sites/${siteId}/booking/staff`, payload);
                savedStaffId = response.data.staff_resource?.id ?? null;
                toast.success(t('Booking staff/resource created'));
            } else {
                const response = await axios.put<StaffMutationResponse>(`/panel/sites/${siteId}/booking/staff/${editingStaffId}`, payload);
                savedStaffId = response.data.staff_resource?.id ?? editingStaffId;
                toast.success(t('Booking staff/resource updated'));
            }

            setIsStaffSheetOpen(false);
            resetStaffForm();
            await loadStaff();
            if (savedStaffId) {
                setSelectedStaffForSchedule(String(savedStaffId));
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save booking staff/resource')));
        } finally {
            setIsSubmittingStaff(false);
        }
    };

    const handleEditStaff = (resource: BookingStaffItem) => {
        setEditingStaffId(resource.id);
        setStaffForm({
            name: resource.name,
            slug: resource.slug,
            type: resource.type,
            status: resource.status,
            email: resource.email ?? '',
            phone: resource.phone ?? '',
            timezone: resource.timezone,
            max_parallel_bookings: String(resource.max_parallel_bookings),
        });
        setIsStaffSheetOpen(true);
    };

    const handleDeleteStaff = async (resource: BookingStaffItem) => {
        if (!window.confirm(t('Delete this staff/resource?'))) {
            return;
        }

        try {
            await axios.delete(`/panel/sites/${siteId}/booking/staff/${resource.id}`);
            toast.success(t('Booking staff/resource deleted'));
            await loadStaff();
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete booking staff/resource')));
        }
    };

    const handleSaveStaffSchedules = async () => {
        if (!selectedStaffForSchedule) {
            toast.error(t('Select staff/resource first'));
            return;
        }

        setIsSavingSchedules(true);
        try {
            const payload = {
                schedules: scheduleRows.map((row) => ({
                    day_of_week: row.day_of_week,
                    start_time: row.start_time,
                    end_time: row.end_time,
                    is_available: row.is_available,
                })),
            };

            await axios.put(`/panel/sites/${siteId}/booking/staff/${selectedStaffForSchedule}/work-schedules`, payload);
            toast.success(t('Staff schedule saved'));
            await Promise.all([
                loadStaffSchedules(selectedStaffForSchedule),
                loadCalendar(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to save staff schedule')));
        } finally {
            setIsSavingSchedules(false);
        }
    };

    const handleCreateTimeOff = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!selectedStaffForSchedule) {
            toast.error(t('Select staff/resource first'));
            return;
        }

        setIsSavingTimeOff(true);
        try {
            await axios.post(`/panel/sites/${siteId}/booking/staff/${selectedStaffForSchedule}/time-off`, {
                starts_at: timeOffForm.starts_at,
                ends_at: timeOffForm.ends_at,
                status: timeOffForm.status,
                reason: timeOffForm.reason.trim() || null,
            });

            toast.success(t('Time-off entry created'));
            setTimeOffForm({
                starts_at: '',
                ends_at: '',
                status: 'approved',
                reason: '',
            });

            await Promise.all([
                loadStaffTimeOff(selectedStaffForSchedule),
                loadCalendar(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to create time-off entry')));
        } finally {
            setIsSavingTimeOff(false);
        }
    };

    const handleDeleteTimeOff = async (entry: StaffTimeOffItem) => {
        if (!selectedStaffForSchedule) {
            return;
        }

        if (!window.confirm(t('Delete this time-off entry?'))) {
            return;
        }

        setIsSavingTimeOff(true);
        try {
            await axios.delete(`/panel/sites/${siteId}/booking/staff/${selectedStaffForSchedule}/time-off/${entry.id}`);
            toast.success(t('Time-off entry deleted'));
            await Promise.all([
                loadStaffTimeOff(selectedStaffForSchedule),
                loadCalendar(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to delete time-off entry')));
        } finally {
            setIsSavingTimeOff(false);
        }
    };

    const handleUpdateTimeOffStatus = async (entry: StaffTimeOffItem, status: StaffTimeOffItem['status']) => {
        if (!selectedStaffForSchedule) {
            return;
        }

        setIsSavingTimeOff(true);
        try {
            await axios.put(`/panel/sites/${siteId}/booking/staff/${selectedStaffForSchedule}/time-off/${entry.id}`, {
                status,
            });
            toast.success(t('Time-off status updated'));
            await Promise.all([
                loadStaffTimeOff(selectedStaffForSchedule),
                loadCalendar(),
            ]);
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update time-off status')));
        } finally {
            setIsSavingTimeOff(false);
        }
    };

    const fetchBookingDetails = useCallback(async (bookingId: number): Promise<BookingDetailsItem> => {
        const response = await axios.get<BookingShowResponse>(`/panel/sites/${siteId}/booking/bookings/${bookingId}`);
        return response.data.booking;
    }, [siteId]);

    const loadInboxBookingDetails = useCallback(async (bookingId: number) => {
        setIsLoadingInboxBookingDetails(true);
        setInboxBookingDetailsError(null);
        try {
            const booking = await fetchBookingDetails(bookingId);
            setSelectedInboxBookingDetails(booking);
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load booking details'));
            setInboxBookingDetailsError(message);
            setSelectedInboxBookingDetails(null);
            toast.error(message);
        } finally {
            setIsLoadingInboxBookingDetails(false);
        }
    }, [fetchBookingDetails, t]);

    const openInboxBookingDetailsSheet = useCallback((bookingId: number) => {
        setSelectedInboxBookingId(bookingId);
        setIsInboxBookingDetailsSheetOpen(true);
        setSelectedInboxBookingDetails(null);
        setInboxBookingDetailsError(null);
        void loadInboxBookingDetails(bookingId);
    }, [loadInboxBookingDetails]);

    const openCreateBookingDrawer = () => {
        const next = defaultBookingFormState();
        const firstService = services[0];

        if (firstService) {
            next.service_id = String(firstService.id);
            next.duration_minutes = String(firstService.duration_minutes || 60);
        }

        setBookingForm(next);
        setBookingDrawerMode('create');
        setBookingDrawerBookingId(null);
        setBookingDrawerDetails(null);
        setBookingDrawerStatus('pending');
        setBookingDrawerError(null);
        setIsLoadingBookingDrawer(false);
        setBookingCustomerSearchInput('');
        setBookingCustomerSearchResults([]);
        setBookingCustomerSearchError(null);
        setSelectedBookingCustomerUser(null);
        setIsBookingDrawerOpen(true);
    };

    const openEditBookingDrawer = async (bookingId: number) => {
        setBookingDrawerMode('edit');
        setBookingDrawerBookingId(bookingId);
        setBookingDrawerError(null);
        setIsLoadingBookingDrawer(true);
        setIsBookingDrawerOpen(true);

        try {
            const booking = await fetchBookingDetails(bookingId);

            setBookingDrawerDetails(booking);
            setBookingDrawerStatus(booking.status);
            setBookingForm({
                service_id: booking.service.id ? String(booking.service.id) : '',
                staff_resource_id: booking.staff_resource.id ? String(booking.staff_resource.id) : '',
                starts_at: toInputDateTime(booking.starts_at),
                duration_minutes: String(booking.duration_minutes || 60),
                customer_name: booking.customer_name ?? '',
                customer_email: booking.customer_email ?? '',
                customer_phone: booking.customer_phone ?? '',
                customer_notes: booking.customer_notes ?? '',
            });

            const metaCustomerUserIdRaw = booking.meta_json?.customer_user_id;
            const metaCustomerUserId = typeof metaCustomerUserIdRaw === 'number'
                ? metaCustomerUserIdRaw
                : Number.parseInt(String(metaCustomerUserIdRaw ?? ''), 10);

            if (Number.isInteger(metaCustomerUserId) && metaCustomerUserId > 0 && booking.customer_email) {
                setSelectedBookingCustomerUser({
                    id: metaCustomerUserId,
                    name: booking.customer_name ?? booking.customer_email,
                    email: booking.customer_email,
                });
            } else {
                setSelectedBookingCustomerUser(null);
            }

            setBookingCustomerSearchInput(booking.customer_email ?? booking.customer_name ?? '');
            setBookingCustomerSearchResults([]);
            setBookingCustomerSearchError(null);
        } catch (error) {
            const message = getApiErrorMessage(error, t('Failed to load booking details'));
            setBookingDrawerError(message);
            toast.error(message);
        } finally {
            setIsLoadingBookingDrawer(false);
        }
    };

    const handleSubmitBookingDrawer = async (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        if (!bookingForm.service_id || !bookingForm.starts_at) {
            toast.error(t('Please fill required booking fields'));
            return;
        }

        setIsSubmittingBooking(true);
        setBookingDrawerError(null);
        try {
            const shouldAutoRegisterCustomer = bookingDrawerMode === 'create'
                && !selectedBookingCustomerUser
                && bookingForm.customer_email.trim() !== ''
                && bookingForm.customer_name.trim() !== '';

            if (bookingDrawerMode === 'create') {
                await axios.post<BookingMutationResponse>(`/panel/sites/${siteId}/booking/bookings`, {
                    service_id: Number(bookingForm.service_id),
                    staff_resource_id: bookingForm.staff_resource_id ? Number(bookingForm.staff_resource_id) : null,
                    starts_at: bookingForm.starts_at,
                    duration_minutes: Number(bookingForm.duration_minutes),
                    customer_name: bookingForm.customer_name.trim() || null,
                    customer_email: bookingForm.customer_email.trim() || null,
                    customer_phone: bookingForm.customer_phone.trim() || null,
                    customer_user_id: selectedBookingCustomerUser?.id ?? null,
                    register_customer_if_missing: shouldAutoRegisterCustomer,
                    customer_notes: bookingForm.customer_notes.trim() || null,
                    source: 'panel',
                });

                toast.success(t('Booking created'));
                setIsBookingDrawerOpen(false);
                setBookingForm(defaultBookingFormState());
            } else if (bookingDrawerBookingId) {
                await axios.post<BookingMutationResponse>(`/panel/sites/${siteId}/booking/bookings/${bookingDrawerBookingId}/reschedule`, {
                    service_id: Number(bookingForm.service_id),
                    staff_resource_id: bookingForm.staff_resource_id ? Number(bookingForm.staff_resource_id) : null,
                    starts_at: bookingForm.starts_at,
                    duration_minutes: Number(bookingForm.duration_minutes),
                    customer_name: bookingForm.customer_name.trim() || null,
                    customer_email: bookingForm.customer_email.trim() || null,
                    customer_phone: bookingForm.customer_phone.trim() || null,
                    customer_user_id: selectedBookingCustomerUser?.id ?? null,
                    register_customer_if_missing: false,
                    customer_notes: bookingForm.customer_notes.trim() || null,
                });

                if (bookingDrawerDetails?.status !== bookingDrawerStatus) {
                    await axios.post<BookingMutationResponse>(`/panel/sites/${siteId}/booking/bookings/${bookingDrawerBookingId}/status`, {
                        status: bookingDrawerStatus,
                    });
                }

                toast.success(t('Booking updated'));

                try {
                    const refreshedBooking = await fetchBookingDetails(bookingDrawerBookingId);
                    setBookingDrawerDetails(refreshedBooking);
                    setBookingDrawerStatus(refreshedBooking.status);
                } catch {
                    // Silent refresh failure in drawer; list/calendar reload still updates visible data.
                }
            }

            await Promise.all([loadBookings(), loadCalendar()]);
            if (bookingDrawerMode === 'edit' && bookingDrawerBookingId && isInboxBookingDetailsSheetOpen && selectedInboxBookingId === bookingDrawerBookingId) {
                void loadInboxBookingDetails(bookingDrawerBookingId);
            }
        } catch (error) {
            const fallback = bookingDrawerMode === 'create'
                ? t('Failed to create booking')
                : t('Failed to update booking');
            const message = getApiErrorMessage(error, fallback);
            setBookingDrawerError(message);
            toast.error(message);
        } finally {
            setIsSubmittingBooking(false);
        }
    };

    const handleStatusUpdate = async (bookingId: number, status: BookingSummaryItem['status']) => {
        setUpdatingBookingId(bookingId);
        try {
            await axios.post<BookingMutationResponse>(`/panel/sites/${siteId}/booking/bookings/${bookingId}/status`, { status });
            toast.success(t('Booking status updated'));
            await Promise.all([loadBookings(), loadCalendar()]);
            if (isInboxBookingDetailsSheetOpen && selectedInboxBookingId === bookingId) {
                void loadInboxBookingDetails(bookingId);
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to update booking status')));
        } finally {
            setUpdatingBookingId(null);
        }
    };

    const handleCancelBooking = async (bookingId: number) => {
        setUpdatingBookingId(bookingId);
        try {
            await axios.post<BookingMutationResponse>(`/panel/sites/${siteId}/booking/bookings/${bookingId}/cancel`, {
                reason: t('Cancelled by manager'),
            });
            toast.success(t('Booking cancelled'));
            await Promise.all([loadBookings(), loadCalendar()]);
            if (isInboxBookingDetailsSheetOpen && selectedInboxBookingId === bookingId) {
                void loadInboxBookingDetails(bookingId);
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to cancel booking')));
        } finally {
            setUpdatingBookingId(null);
        }
    };

    const handleDeleteBookingFromInbox = useCallback(async (bookingId: number) => {
        const confirmed = window.confirm(t('Cancel this booking?'));
        if (!confirmed) {
            return;
        }

        await handleCancelBooking(bookingId);
    }, [handleCancelBooking, t]);

    const handleRescheduleBooking = async (bookingId: number) => {
        const startsAt = rescheduleDrafts[bookingId];
        if (!startsAt) {
            toast.error(t('Pick a new date/time first'));
            return;
        }

        setUpdatingBookingId(bookingId);
        try {
            await axios.post<BookingMutationResponse>(`/panel/sites/${siteId}/booking/bookings/${bookingId}/reschedule`, {
                starts_at: startsAt,
            });
            toast.success(t('Booking rescheduled'));
            await Promise.all([loadBookings(), loadCalendar()]);
            if (isInboxBookingDetailsSheetOpen && selectedInboxBookingId === bookingId) {
                void loadInboxBookingDetails(bookingId);
            }
        } catch (error) {
            toast.error(getApiErrorMessage(error, t('Failed to reschedule booking')));
        } finally {
            setUpdatingBookingId(null);
        }
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2 items-center justify-between">
                <div className="flex flex-wrap gap-2 items-center">
                    <Badge variant="secondary">{t('Pending')}: {inboxCounts.pending ?? 0}</Badge>
                    <Badge variant="secondary">{t('Confirmed')}: {inboxCounts.confirmed ?? 0}</Badge>
                    <Badge variant="secondary">{t('In Progress')}: {inboxCounts.in_progress ?? 0}</Badge>
                    <Badge variant="secondary">{t('Completed')}: {inboxCounts.completed ?? 0}</Badge>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                        bookingsLoadedKeyRef.current = null;
                        calendarLoadedKeyRef.current = null;
                        servicesLoadedForSiteRef.current = null;
                        staffLoadedForSiteRef.current = null;
                        const tasks: Array<Promise<unknown>> = [];

                        if (['inbox', 'services', 'finance'].includes(activeTab)) {
                            tasks.push(loadServices());
                        }

                        if (['inbox', 'team', 'finance'].includes(activeTab)) {
                            tasks.push(loadStaff());
                        }

                        if (activeTab === 'inbox') {
                            tasks.push(loadBookings());
                        }

                        if (activeTab === 'calendar' || activeTab === 'team') {
                            tasks.push(loadCalendar());
                        }

                        if (activeTab === 'finance') {
                            tasks.push(loadFinanceData());
                        }

                        if (tasks.length > 0) {
                            void Promise.allSettled(tasks);
                        }
                    }}
                >
                    <RefreshCw className="h-4 w-4 mr-1.5" />
                    {t('Refresh')}
                </Button>
            </div>

            <Tabs value={activeTab} onValueChange={(value) => setActiveTab(value as BookingTab)}>
                {!hideTabs ? (
                    <TabsList>
                        <TabsTrigger value="inbox">{t('Inbox')}</TabsTrigger>
                        <TabsTrigger value="calendar">{t('Calendar')}</TabsTrigger>
                        <TabsTrigger value="services">{t('Services')}</TabsTrigger>
                        <TabsTrigger value="team">{t('Team')}</TabsTrigger>
                        <TabsTrigger value="finance">{t('Finance')}</TabsTrigger>
                    </TabsList>
                ) : null}

                <TabsContent value="inbox" className="space-y-4 pt-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between gap-3">
                            <div>
                                <CardTitle>{t('Bookings')}</CardTitle>
                                <CardDescription>{t('Create and manage bookings from a side panel')}</CardDescription>
                            </div>
                            <Button onClick={openCreateBookingDrawer} disabled={isLoadingServices || services.length === 0}>
                                <CalendarDays className="h-4 w-4 mr-1.5" />
                                {t('Add Booking')}
                            </Button>
                        </CardHeader>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Booking Inbox')}</CardTitle>
                            <CardDescription>{t('Search, filter and manage booking statuses')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {bookingsError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {bookingsError}
                                </div>
                            ) : null}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <Input
                                    placeholder={t('Search by booking/customer')}
                                    value={searchTerm}
                                    onChange={(event) => setSearchTerm(event.target.value)}
                                />
                                <Select value={statusFilter} onValueChange={(value) => setStatusFilter(value as typeof statusFilter)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All statuses')}</SelectItem>
                                        <SelectItem value="pending">{t('Pending')}</SelectItem>
                                        <SelectItem value="confirmed">{t('Confirmed')}</SelectItem>
                                        <SelectItem value="in_progress">{t('In Progress')}</SelectItem>
                                        <SelectItem value="completed">{t('Completed')}</SelectItem>
                                        <SelectItem value="cancelled">{t('Cancelled')}</SelectItem>
                                        <SelectItem value="no_show">{t('No Show')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button variant="outline" onClick={() => void loadBookings()}>
                                    {isLoadingBookings ? <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> : <RefreshCw className="h-4 w-4 mr-1.5" />}
                                    {t('Apply')}
                                </Button>
                            </div>

                            {bookings.length === 0 ? (
                                <div className="rounded-md border border-dashed p-8 text-center text-sm text-muted-foreground">
                                    {isLoadingBookings ? t('Loading bookings...') : t('No bookings found')}
                                </div>
                            ) : (
                                <div className="overflow-x-auto rounded-lg border">
                                    <table className="min-w-[980px] w-full text-sm">
                                        <thead className="bg-background">
                                            <tr className="border-b text-left text-xs uppercase tracking-wide text-muted-foreground">
                                                <th className="px-4 py-3 font-medium">{t('Booking')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Customer')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Service')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Staff')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Status')}</th>
                                                <th className="px-4 py-3 font-medium">{t('Schedule')}</th>
                                                <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {bookings.map((booking) => {
                                                const isSelected = selectedInboxBookingId === booking.id && isInboxBookingDetailsSheetOpen;
                                                const isBusy = updatingBookingId === booking.id;

                                                return (
                                                    <tr
                                                        key={booking.id}
                                                        role="button"
                                                        tabIndex={0}
                                                        onClick={() => openInboxBookingDetailsSheet(booking.id)}
                                                        onKeyDown={(event) => {
                                                            if (event.key === 'Enter' || event.key === ' ') {
                                                                event.preventDefault();
                                                                openInboxBookingDetailsSheet(booking.id);
                                                            }
                                                        }}
                                                        className={`cursor-pointer border-b align-top transition ${
                                                            isSelected ? 'bg-primary/5' : 'hover:bg-muted/20'
                                                        }`}
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="space-y-1">
                                                                <div className="font-semibold">{booking.booking_number}</div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {t('Duration')}: {booking.duration_minutes} {t('min')}
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <div className="min-w-0 space-y-1">
                                                                <div className="truncate font-medium">
                                                                    {booking.customer_name || t('Guest')}
                                                                </div>
                                                                <div className="truncate text-xs text-muted-foreground">
                                                                    {booking.customer_email || '—'}
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    {booking.customer_phone || '—'}
                                                                </div>
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <div className="space-y-1">
                                                                <div className="font-medium">{booking.service.name ?? '—'}</div>
                                                                {booking.service.id ? (
                                                                    <div className="text-xs text-muted-foreground">#{booking.service.id}</div>
                                                                ) : null}
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <div className="space-y-1">
                                                                <div className="font-medium">{booking.staff_resource.name ?? t('Unassigned')}</div>
                                                                {booking.staff_resource.id ? (
                                                                    <div className="text-xs text-muted-foreground">#{booking.staff_resource.id}</div>
                                                                ) : null}
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <Badge variant={statusVariant(booking.status)}>
                                                                {t(booking.status)}
                                                            </Badge>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <div className="space-y-1 text-xs text-muted-foreground">
                                                                <div>{formatDateTime(booking.starts_at, locale)}</div>
                                                                <div>{formatDateTime(booking.ends_at, locale)}</div>
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <div className="flex items-center justify-end gap-1">
                                                                <Button
                                                                    type="button"
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="h-8 w-8"
                                                                    disabled={isBusy}
                                                                    onClick={(event) => {
                                                                        event.stopPropagation();
                                                                        void openEditBookingDrawer(booking.id);
                                                                    }}
                                                                    aria-label={t('Edit Booking')}
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    size="icon"
                                                                    variant="ghost"
                                                                    className="h-8 w-8 text-destructive"
                                                                    disabled={isBusy}
                                                                    onClick={(event) => {
                                                                        event.stopPropagation();
                                                                        void handleDeleteBookingFromInbox(booking.id);
                                                                    }}
                                                                    aria-label={t('Cancel Booking')}
                                                                >
                                                                    {isBusy ? (
                                                                        <Loader2 className="h-4 w-4 animate-spin" />
                                                                    ) : (
                                                                        <Trash2 className="h-4 w-4" />
                                                                    )}
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="calendar" className="space-y-4 pt-4">
                    <div className="min-w-0">
                        <div
                            className="webu-booking-calendar-shell overflow-hidden rounded-xl border bg-card"
                            data-sidebar-open={isCalendarSidebarOpen ? 'true' : 'false'}
                        >
                            <div className="webu-booking-calendar-sidebar border-b bg-muted/10 xl:border-b-0 xl:border-r">
                                <div className="webu-booking-calendar-sidebar__section webu-booking-calendar-sidebar__section--cta border-b p-4">
                                    <Button
                                        className="w-full justify-center"
                                        onClick={openCreateBookingDrawer}
                                        disabled={isLoadingServices || services.length === 0}
                                    >
                                        <CalendarDays className="mr-2 h-4 w-4" />
                                        {t('Add Booking')}
                                    </Button>
                                </div>

                                <div className="webu-booking-calendar-sidebar__section border-b p-4">
                                    <div className="mb-3 flex items-center justify-between gap-2">
                                        <p className="text-sm font-semibold">{miniCalendarMonthLabel}</p>
                                        <div className="flex items-center gap-1">
                                            <Button type="button" size="icon" variant="ghost" className="h-8 w-8" onClick={handleMiniCalendarPrevMonth}>
                                                <ChevronLeft className="h-4 w-4" />
                                            </Button>
                                            <Button type="button" size="icon" variant="ghost" className="h-8 w-8" onClick={handleMiniCalendarNextMonth}>
                                                <ChevronRight className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="webu-mini-date-picker">
                                        <div className="webu-mini-date-picker__weekdays">
                                            {miniCalendarWeekdayLabels.map((label, index) => (
                                                <div key={`mini-calendar-weekday-${index}`} className="webu-mini-date-picker__weekday" title={label}>
                                                    {label.slice(0, 2)}
                                                </div>
                                            ))}
                                        </div>
                                        <div className="webu-mini-date-picker__grid">
                                            {miniCalendarDays.map((day) => (
                                                <button
                                                    key={`mini-calendar-day-${day.iso}`}
                                                    type="button"
                                                    onClick={() => handleMiniCalendarPickDate(day.date)}
                                                    className={[
                                                        'webu-mini-date-picker__day',
                                                        day.isCurrentMonth ? '' : 'is-outside',
                                                        day.isToday ? 'is-today' : '',
                                                        day.isInRange ? 'is-in-range' : '',
                                                        day.isRangeStart ? 'is-range-start' : '',
                                                        day.isRangeEnd ? 'is-range-end' : '',
                                                    ].filter(Boolean).join(' ')}
                                                    aria-label={day.iso}
                                                >
                                                    <span>{day.day}</span>
                                                    {day.eventCount > 0 ? (
                                                        <span className="webu-mini-date-picker__dot" aria-hidden="true" />
                                                    ) : null}
                                                </button>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                <div className="webu-booking-calendar-sidebar__section border-b p-4">
                                    <div className="mb-3 flex items-center justify-between gap-2">
                                        <div>
                                            <h3 className="text-sm font-semibold">{t('Event Filters')}</h3>
                                            <p className="mt-0.5 text-xs text-muted-foreground">{t('Show or hide booking statuses')}</p>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="ghost"
                                            className="h-7 px-2 text-xs"
                                            onClick={() => setCalendarStatusFilters(BOOKING_CALENDAR_STATUS_OPTIONS)}
                                            disabled={areAllCalendarStatusFiltersSelected}
                                        >
                                            {t('View All')}
                                        </Button>
                                    </div>

                                    <div className="space-y-1.5">
                                        {BOOKING_CALENDAR_STATUS_OPTIONS.map((status) => {
                                            const enabled = calendarStatusFilters.includes(status);
                                            const count = calendarEventCountsByStatus[status];

                                            return (
                                                <button
                                                    key={`calendar-status-${status}`}
                                                    type="button"
                                                    onClick={() => {
                                                        setCalendarStatusFilters((current) => {
                                                            if (current.includes(status)) {
                                                                const next = current.filter((item) => item !== status);
                                                                return next.length > 0 ? next : current;
                                                            }

                                                            return [...current, status];
                                                        });
                                                    }}
                                                    className={[
                                                        'webu-calendar-filter-row',
                                                        enabled ? 'is-active' : '',
                                                    ].filter(Boolean).join(' ')}
                                                >
                                                    <span className={`webu-calendar-filter-row__checkbox ${enabled ? 'is-checked' : ''}`} aria-hidden="true" />
                                                    <span className="webu-calendar-filter-row__label">{t(status)}</span>
                                                    <span
                                                        className={`webu-calendar-filter-row__dot webu-calendar-filter-row__dot--${status}`}
                                                        aria-hidden="true"
                                                    />
                                                    <span className="webu-calendar-filter-row__count">{count}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>

                                <div className="webu-booking-calendar-sidebar__section p-4 space-y-4">
                                    <div>
                                        <h3 className="text-sm font-semibold">{t('Advanced Filters')}</h3>
                                        <p className="mt-0.5 text-xs text-muted-foreground">{t('Filter bookings by date, service and staff')}</p>
                                    </div>

                                    {calendarError ? (
                                        <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                            {calendarError}
                                        </div>
                                    ) : null}

                                    <div className="grid grid-cols-1 gap-3">
                                        <div className="space-y-1.5">
                                            <Label>{t('From')}</Label>
                                            <Input type="date" value={calendarFrom} onChange={(event) => setCalendarFrom(event.target.value)} />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>{t('To')}</Label>
                                            <Input type="date" value={calendarTo} onChange={(event) => setCalendarTo(event.target.value)} />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>{t('Service')}</Label>
                                            <Select value={calendarServiceFilter} onValueChange={setCalendarServiceFilter}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">{t('All')}</SelectItem>
                                                    {services.map((service) => (
                                                        <SelectItem key={`calendar-service-${service.id}`} value={String(service.id)}>
                                                            {service.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>{t('Staff')}</Label>
                                            <Select value={calendarStaffFilter} onValueChange={setCalendarStaffFilter}>
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="all">{t('All')}</SelectItem>
                                                    {staff.map((member) => (
                                                        <SelectItem key={`calendar-staff-${member.id}`} value={String(member.id)}>
                                                            {member.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <Button className="w-full" variant="outline" onClick={() => void loadCalendar()} disabled={isLoadingCalendar}>
                                            {isLoadingCalendar ? <Loader2 className="mr-1.5 h-4 w-4 animate-spin" /> : <CalendarDays className="mr-1.5 h-4 w-4" />}
                                            {t('Load Calendar')}
                                        </Button>
                                    </div>

                                    <div className="rounded-lg border bg-muted/20 p-3">
                                        <p className="text-xs font-medium">{t('Summary')}</p>
                                        <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                                            <div className="rounded-md bg-background px-2 py-1.5">
                                                <p className="text-muted-foreground">{t('Bookings')}</p>
                                                <p className="font-semibold text-sm">{filteredCalendarEvents.length}</p>
                                            </div>
                                            <div className="rounded-md bg-background px-2 py-1.5">
                                                <p className="text-muted-foreground">{t('Time Off')}</p>
                                                <p className="font-semibold text-sm">{calendarTimeOffBlocks?.length ?? 0}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="webu-booking-calendar-main min-w-0">
                                <div className="flex flex-row items-start justify-between gap-3 border-b px-4 py-4">
                                    <div className="min-w-0">
                                        <h3 className="text-base font-semibold">{t('Calendar')}</h3>
                                        <p className="mt-1 text-xs text-muted-foreground">{t('Monthly, weekly and daily booking calendar')}</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="xl:hidden"
                                            onClick={() => setIsCalendarSidebarOpen((current) => !current)}
                                        >
                                            <Menu className="mr-1.5 h-4 w-4" />
                                            {t('Filters')}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => void loadCalendar()}
                                            disabled={isLoadingCalendar}
                                        >
                                            {isLoadingCalendar ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                        </Button>
                                    </div>
                                </div>
                                <div className="space-y-3 p-4">
                                    {isLoadingCalendar && filteredCalendarEvents.length === 0 ? (
                                        <div className="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
                                            <div className="flex items-center justify-center gap-2">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                <span>{t('Loading calendar...')}</span>
                                            </div>
                                        </div>
                                    ) : null}

                                    {!isLoadingCalendar && fullCalendarEvents.length === 0 ? (
                                        <div className="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground">
                                            {t('No bookings in selected range')}
                                        </div>
                                    ) : null}

                                    <div className="webu-booking-calendar rounded-xl border bg-background p-3 sm:p-4">
                                        <FullCalendar
                                            ref={bookingCalendarRef}
                                            plugins={[interactionPlugin, dayGridPlugin, timeGridPlugin, listPlugin]}
                                            initialView="dayGridMonth"
                                            headerToolbar={{
                                                start: 'sidebarToggle prev,next title',
                                                center: '',
                                                end: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth',
                                            }}
                                            customButtons={{
                                                sidebarToggle: {
                                                    text: '☰',
                                                    click: () => setIsCalendarSidebarOpen((current) => !current),
                                                },
                                            }}
                                            buttonText={{
                                                month: t('Month'),
                                                week: t('Week'),
                                                day: t('Day'),
                                                list: t('List'),
                                            }}
                                            views={{
                                                timeGridWeek: {
                                                    titleFormat: { year: 'numeric', month: 'short', day: 'numeric' },
                                                },
                                                listMonth: {
                                                    titleFormat: { year: 'numeric', month: 'short' },
                                                },
                                            }}
                                            height="auto"
                                            stickyHeaderDates
                                            nowIndicator
                                            allDaySlot={false}
                                            slotMinTime="06:00:00"
                                            slotMaxTime="23:00:00"
                                            dayMaxEvents={2}
                                            navLinks
                                            editable={false}
                                            eventResizableFromStart={false}
                                            eventTimeFormat={{ hour: '2-digit', minute: '2-digit', meridiem: false }}
                                            events={fullCalendarEvents}
                                            datesSet={handleCalendarDatesSet}
                                            eventClick={handleCalendarEventClick}
                                            eventClassNames={(arg) => {
                                                const status = String(arg.event.extendedProps?.status ?? '');
                                                return status ? ['booking-calendar-event', `booking-status-${status}`] : [];
                                            }}
                                            dayHeaderFormat={{ weekday: 'short' }}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </TabsContent>

                <TabsContent value="services" className="space-y-4 pt-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>{t('Service Catalog')}</CardTitle>
                                    <CardDescription>{t('Manage booking services, pricing and durations')}</CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={openCreateServiceSheet}>
                                        <Plus className="mr-1.5 h-4 w-4" />
                                        {t('Add Service')}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            servicesLoadedForSiteRef.current = null;
                                            void loadServices();
                                        }}
                                        disabled={isLoadingServices}
                                    >
                                        {isLoadingServices ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {servicesError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {servicesError}
                                </div>
                            ) : null}
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Total Services')}</p>
                                    <p className="mt-1 text-xl font-semibold">{serviceCatalogSummary.total}</p>
                                </div>
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Active')}</p>
                                    <p className="mt-1 text-xl font-semibold">{serviceCatalogSummary.active}</p>
                                </div>
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Requires Staff')}</p>
                                    <p className="mt-1 text-xl font-semibold">{serviceCatalogSummary.requiresStaff}</p>
                                </div>
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Avg Duration')}</p>
                                    <p className="mt-1 text-xl font-semibold">
                                        {serviceCatalogSummary.avgDuration > 0 ? `${serviceCatalogSummary.avgDuration}m` : '—'}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <CardTitle>{t('Services List')}</CardTitle>
                                    <CardDescription>{t('Click edit to open service settings in a side panel')}</CardDescription>
                                </div>
                                <Button type="button" variant="outline" size="sm" onClick={openCreateServiceSheet}>
                                    <Plus className="mr-1.5 h-4 w-4" />
                                    {t('New Service')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {services.length === 0 ? (
                                <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    {isLoadingServices ? t('Loading services...') : t('No services configured')}
                                </div>
                            ) : (
                                services.map((service) => {
                                    const plainDescription = (service.description ?? '')
                                        .replace(/<[^>]*>/g, ' ')
                                        .replace(/\s+/g, ' ')
                                        .trim();

                                    return (
                                        <div key={service.id} className="rounded-xl border p-3 md:p-4">
                                            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <p className="truncate font-medium">{service.name}</p>
                                                        <Badge variant={service.status === 'active' ? 'secondary' : 'outline'}>
                                                            {service.status}
                                                        </Badge>
                                                        <Badge variant="outline">
                                                            {service.requires_staff ? t('Staff Required') : t('No Staff')}
                                                        </Badge>
                                                    </div>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        /{service.slug} · {service.duration_minutes}m · {service.price} {service.currency}
                                                    </p>
                                                    {plainDescription ? (
                                                        <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">
                                                            {plainDescription}
                                                        </p>
                                                    ) : null}
                                                    <div className="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground">
                                                        <span className="rounded-md border bg-muted/20 px-2 py-1">
                                                            {t('Max parallel')}: {service.max_parallel_bookings}
                                                        </span>
                                                        <span className="rounded-md border bg-muted/20 px-2 py-1">
                                                            {t('Online payment')}: {service.allow_online_payment ? t('Yes') : t('No')}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2 self-start xl:self-center">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleEditService(service)}
                                                    >
                                                        <Pencil className="mr-1.5 h-4 w-4" />
                                                        {t('Edit')}
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="text-destructive"
                                                        onClick={() => void handleDeleteService(service)}
                                                    >
                                                        <Trash2 className="mr-1.5 h-4 w-4" />
                                                        {t('Delete')}
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="team" className="space-y-4 pt-4">
                    <Card>
                        <CardHeader className="pb-3">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <CardTitle>{t('Team / Resources')}</CardTitle>
                                    <CardDescription>{t('Manage staff members, resources and weekly availability')}</CardDescription>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Button type="button" variant="outline" size="sm" onClick={openCreateStaffSheet}>
                                        <UserRound className="mr-1.5 h-4 w-4" />
                                        {t('Add Team Member')}
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            staffLoadedForSiteRef.current = null;
                                            void loadStaff();
                                        }}
                                        disabled={isLoadingStaff}
                                    >
                                        {isLoadingStaff ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {staffError ? (
                                <div className="mb-3 rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {staffError}
                                </div>
                            ) : null}
                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Total Members')}</p>
                                    <p className="mt-1 text-xl font-semibold">{staff.length}</p>
                                </div>
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Active')}</p>
                                    <p className="mt-1 text-xl font-semibold">
                                        {staff.filter((resource) => resource.status === 'active').length}
                                    </p>
                                </div>
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Staff')}</p>
                                    <p className="mt-1 text-xl font-semibold">
                                        {staff.filter((resource) => resource.type === 'staff').length}
                                    </p>
                                </div>
                                <div className="rounded-xl border bg-background/90 p-3">
                                    <p className="text-xs text-muted-foreground">{t('Resources')}</p>
                                    <p className="mt-1 text-xl font-semibold">
                                        {staff.filter((resource) => resource.type === 'resource').length}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between gap-2">
                                <CardTitle>{t('Team List')}</CardTitle>
                                <Button type="button" variant="outline" size="sm" onClick={openCreateStaffSheet}>
                                    <UserRound className="mr-1.5 h-4 w-4" />
                                    {t('Add')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {staff.length === 0 ? (
                                <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground text-center">
                                    {isLoadingStaff ? t('Loading team...') : t('No staff/resources configured')}
                                </div>
                            ) : (
                                staff.map((resource) => (
                                    <div key={resource.id} className="rounded-xl border p-3 md:p-4">
                                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <p className="font-medium truncate">{resource.name}</p>
                                                    <Badge variant={resource.status === 'active' ? 'secondary' : 'outline'}>{resource.status}</Badge>
                                                    <Badge variant="outline">{resource.type}</Badge>
                                                </div>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {resource.timezone}
                                                    {resource.email ? ` · ${resource.email}` : ''}
                                                    {resource.phone ? ` · ${resource.phone}` : ''}
                                                </p>
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {t('Max parallel bookings')}: {resource.max_parallel_bookings}
                                                </p>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => setSelectedStaffForSchedule(String(resource.id))}
                                                >
                                                    {t('Manage Schedule')}
                                                </Button>
                                                <Button size="sm" variant="outline" onClick={() => handleEditStaff(resource)}>
                                                    <UserRound className="h-4 w-4 mr-1.5" />
                                                    {t('Edit')}
                                                </Button>
                                                <Button size="sm" variant="ghost" className="text-destructive" onClick={() => void handleDeleteStaff(resource)}>
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Work Hours')}</CardTitle>
                            <CardDescription>{t('Configure weekly availability for each team member')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {schedulesError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {schedulesError}
                                </div>
                            ) : null}
                            <div className="rounded-xl border p-4 space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_auto] gap-3 items-end">
                                    <div className="space-y-1.5">
                                        <Label>{t('Staff/Resource')}</Label>
                                        <Select
                                            value={selectedStaffForSchedule || '__none__'}
                                            onValueChange={(value) => setSelectedStaffForSchedule(value === '__none__' ? '' : value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder={t('Select staff/resource')} />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="__none__">{t('Select')}</SelectItem>
                                                {staff.map((resource) => (
                                                    <SelectItem key={resource.id} value={String(resource.id)}>
                                                        {resource.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Button
                                        variant="outline"
                                        disabled={!selectedStaffForSchedule}
                                        onClick={() => {
                                            if (selectedStaffForSchedule) {
                                                void Promise.all([
                                                    loadStaffSchedules(selectedStaffForSchedule),
                                                    loadStaffTimeOff(selectedStaffForSchedule),
                                                ]);
                                            }
                                        }}
                                    >
                                        <RefreshCw className="h-4 w-4 mr-1.5" />
                                        {t('Reload')}
                                    </Button>
                                </div>

                                {selectedStaffScheduleResource ? (
                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                                        <div className="rounded-lg border bg-muted/20 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Selected')}</p>
                                            <p className="mt-1 font-semibold">{selectedStaffScheduleResource.name}</p>
                                            <p className="text-xs text-muted-foreground">{selectedStaffScheduleResource.type} · {selectedStaffScheduleResource.timezone}</p>
                                        </div>
                                        <div className="rounded-lg border bg-muted/20 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Working Days')}</p>
                                            <p className="mt-1 text-xl font-semibold">{workScheduleSummary.activeDays}</p>
                                        </div>
                                        <div className="rounded-lg border bg-muted/20 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Off Days')}</p>
                                            <p className="mt-1 text-xl font-semibold">{workScheduleSummary.inactiveDays}</p>
                                        </div>
                                        <div className="rounded-lg border bg-muted/20 p-3">
                                            <p className="text-xs text-muted-foreground">{t('Total Weekly Hours')}</p>
                                            <p className="mt-1 text-xl font-semibold">{workScheduleSummary.totalHours}</p>
                                        </div>
                                    </div>
                                ) : null}
                            </div>

                            {!selectedStaffForSchedule ? (
                                <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    {t('Select staff/resource to manage schedule')}
                                </div>
                            ) : (
                                <>
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-semibold">{t('Weekly Schedule')}</p>
                                            <p className="text-xs text-muted-foreground">{t('Toggle availability and set hours for each day')}</p>
                                        </div>
                                        <div className="flex flex-wrap gap-2">
                                            <Button type="button" size="sm" variant="outline" onClick={() => applyWorkSchedulePreset('weekdays')}>
                                                {t('Weekdays')}
                                            </Button>
                                            <Button type="button" size="sm" variant="outline" onClick={() => applyWorkSchedulePreset('all-days')}>
                                                {t('All Days')}
                                            </Button>
                                            <Button type="button" size="sm" variant="ghost" onClick={() => applyWorkSchedulePreset('all-off')}>
                                                {t('All Off')}
                                            </Button>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 md:grid-cols-2">
                                        {scheduleRows.map((row) => {
                                            const dayMeta = DAY_OF_WEEK_OPTIONS.find((option) => option.value === row.day_of_week);
                                            return (
                                                <div key={row.day_of_week} className={`rounded-xl border p-3 transition ${row.is_available ? 'bg-background' : 'bg-muted/20'}`}>
                                                    <div className="flex items-center justify-between gap-3">
                                                        <div>
                                                            <p className="text-sm font-semibold">{t(dayMeta?.label ?? `Day ${row.day_of_week}`)}</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {row.is_available ? `${row.start_time} - ${row.end_time}` : t('Day off')}
                                                            </p>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge variant={row.is_available ? 'secondary' : 'outline'} className="capitalize">
                                                                {row.is_available ? t('Available') : t('Off')}
                                                            </Badge>
                                                            <Switch
                                                                checked={row.is_available}
                                                                onCheckedChange={(checked) => updateScheduleRow(row.day_of_week, { is_available: checked })}
                                                                aria-label={t('Toggle availability')}
                                                            />
                                                        </div>
                                                    </div>

                                                    <div className="mt-3 grid grid-cols-2 gap-2">
                                                        <div className="space-y-1">
                                                            <Label className="text-xs">{t('Start')}</Label>
                                                            <Input
                                                                type="time"
                                                                value={row.start_time}
                                                                disabled={!row.is_available}
                                                                className="h-9"
                                                                onChange={(event) => updateScheduleRow(row.day_of_week, { start_time: event.target.value })}
                                                            />
                                                        </div>
                                                        <div className="space-y-1">
                                                            <Label className="text-xs">{t('End')}</Label>
                                                            <Input
                                                                type="time"
                                                                value={row.end_time}
                                                                disabled={!row.is_available}
                                                                className="h-9"
                                                                onChange={(event) => updateScheduleRow(row.day_of_week, { end_time: event.target.value })}
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>

                                    <div className="flex flex-wrap justify-end gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => selectedStaffForSchedule && void loadStaffSchedules(selectedStaffForSchedule)}
                                            disabled={isLoadingSchedules || !selectedStaffForSchedule}
                                        >
                                            {isLoadingSchedules ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-2" />}
                                            {t('Reload Work Hours')}
                                        </Button>
                                        <Button
                                            onClick={() => void handleSaveStaffSchedules()}
                                            disabled={isSavingSchedules || isLoadingSchedules}
                                        >
                                            {isSavingSchedules ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                            {t('Save Work Hours')}
                                        </Button>
                                    </div>
                                </>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Time Off')}</CardTitle>
                            <CardDescription>{t('Create and manage time-off entries')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {timeOffError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {timeOffError}
                                </div>
                            ) : null}
                            {!selectedStaffForSchedule ? (
                                <p className="text-sm text-muted-foreground">{t('Select staff/resource to manage time off')}</p>
                            ) : (
                                <>
                                    <form className="grid grid-cols-1 md:grid-cols-2 gap-3" onSubmit={handleCreateTimeOff}>
                                        <div className="space-y-1.5">
                                            <Label>{t('Start Time')}</Label>
                                            <Input
                                                type="datetime-local"
                                                value={timeOffForm.starts_at}
                                                onChange={(event) => setTimeOffForm((current) => ({ ...current, starts_at: event.target.value }))}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>{t('End Time')}</Label>
                                            <Input
                                                type="datetime-local"
                                                value={timeOffForm.ends_at}
                                                onChange={(event) => setTimeOffForm((current) => ({ ...current, ends_at: event.target.value }))}
                                                required
                                            />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>{t('Status')}</Label>
                                            <Select
                                                value={timeOffForm.status}
                                                onValueChange={(value) => setTimeOffForm((current) => ({
                                                    ...current,
                                                    status: value as StaffTimeOffItem['status'],
                                                }))}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="approved">{t('Approved')}</SelectItem>
                                                    <SelectItem value="pending">{t('Pending')}</SelectItem>
                                                    <SelectItem value="rejected">{t('Rejected')}</SelectItem>
                                                    <SelectItem value="cancelled">{t('Cancelled')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label>{t('Reason')}</Label>
                                            <Input
                                                value={timeOffForm.reason}
                                                onChange={(event) => setTimeOffForm((current) => ({ ...current, reason: event.target.value }))}
                                            />
                                        </div>
                                        <div className="md:col-span-2 flex justify-end">
                                            <Button type="submit" disabled={isSavingTimeOff || isLoadingTimeOff}>
                                                {isSavingTimeOff ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                                {t('Create Time Off')}
                                            </Button>
                                        </div>
                                    </form>

                                    {staffTimeOffEntries.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">
                                            {isLoadingTimeOff ? t('Loading time-off entries...') : t('No time-off entries')}
                                        </p>
                                    ) : (
                                        <div className="space-y-2">
                                            {staffTimeOffEntries.map((entry) => (
                                                <div key={entry.id} className="rounded-md border p-3 space-y-2">
                                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                                        <Badge variant={entry.status === 'approved' ? 'secondary' : 'outline'}>
                                                            {entry.status}
                                                        </Badge>
                                                        <div className="flex flex-wrap gap-2">
                                                            {entry.status !== 'approved' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={isSavingTimeOff}
                                                                    onClick={() => void handleUpdateTimeOffStatus(entry, 'approved')}
                                                                >
                                                                    {t('Approve')}
                                                                </Button>
                                                            )}
                                                            {entry.status !== 'cancelled' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    disabled={isSavingTimeOff}
                                                                    onClick={() => void handleUpdateTimeOffStatus(entry, 'cancelled')}
                                                                >
                                                                    {t('Cancel')}
                                                                </Button>
                                                            )}
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                className="text-destructive"
                                                                disabled={isSavingTimeOff}
                                                                onClick={() => void handleDeleteTimeOff(entry)}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        {formatDateTime(entry.starts_at)} → {formatDateTime(entry.ends_at)}
                                                    </p>
                                                    {entry.reason && (
                                                        <p className="text-sm">{entry.reason}</p>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>
                    </Card>
                </TabsContent>

                <TabsContent value="finance" className="space-y-4 pt-4">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Finance Analytics')}</CardTitle>
                            <CardDescription>{t('Revenue, refunds and outstanding views by service, staff and channel')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {financeError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {financeError}
                                </div>
                            ) : null}
                            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-3">
                                <div className="space-y-1.5">
                                    <Label>{t('Date From')}</Label>
                                    <Input type="date" value={financeDateFrom} onChange={(event) => setFinanceDateFrom(event.target.value)} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>{t('Date To')}</Label>
                                    <Input type="date" value={financeDateTo} onChange={(event) => setFinanceDateTo(event.target.value)} />
                                </div>
                                <div className="space-y-1.5">
                                    <Label>{t('Service')}</Label>
                                    <Select value={financeServiceFilter} onValueChange={setFinanceServiceFilter}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All services')}</SelectItem>
                                            {services.map((service) => (
                                                <SelectItem key={service.id} value={String(service.id)}>{service.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>{t('Staff/Resource')}</Label>
                                    <Select value={financeStaffFilter} onValueChange={setFinanceStaffFilter}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All staff/resources')}</SelectItem>
                                            {staff.map((resource) => (
                                                <SelectItem key={resource.id} value={String(resource.id)}>{resource.name}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>{t('Channel')}</Label>
                                    <Select value={financeSourceFilter} onValueChange={setFinanceSourceFilter}>
                                        <SelectTrigger><SelectValue /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">{t('All channels')}</SelectItem>
                                            {financeSourceOptions.map((source) => (
                                                <SelectItem key={source} value={source}>{source}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5">
                                    <Label>{t('Top Rows')}</Label>
                                    <Input
                                        type="number"
                                        min={1}
                                        max={50}
                                        value={financeTop}
                                        onChange={(event) => setFinanceTop(event.target.value)}
                                    />
                                </div>
                            </div>
                            <div className="flex justify-end">
                                <Button variant="outline" onClick={() => void loadFinanceData()} disabled={isLoadingFinance}>
                                    {isLoadingFinance ? <Loader2 className="h-4 w-4 mr-1.5 animate-spin" /> : <RefreshCw className="h-4 w-4 mr-1.5" />}
                                    {t('Apply')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    {isLoadingFinance ? (
                        <Card>
                            <CardContent className="pt-6">
                                <div className="text-sm text-muted-foreground flex items-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    {t('Loading booking finance analytics...')}
                                </div>
                            </CardContent>
                        </Card>
                    ) : financeReport && financeReconciliation ? (
                        <>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-sm">{t('Bookings')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-2xl font-semibold">{financeReport.summary.bookings_count}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {t('Average')}: {financeReport.summary.average_booking_value}
                                        </p>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-sm">{t('Revenue vs Outstanding')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-1 text-sm">
                                        <div className="flex items-center justify-between"><span>{t('Revenue')}</span><span>{financeReport.summary.revenue_total}</span></div>
                                        <div className="flex items-center justify-between"><span>{t('Outstanding')}</span><span>{financeReport.summary.outstanding_total}</span></div>
                                        <div className="flex items-center justify-between"><span>{t('Discount')}</span><span>{financeReport.summary.discount_total}</span></div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-sm">{t('Collections')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-1 text-sm">
                                        <div className="flex items-center justify-between"><span>{t('Settled Payments')}</span><span>{financeReport.summary.settled_payments_total}</span></div>
                                        <div className="flex items-center justify-between"><span>{t('Refunds')}</span><span>{financeReport.summary.refunds_total}</span></div>
                                        <div className="flex items-center justify-between font-medium"><span>{t('Net Collected')}</span><span>{financeReport.summary.net_collected_total}</span></div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-sm">{t('Ledger Balance')}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-1 text-sm">
                                        <div className="flex items-center justify-between"><span>{t('Entries')}</span><span>{financeReconciliation.summary.entries_count}</span></div>
                                        <div className="flex items-center justify-between"><span>{t('Difference')}</span><span>{financeReconciliation.summary.difference}</span></div>
                                        <div className="pt-1">
                                            <Badge variant={financeReconciliation.summary.is_balanced ? 'default' : 'secondary'}>
                                                {financeReconciliation.summary.is_balanced ? t('Balanced') : t('Unbalanced')}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="grid gap-4 xl:grid-cols-3">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('By Service')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {financeReport.groups.services.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">{t('No service-level finance data')}</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {financeReport.groups.services.map((row) => (
                                                    <div key={row.key} className="rounded-md border p-2 text-xs space-y-1">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="font-medium truncate">{row.label}</span>
                                                            <span>{row.bookings_count}</span>
                                                        </div>
                                                        <div className="text-muted-foreground">{t('Revenue')}: {row.revenue_total}</div>
                                                        <div className="text-muted-foreground">{t('Outstanding')}: {row.outstanding_total}</div>
                                                        <div className="text-muted-foreground">{t('Refunds')}: {row.refunds_total}</div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('By Staff / Resource')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {financeReport.groups.staff.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">{t('No staff-level finance data')}</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {financeReport.groups.staff.map((row) => (
                                                    <div key={row.key} className="rounded-md border p-2 text-xs space-y-1">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="font-medium truncate">{row.label}</span>
                                                            <span>{row.bookings_count}</span>
                                                        </div>
                                                        <div className="text-muted-foreground">{t('Revenue')}: {row.revenue_total}</div>
                                                        <div className="text-muted-foreground">{t('Outstanding')}: {row.outstanding_total}</div>
                                                        <div className="text-muted-foreground">{t('Refunds')}: {row.refunds_total}</div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader>
                                        <CardTitle>{t('By Channel')}</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {financeReport.groups.channels.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">{t('No channel-level finance data')}</p>
                                        ) : (
                                            <div className="space-y-2">
                                                {financeReport.groups.channels.map((row) => (
                                                    <div key={row.key} className="rounded-md border p-2 text-xs space-y-1">
                                                        <div className="flex items-center justify-between gap-2">
                                                            <span className="font-medium truncate">{row.label}</span>
                                                            <span>{row.bookings_count}</span>
                                                        </div>
                                                        <div className="text-muted-foreground">{t('Revenue')}: {row.revenue_total}</div>
                                                        <div className="text-muted-foreground">{t('Outstanding')}: {row.outstanding_total}</div>
                                                        <div className="text-muted-foreground">{t('Refunds')}: {row.refunds_total}</div>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>

                            <Card>
                                <CardHeader>
                                    <CardTitle>{t('Reconciliation')}</CardTitle>
                                    <CardDescription>{t('Ledger alignment with outstanding totals and account balances')}</CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4 text-sm">
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Bookings Outstanding')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.bookings_outstanding_total}</p>
                                        </div>
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Invoices Outstanding')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.invoices_outstanding_total}</p>
                                        </div>
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Accounts Receivable Net')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.accounts_receivable_net}</p>
                                        </div>
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Outstanding Gap')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.outstanding_gap}</p>
                                        </div>
                                    </div>

                                    <div className="grid gap-3 md:grid-cols-3 text-sm">
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Settled Payments')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.settled_payments_total}</p>
                                        </div>
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Settled Refunds')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.settled_refunds_total}</p>
                                        </div>
                                        <div className="rounded-md border p-2 space-y-1">
                                            <p className="text-xs text-muted-foreground">{t('Uninvoiced Revenue')}</p>
                                            <p className="font-medium">{financeReconciliation.summary.uninvoiced_revenue_total}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('Bookings')}: {financeReconciliation.summary.uninvoiced_bookings_count}
                                            </p>
                                        </div>
                                    </div>

                                    {financeReconciliation.accounts.length === 0 ? (
                                        <p className="text-sm text-muted-foreground">{t('No ledger accounts in selected scope')}</p>
                                    ) : (
                                        <div className="space-y-2">
                                            {financeReconciliation.accounts.map((account) => (
                                                <div key={account.account_code} className="rounded-md border p-2 text-xs">
                                                    <div className="flex items-center justify-between gap-2">
                                                        <span className="font-medium">{account.account_code}</span>
                                                        <span>{account.net}</span>
                                                    </div>
                                                    <p className="text-muted-foreground">{account.account_name}</p>
                                                    <p className="text-muted-foreground">
                                                        {t('Debit')}: {account.debit_total}
                                                        {' · '}
                                                        {t('Credit')}: {account.credit_total}
                                                    </p>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </>
                    ) : (
                        <Card>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">{t('No finance analytics found for selected scope')}</p>
                            </CardContent>
                        </Card>
                    )}
                </TabsContent>
            </Tabs>

            <Sheet
                open={isServiceSheetOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeServiceSheet();
                        return;
                    }

                    setIsServiceSheetOpen(true);
                }}
            >
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-2xl">
                    <SheetHeader className="border-b">
                        <SheetTitle>
                            {editingServiceId === null ? t('Add Service') : t('Edit Service')}
                        </SheetTitle>
                        <SheetDescription>
                            {editingServiceId === null
                                ? t('Create a new booking service with pricing and duration settings')
                                : t('Update service details, pricing and scheduling options')}
                        </SheetDescription>
                    </SheetHeader>

                    <form className="flex min-h-0 flex-1 flex-col" onSubmit={handleSubmitService}>
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                            {servicesError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {servicesError}
                                </div>
                            ) : null}

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('General')}</CardTitle>
                                    <CardDescription>{t('Basic service identity, status and schedule capacity')}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label>{t('Name')}</Label>
                                        <Input
                                            value={serviceForm.name}
                                            onChange={(event) => {
                                                const value = event.target.value;
                                                setServiceForm((current) => ({
                                                    ...current,
                                                    name: value,
                                                    slug: slugify(value),
                                                }));
                                            }}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Slug')}</Label>
                                        <Input
                                            value={serviceForm.slug}
                                            onChange={(event) => setServiceForm((current) => ({ ...current, slug: slugify(event.target.value) }))}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Status')}</Label>
                                        <Select value={serviceForm.status} onValueChange={(value) => setServiceForm((current) => ({ ...current, status: value as ServiceFormState['status'] }))}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">{t('Active')}</SelectItem>
                                                <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Duration (minutes)')}</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={serviceForm.duration_minutes}
                                            onChange={(event) => setServiceForm((current) => ({ ...current, duration_minutes: event.target.value }))}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Max parallel bookings')}</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={serviceForm.max_parallel_bookings}
                                            onChange={(event) => setServiceForm((current) => ({ ...current, max_parallel_bookings: event.target.value }))}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Requires Staff')}</Label>
                                        <Select value={serviceForm.requires_staff} onValueChange={(value) => setServiceForm((current) => ({ ...current, requires_staff: value as ServiceFormState['requires_staff'] }))}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="true">{t('Yes')}</SelectItem>
                                                <SelectItem value="false">{t('No')}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Pricing')}</CardTitle>
                                    <CardDescription>{t('Configure amount and currency')}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label>{t('Price')}</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min={0}
                                            value={serviceForm.price}
                                            onChange={(event) => setServiceForm((current) => ({ ...current, price: event.target.value }))}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Currency')}</Label>
                                        <Input
                                            value={serviceForm.currency}
                                            onChange={(event) => setServiceForm((current) => ({ ...current, currency: event.target.value.toUpperCase() }))}
                                            maxLength={3}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Description')}</CardTitle>
                                    <CardDescription>{t('Optional details shown in booking flow')}</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <RichTextField
                                        value={serviceForm.description}
                                        onChange={(nextValue) => setServiceForm((current) => ({ ...current, description: nextValue }))}
                                        minHeightClassName="min-h-[160px]"
                                    />
                                </CardContent>
                            </Card>
                        </div>

                        <SheetFooter className="border-t bg-background sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                {editingServiceId !== null ? (
                                    <Button type="button" variant="ghost" onClick={resetServiceForm}>
                                        {t('Reset')}
                                    </Button>
                                ) : <span />}
                            </div>
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <Button type="button" variant="outline" onClick={closeServiceSheet}>
                                    {t('Close')}
                                </Button>
                                <Button type="submit" disabled={isSubmittingService}>
                                    {isSubmittingService ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                    {editingServiceId === null ? t('Create Service') : t('Update Service')}
                                </Button>
                            </div>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>

            <Sheet
                open={isStaffSheetOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeStaffSheet();
                        return;
                    }

                    setIsStaffSheetOpen(true);
                }}
            >
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-xl">
                    <SheetHeader className="border-b">
                        <SheetTitle>
                            {editingStaffId === null ? t('Add Team Member / Resource') : t('Edit Team Member / Resource')}
                        </SheetTitle>
                        <SheetDescription>
                            {editingStaffId === null
                                ? t('Create a new staff member or bookable resource')
                                : t('Update member details, timezone and capacity')}
                        </SheetDescription>
                    </SheetHeader>

                    <form className="flex min-h-0 flex-1 flex-col" onSubmit={handleSubmitStaff}>
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                            {staffError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {staffError}
                                </div>
                            ) : null}

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Profile')}</CardTitle>
                                    <CardDescription>{t('Basic identity and availability settings')}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label>{t('Name')}</Label>
                                        <Input
                                            value={staffForm.name}
                                            onChange={(event) => {
                                                const value = event.target.value;
                                                setStaffForm((current) => ({
                                                    ...current,
                                                    name: value,
                                                    slug: slugify(value),
                                                }));
                                            }}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Slug')}</Label>
                                        <Input
                                            value={staffForm.slug}
                                            onChange={(event) => setStaffForm((current) => ({ ...current, slug: slugify(event.target.value) }))}
                                            required
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Type')}</Label>
                                        <Select value={staffForm.type} onValueChange={(value) => setStaffForm((current) => ({ ...current, type: value as StaffFormState['type'] }))}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="staff">{t('Staff')}</SelectItem>
                                                <SelectItem value="resource">{t('Resource')}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Status')}</Label>
                                        <Select value={staffForm.status} onValueChange={(value) => setStaffForm((current) => ({ ...current, status: value as StaffFormState['status'] }))}>
                                            <SelectTrigger><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">{t('Active')}</SelectItem>
                                                <SelectItem value="inactive">{t('Inactive')}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Timezone')}</Label>
                                        <Input
                                            value={staffForm.timezone}
                                            onChange={(event) => setStaffForm((current) => ({ ...current, timezone: event.target.value }))}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Max parallel bookings')}</Label>
                                        <Input
                                            type="number"
                                            min={1}
                                            value={staffForm.max_parallel_bookings}
                                            onChange={(event) => setStaffForm((current) => ({ ...current, max_parallel_bookings: event.target.value }))}
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="text-sm">{t('Contact')}</CardTitle>
                                    <CardDescription>{t('Optional contact details')}</CardDescription>
                                </CardHeader>
                                <CardContent className="grid grid-cols-1 gap-3">
                                    <div className="space-y-1.5">
                                        <Label>{t('Email')}</Label>
                                        <Input
                                            type="email"
                                            value={staffForm.email}
                                            onChange={(event) => setStaffForm((current) => ({ ...current, email: event.target.value }))}
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label>{t('Phone')}</Label>
                                        <Input
                                            value={staffForm.phone}
                                            onChange={(event) => setStaffForm((current) => ({ ...current, phone: event.target.value }))}
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        <SheetFooter className="border-t bg-background sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                {editingStaffId !== null ? (
                                    <Button type="button" variant="ghost" onClick={resetStaffForm}>
                                        {t('Reset')}
                                    </Button>
                                ) : <span />}
                            </div>
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <Button type="button" variant="outline" onClick={closeStaffSheet}>
                                    {t('Close')}
                                </Button>
                                <Button type="submit" disabled={isSubmittingStaff}>
                                    {isSubmittingStaff ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Save className="h-4 w-4 mr-2" />}
                                    {editingStaffId === null ? t('Create Staff/Resource') : t('Save Staff/Resource')}
                                </Button>
                            </div>
                        </SheetFooter>
                    </form>
                </SheetContent>
            </Sheet>

            <Sheet
                open={isInboxBookingDetailsSheetOpen}
                onOpenChange={(open) => {
                    setIsInboxBookingDetailsSheetOpen(open);
                    if (!open) {
                        setInboxBookingDetailsError(null);
                        setIsLoadingInboxBookingDetails(false);
                    }
                }}
            >
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-xl">
                    <SheetHeader className="border-b">
                        <SheetTitle>{t('Booking Details')}</SheetTitle>
                        <SheetDescription>{t('Open any booking from the list to view details')}</SheetDescription>
                    </SheetHeader>

                    <div className="flex min-h-0 flex-1 flex-col">
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                            {isLoadingInboxBookingDetails ? (
                                <div className="rounded-md border border-dashed p-6 text-sm text-muted-foreground flex items-center justify-center gap-2">
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    <span>{t('Loading booking details...')}</span>
                                </div>
                            ) : null}

                            {inboxBookingDetailsError ? (
                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                    {inboxBookingDetailsError}
                                </div>
                            ) : null}

                            {!isLoadingInboxBookingDetails && !inboxBookingDetailsError && selectedInboxBookingDisplay ? (
                                <>
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <CardTitle className="text-sm">{selectedInboxBookingDisplay.booking_number}</CardTitle>
                                                    <CardDescription>{t('Inbox booking')}</CardDescription>
                                                </div>
                                                <Badge variant={statusVariant(selectedInboxBookingDisplay.status)}>
                                                    {t(selectedInboxBookingDisplay.status)}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Customer')}</p>
                                                    <p className="mt-1 font-medium">{selectedInboxBookingDisplay.customer_name ?? t('Guest')}</p>
                                                    {selectedInboxBookingDisplay.customer_email ? (
                                                        <p className="mt-1 text-xs text-muted-foreground">{selectedInboxBookingDisplay.customer_email}</p>
                                                    ) : null}
                                                    {selectedInboxBookingDisplay.customer_phone ? (
                                                        <p className="text-xs text-muted-foreground">{selectedInboxBookingDisplay.customer_phone}</p>
                                                    ) : null}
                                                </div>
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <div className="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <Clock3 className="h-3.5 w-3.5" />
                                                        <span>{t('Time')}</span>
                                                    </div>
                                                    <p className="font-medium">
                                                        {formatDateTime(selectedInboxBookingDisplay.starts_at, locale)}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        {formatDateTime(selectedInboxBookingDisplay.ends_at, locale)} · {selectedInboxBookingDisplay.duration_minutes} {t('min')}
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Service')}</p>
                                                    <p className="mt-1 font-medium">{selectedInboxBookingDisplay.service.name ?? '—'}</p>
                                                </div>
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Staff')}</p>
                                                    <p className="mt-1 font-medium">{selectedInboxBookingDisplay.staff_resource.name ?? t('Unassigned')}</p>
                                                </div>
                                            </div>

                                            {selectedInboxBookingDetails?.customer_notes ? (
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Customer Notes')}</p>
                                                    <p className="mt-1 whitespace-pre-wrap">{selectedInboxBookingDetails.customer_notes}</p>
                                                </div>
                                            ) : null}

                                            {selectedInboxBookingDetails?.internal_notes ? (
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Internal Notes')}</p>
                                                    <p className="mt-1 whitespace-pre-wrap">{selectedInboxBookingDetails.internal_notes}</p>
                                                </div>
                                            ) : null}
                                        </CardContent>
                                    </Card>

                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm">{t('Quick Actions')}</CardTitle>
                                            <CardDescription>{t('Update status or reschedule without opening editor')}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={updatingBookingId === selectedInboxBookingDisplay.id}
                                                    onClick={() => void handleStatusUpdate(selectedInboxBookingDisplay.id, 'confirmed')}
                                                >
                                                    <CheckCircle2 className="mr-1.5 h-4 w-4" />
                                                    {t('Confirm')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={updatingBookingId === selectedInboxBookingDisplay.id}
                                                    onClick={() => void handleStatusUpdate(selectedInboxBookingDisplay.id, 'in_progress')}
                                                >
                                                    {t('Start')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={updatingBookingId === selectedInboxBookingDisplay.id}
                                                    onClick={() => void handleStatusUpdate(selectedInboxBookingDisplay.id, 'completed')}
                                                >
                                                    {t('Complete')}
                                                </Button>
                                            </div>

                                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-[1fr_auto]">
                                                <Input
                                                    type="datetime-local"
                                                    value={rescheduleDrafts[selectedInboxBookingDisplay.id] ?? ''}
                                                    onChange={(event) => {
                                                        const value = event.target.value;
                                                        setRescheduleDrafts((current) => ({ ...current, [selectedInboxBookingDisplay.id]: value }));
                                                    }}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    disabled={updatingBookingId === selectedInboxBookingDisplay.id}
                                                    onClick={() => void handleRescheduleBooking(selectedInboxBookingDisplay.id)}
                                                >
                                                    {t('Reschedule')}
                                                </Button>
                                            </div>
                                        </CardContent>
                                    </Card>

                                    {selectedInboxBookingDetails ? (
                                        <Card>
                                            <CardHeader className="pb-3">
                                                <CardTitle className="text-sm">{t('Activity')}</CardTitle>
                                                <CardDescription>{t('Recent booking events')}</CardDescription>
                                            </CardHeader>
                                            <CardContent className="space-y-2">
                                                {selectedInboxBookingDetails.events.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground">{t('No events yet')}</p>
                                                ) : (
                                                    selectedInboxBookingDetails.events.slice(0, 6).map((eventItem) => (
                                                        <div key={`inbox-booking-event-${eventItem.id}`} className="rounded-md border bg-muted/10 p-2 text-xs">
                                                            <p className="font-medium">{eventItem.event_key ?? eventItem.event_type}</p>
                                                            <p className="text-muted-foreground">{formatDateTime(eventItem.occurred_at, locale)}</p>
                                                        </div>
                                                    ))
                                                )}
                                            </CardContent>
                                        </Card>
                                    ) : null}
                                </>
                            ) : null}

                            {!isLoadingInboxBookingDetails && !inboxBookingDetailsError && !selectedInboxBookingDisplay ? (
                                <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    {t('Select a booking from the list')}
                                </div>
                            ) : null}
                        </div>

                        <SheetFooter className="border-t bg-background sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                {selectedInboxBookingDisplay ? (
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        disabled={updatingBookingId === selectedInboxBookingDisplay.id}
                                        onClick={() => void handleDeleteBookingFromInbox(selectedInboxBookingDisplay.id)}
                                    >
                                        {updatingBookingId === selectedInboxBookingDisplay.id ? (
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        ) : (
                                            <Trash2 className="mr-2 h-4 w-4" />
                                        )}
                                        {t('Cancel Booking')}
                                    </Button>
                                ) : <span />}
                            </div>
                            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                <Button type="button" variant="outline" onClick={() => setIsInboxBookingDetailsSheetOpen(false)}>
                                    {t('Close')}
                                </Button>
                                {selectedInboxBookingDisplay ? (
                                    <Button
                                        type="button"
                                        onClick={() => {
                                            setIsInboxBookingDetailsSheetOpen(false);
                                            void openEditBookingDrawer(selectedInboxBookingDisplay.id);
                                        }}
                                    >
                                        <Pencil className="mr-2 h-4 w-4" />
                                        {t('Edit Booking')}
                                    </Button>
                                ) : null}
                            </div>
                        </SheetFooter>
                    </div>
                </SheetContent>
            </Sheet>

            <Sheet
                open={isCalendarDetailsSheetOpen}
                onOpenChange={(open) => setIsCalendarDetailsSheetOpen(open)}
            >
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-lg">
                    <SheetHeader className="border-b">
                        <SheetTitle>{t('Booking Details')}</SheetTitle>
                        <SheetDescription>{t('Details for the selected booking from the calendar')}</SheetDescription>
                    </SheetHeader>

                    <div className="flex min-h-0 flex-1 flex-col">
                        <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                            {selectedCalendarEvent ? (
                                <>
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <div className="flex items-start justify-between gap-2">
                                                <div>
                                                    <CardTitle className="text-sm">{selectedCalendarEvent.booking_number}</CardTitle>
                                                    <CardDescription>{t('Calendar booking')}</CardDescription>
                                                </div>
                                                <Badge variant={statusVariant(selectedCalendarEvent.status)}>
                                                    {t(selectedCalendarEvent.status)}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-3">
                                            <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                <p className="text-xs text-muted-foreground">{t('Customer')}</p>
                                                <p className="mt-1 font-medium">{selectedCalendarEvent.customer_name ?? t('Guest')}</p>
                                            </div>

                                            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Service')}</p>
                                                    <p className="mt-1 font-medium">{selectedCalendarEvent.service.name ?? '—'}</p>
                                                </div>
                                                <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                    <p className="text-xs text-muted-foreground">{t('Staff')}</p>
                                                    <p className="mt-1 font-medium">{selectedCalendarEvent.staff_resource.name ?? t('Unassigned')}</p>
                                                </div>
                                            </div>

                                            <div className="rounded-md border bg-muted/20 p-3 text-sm">
                                                <div className="mb-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                                                    <Clock3 className="h-3.5 w-3.5" />
                                                    <span>{t('Time')}</span>
                                                </div>
                                                <p className="font-medium">
                                                    {formatDateTime(selectedCalendarEvent.starts_at, locale)} → {formatDateTime(selectedCalendarEvent.ends_at, locale)}
                                                </p>
                                            </div>
                                        </CardContent>
                                    </Card>
                                </>
                            ) : (
                                <div className="rounded-md border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    {t('Select a booking from the calendar')}
                                </div>
                            )}
                        </div>

                        <SheetFooter className="border-t bg-background sm:flex-row sm:items-center sm:justify-end">
                            <Button type="button" variant="outline" onClick={() => setIsCalendarDetailsSheetOpen(false)}>
                                {t('Close')}
                            </Button>
                            {selectedCalendarEvent ? (
                                <Button
                                    type="button"
                                    onClick={() => {
                                        setIsCalendarDetailsSheetOpen(false);
                                        void openEditBookingDrawer(selectedCalendarEvent.id);
                                    }}
                                >
                                    <Wrench className="mr-2 h-4 w-4" />
                                    {t('Edit Booking')}
                                </Button>
                            ) : null}
                        </SheetFooter>
                    </div>
                </SheetContent>
            </Sheet>

            <Sheet
                open={isBookingDrawerOpen}
                onOpenChange={(open) => {
                    setIsBookingDrawerOpen(open);
                    if (!open) {
                        setBookingDrawerError(null);
                        setIsLoadingBookingDrawer(false);
                        setBookingCustomerSearchResults([]);
                        setBookingCustomerSearchError(null);
                        setIsLoadingBookingCustomerSearch(false);
                    }
                }}
            >
                <SheetContent side="right" className="w-full gap-0 p-0 sm:max-w-2xl">
                    <SheetHeader className="border-b">
                        <SheetTitle>
                            {bookingDrawerMode === 'create' ? t('Add Booking') : t('Edit Booking')}
                        </SheetTitle>
                        <SheetDescription>
                            {bookingDrawerMode === 'create'
                                ? t('Create a booking from the admin panel')
                                : t('Update booking schedule and status')}
                        </SheetDescription>
                    </SheetHeader>

                    {isLoadingBookingDrawer ? (
                        <div className="flex flex-1 items-center justify-center p-6">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Loader2 className="h-4 w-4 animate-spin" />
                                <span>{t('Loading booking details...')}</span>
                            </div>
                        </div>
                    ) : (
                        <form className="flex min-h-0 flex-1 flex-col" onSubmit={handleSubmitBookingDrawer}>
                            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                                {bookingDrawerError ? (
                                    <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                        {bookingDrawerError}
                                    </div>
                                ) : null}

                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-sm">{t('Customer')}</CardTitle>
                                        <CardDescription>
                                            {bookingDrawerMode === 'create'
                                                ? t('Search registered customer first, or enter details to auto-register on save')
                                                : t('Search registered user or update customer information')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="space-y-3">
                                        <div className="space-y-1.5">
                                            <Label>{t('Search Customer')}</Label>
                                            <Input
                                                value={bookingCustomerSearchInput}
                                                onChange={(event) => {
                                                    const value = event.target.value;
                                                    setBookingCustomerSearchInput(value);
                                                    if (value.trim() === '') {
                                                        setSelectedBookingCustomerUser(null);
                                                        setBookingCustomerSearchResults([]);
                                                    }
                                                }}
                                                placeholder={t('Search by name or email')}
                                            />
                                            <div className="flex flex-wrap items-center gap-2 text-xs">
                                                {selectedBookingCustomerUser ? (
                                                    <>
                                                        <Badge variant="secondary">{t('Registered user selected')}</Badge>
                                                        <span className="text-muted-foreground">
                                                            {selectedBookingCustomerUser.name} · {selectedBookingCustomerUser.email}
                                                        </span>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-6 px-2"
                                                            onClick={() => setSelectedBookingCustomerUser(null)}
                                                        >
                                                            {t('Clear')}
                                                        </Button>
                                                    </>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {bookingDrawerMode === 'create'
                                                            ? t('If user is not registered, booking save will create an account from name + email.')
                                                            : t('Manual customer fields can be edited below.')}
                                                    </span>
                                                )}
                                            </div>

                                            {bookingCustomerSearchError ? (
                                                <div className="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2 text-xs text-destructive">
                                                    {bookingCustomerSearchError}
                                                </div>
                                            ) : null}

                                            {bookingCustomerSearchInput.trim().length >= 2 ? (
                                                <div className="rounded-md border bg-background">
                                                    {isLoadingBookingCustomerSearch ? (
                                                        <div className="px-3 py-2 text-xs text-muted-foreground flex items-center gap-2">
                                                            <Loader2 className="h-3.5 w-3.5 animate-spin" />
                                                            <span>{t('Searching customers...')}</span>
                                                        </div>
                                                    ) : bookingCustomerSearchResults.length === 0 ? (
                                                        <div className="px-3 py-2 text-xs text-muted-foreground">
                                                            {t('No registered customer found')}
                                                        </div>
                                                    ) : (
                                                        <div className="max-h-44 overflow-y-auto p-1">
                                                            {bookingCustomerSearchResults.map((user) => (
                                                                <button
                                                                    key={`booking-customer-search-${user.id}`}
                                                                    type="button"
                                                                    className="flex w-full items-start justify-between gap-3 rounded-md px-2 py-2 text-left hover:bg-muted/40"
                                                                    onClick={() => {
                                                                        setSelectedBookingCustomerUser(user);
                                                                        setBookingCustomerSearchInput(user.email || user.name);
                                                                        setBookingCustomerSearchResults([]);
                                                                        setBookingForm((current) => ({
                                                                            ...current,
                                                                            customer_name: user.name ?? current.customer_name,
                                                                            customer_email: user.email ?? current.customer_email,
                                                                        }));
                                                                    }}
                                                                >
                                                                    <div className="min-w-0">
                                                                        <p className="truncate text-sm font-medium">{user.name}</p>
                                                                        <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                                                                    </div>
                                                                    <UserRound className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                                                </button>
                                                            ))}
                                                        </div>
                                                    )}
                                                </div>
                                            ) : null}
                                        </div>

                                        <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            <div className="space-y-1.5">
                                                <Label>{t('Customer Name')}</Label>
                                                <Input
                                                    value={bookingForm.customer_name}
                                                    onChange={(event) => {
                                                        const value = event.target.value;
                                                        setBookingForm((current) => ({ ...current, customer_name: value }));
                                                        if (selectedBookingCustomerUser && value.trim() !== selectedBookingCustomerUser.name.trim()) {
                                                            setSelectedBookingCustomerUser(null);
                                                        }
                                                    }}
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label>{t('Customer Email')}</Label>
                                                <Input
                                                    type="email"
                                                    value={bookingForm.customer_email}
                                                    onChange={(event) => {
                                                        const value = event.target.value;
                                                        setBookingForm((current) => ({ ...current, customer_email: value }));
                                                        if (
                                                            selectedBookingCustomerUser
                                                            && value.trim().toLowerCase() !== selectedBookingCustomerUser.email.trim().toLowerCase()
                                                        ) {
                                                            setSelectedBookingCustomerUser(null);
                                                        }
                                                    }}
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label>{t('Customer Phone')}</Label>
                                                <Input
                                                    value={bookingForm.customer_phone}
                                                    onChange={(event) => setBookingForm((current) => ({ ...current, customer_phone: event.target.value }))}
                                                />
                                            </div>
                                            <div className="space-y-1.5 md:col-span-2">
                                                <Label>{t('Notes')}</Label>
                                                <Textarea
                                                    rows={3}
                                                    value={bookingForm.customer_notes}
                                                    onChange={(event) => setBookingForm((current) => ({ ...current, customer_notes: event.target.value }))}
                                                />
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-sm">{t('Booking Details')}</CardTitle>
                                        <CardDescription>{t('Service, staff and time')}</CardDescription>
                                    </CardHeader>
                                    <CardContent className="grid grid-cols-1 gap-3 md:grid-cols-2">
                                        <div className="space-y-1.5">
                                            <Label>{t('Service')}</Label>
                                            <Select
                                                value={bookingForm.service_id || '__none__'}
                                                onValueChange={(value) => {
                                                    const nextValue = value === '__none__' ? '' : value;
                                                    const selectedService = services.find((service) => String(service.id) === nextValue);
                                                    setBookingForm((current) => ({
                                                        ...current,
                                                        service_id: nextValue,
                                                        duration_minutes: selectedService
                                                            ? String(selectedService.duration_minutes || Number(current.duration_minutes) || 60)
                                                            : current.duration_minutes,
                                                    }));
                                                }}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('Select service')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none__">{t('Select')}</SelectItem>
                                                    {services.map((service) => (
                                                        <SelectItem key={`drawer-service-${service.id}`} value={String(service.id)}>
                                                            {service.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label>{t('Staff/Resource')}</Label>
                                            <Select
                                                value={bookingForm.staff_resource_id || '__none__'}
                                                onValueChange={(value) => setBookingForm((current) => ({
                                                    ...current,
                                                    staff_resource_id: value === '__none__' ? '' : value,
                                                }))}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder={t('Optional')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="__none__">{t('No specific staff')}</SelectItem>
                                                    {staff.map((resource) => (
                                                        <SelectItem key={`drawer-staff-${resource.id}`} value={String(resource.id)}>
                                                            {resource.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label>{t('Start Time')}</Label>
                                            <Input
                                                type="datetime-local"
                                                value={bookingForm.starts_at}
                                                onChange={(event) => setBookingForm((current) => ({ ...current, starts_at: event.target.value }))}
                                                required
                                            />
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label>{t('Duration (minutes)')}</Label>
                                            <Input
                                                type="number"
                                                min={1}
                                                value={bookingForm.duration_minutes}
                                                onChange={(event) => setBookingForm((current) => ({ ...current, duration_minutes: event.target.value }))}
                                                required
                                            />
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label>{t('Status')}</Label>
                                            <Select
                                                value={bookingDrawerStatus}
                                                onValueChange={(value) => setBookingDrawerStatus(value as BookingSummaryItem['status'])}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="pending">{t('Pending')}</SelectItem>
                                                    <SelectItem value="confirmed">{t('Confirmed')}</SelectItem>
                                                    <SelectItem value="in_progress">{t('In Progress')}</SelectItem>
                                                    <SelectItem value="completed">{t('Completed')}</SelectItem>
                                                    <SelectItem value="cancelled">{t('Cancelled')}</SelectItem>
                                                    <SelectItem value="no_show">{t('No Show')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        {bookingDrawerMode === 'edit' && bookingDrawerDetails ? (
                                            <div className="space-y-1.5">
                                                <Label>{t('Booking Number')}</Label>
                                                <Input value={bookingDrawerDetails.booking_number} readOnly />
                                            </div>
                                        ) : null}
                                    </CardContent>
                                </Card>

                                {bookingDrawerMode === 'edit' && bookingDrawerDetails ? (
                                    <Card>
                                        <CardHeader className="pb-3">
                                            <CardTitle className="text-sm">{t('Activity')}</CardTitle>
                                            <CardDescription>{t('Latest booking events')}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="space-y-2">
                                            {bookingDrawerDetails.events.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">{t('No events yet')}</p>
                                            ) : (
                                                bookingDrawerDetails.events.slice(0, 5).map((eventItem) => (
                                                    <div key={`booking-event-${eventItem.id}`} className="rounded-md border p-2 text-xs">
                                                        <p className="font-medium">{eventItem.event_key ?? eventItem.event_type}</p>
                                                        <p className="text-muted-foreground">{formatDateTime(eventItem.occurred_at, locale)}</p>
                                                    </div>
                                                ))
                                            )}
                                        </CardContent>
                                    </Card>
                                ) : null}
                            </div>

                            <SheetFooter className="border-t bg-background sm:flex-row sm:items-center sm:justify-between">
                                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                    {bookingDrawerMode === 'edit' && bookingDrawerBookingId ? (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            disabled={updatingBookingId === bookingDrawerBookingId}
                                            onClick={async () => {
                                                await handleCancelBooking(bookingDrawerBookingId);
                                                setIsBookingDrawerOpen(false);
                                            }}
                                        >
                                            {updatingBookingId === bookingDrawerBookingId ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                                            {t('Cancel Booking')}
                                        </Button>
                                    ) : <span />}
                                </div>
                                <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                                    <Button type="button" variant="outline" onClick={() => setIsBookingDrawerOpen(false)}>
                                        {t('Close')}
                                    </Button>
                                    <Button type="submit" disabled={isSubmittingBooking || isLoadingBookingDrawer || services.length === 0}>
                                        {isSubmittingBooking ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Save className="mr-2 h-4 w-4" />}
                                        {bookingDrawerMode === 'create' ? t('Create Booking') : t('Save Booking')}
                                    </Button>
                                </div>
                            </SheetFooter>
                        </form>
                    )}
                </SheetContent>
            </Sheet>
        </div>
    );
}
