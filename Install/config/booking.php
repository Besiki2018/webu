<?php

return [
    'notifications' => [
        'queue' => env('BOOKING_NOTIFICATIONS_QUEUE', 'default'),
    ],

    'reminders' => [
        'minutes_before_start' => (int) env('BOOKING_REMINDER_MINUTES_BEFORE_START', 120),
        'dispatch_window_minutes' => (int) env('BOOKING_REMINDER_DISPATCH_WINDOW_MINUTES', 10),
        'batch_limit' => (int) env('BOOKING_REMINDER_BATCH_LIMIT', 500),
    ],
];
