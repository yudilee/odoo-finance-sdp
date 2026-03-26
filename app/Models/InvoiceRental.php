<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvoiceRental extends Model
{
    protected $fillable = [
        'name',
        'partner_name',
        'invoice_date',
        'payment_term',
        'ref',
        'journal_name',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
        'partner_bank',
        'bc_manager',
        'bc_spv',
        'partner_address',
        'partner_address_complete',
        'print_count'
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'amount_untaxed' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'amount_total' => 'decimal:2',
        'print_count' => 'integer',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceRentalLine::class);
    }
}
