<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Table Names
    |--------------------------------------------------------------------------
    |
    | Configure the table names used by the invoice package.
    |
    */
    'tables' => [
        'invoices' => 'invoices',
        'invoice_items' => 'invoice_items',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Statuses
    |--------------------------------------------------------------------------
    |
    | Define the available invoice statuses for your application.
    |
    */
    'statuses' => [
        'pending' => 'pending',
        'paid' => 'paid',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        'refunded' => 'refunded',
        'overdue' => 'overdue',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | The default currency code for invoices (ISO 4217 format).
    |
    */
    'currency' => env('INVOICE_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Invoice Number Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how invoice numbers are generated automatically.
    |
    */
    'invoice_number' => [
        'prefix' => env('INVOICE_PREFIX', 'INV-'),
        'format' => 'Ymd', // Date format for invoice number
        'padding' => 4, // Number padding (e.g., 0001)
    ],

    /*
    |--------------------------------------------------------------------------
    | Due Date Configuration
    |--------------------------------------------------------------------------
    |
    | Default number of days until invoice is due from creation date.
    |
    */
    'due_date_days' => env('INVOICE_DUE_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Routes Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the invoice management routes.
    |
    */
    'routes' => [
        'enabled' => true,
        'prefix' => 'invoices',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the built-in user interface options.
    |
    */
    'ui' => [
        'enabled' => true,
        'per_page' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how callbacks are executed after invoice payment.
    |
    */
    'callbacks' => [
        'enabled' => true,
        'queue' => false, // Queue callbacks for async execution
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Configuration
    |--------------------------------------------------------------------------
    |
    | Configure default tax settings for invoices.
    |
    */
    'tax' => [
        'enabled' => true,
        'default_rate' => 0, // Default tax rate (0 = no tax)
        'inclusive' => false, // Tax included in price or added separately
    ],
];