<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditNote extends Model
{
    protected $fillable = [
        'name',
        'partner_name',
        'ref',
        'invoice_date',
        'invoice_date_due',
        'payment_date',
        'description',
        'tax_number',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
        'payment_state',
        'state',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'invoice_date_due' => 'date',
        'payment_date' => 'date',
        'amount_untaxed' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'amount_total' => 'decimal:2',
    ];
}
