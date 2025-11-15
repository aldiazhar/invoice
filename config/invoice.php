<?php

return [
    'tables' => [
        'invoices' => 'invoices',
        'invoice_items' => 'invoice_items',
        'invoice_payments' => 'invoice_payments',
        'invoice_activities' => 'invoice_activities',
    ],

    'statuses' => [
        'pending' => 'pending',
        'paid' => 'paid',
        'cancelled' => 'cancelled',
        'failed' => 'failed',
        'refunded' => 'refunded',
        'overdue' => 'overdue',
    ],

    'currency' => env('INVOICE_CURRENCY', 'USD'),

    'due_date_days' => 30,

    'invoice_number' => [
        'prefix' => env('INVOICE_NUMBER_PREFIX', 'INV-'),
        'format' => 'Ymd',
        'padding' => 4,
    ],

    'strict_validation' => true,

    'callbacks' => [
        'enabled' => true,
    ],

    'activity_log' => [
        'enabled' => true,
    ],

    'routes' => [
        'enabled' => false,
        'prefix' => 'invoices',
        'middleware' => ['web', 'auth'],
    ],
];