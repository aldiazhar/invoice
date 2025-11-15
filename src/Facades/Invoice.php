<?php

namespace Aldiazhar\Invoice\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aldiazhar\Invoice\Builders\InvoiceBuilder create($payer, $invoiceable)
 * @method static \Aldiazhar\Invoice\Models\Invoice find($id)
 * @method static \Illuminate\Database\Eloquent\Collection pending()
 * @method static \Illuminate\Database\Eloquent\Collection paid()
 * @method static \Illuminate\Database\Eloquent\Collection overdue()
 * 
 * @see \Aldiazhar\Invoice\InvoiceManager
 */
class Invoice extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'invoice';
    }
}