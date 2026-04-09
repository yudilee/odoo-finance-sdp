<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceOther extends Model
{
    protected $fillable = [
        'odoo_id',
        'name',
        'partner_name',
        'invoice_date',
        'invoice_date_due',
        'payment_term',
        'ref',
        'contract_ref',
        'journal_name',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
        'partner_bank',
        'manager_name',
        'spv_name',
        'partner_address',
        'partner_address_complete',
        'narration',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'invoice_date_due' => 'date',
        'amount_untaxed' => 'decimal:2',
        'amount_tax' => 'decimal:2',
        'amount_total' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(InvoiceOtherLine::class);
    }

    /**
     * Check if this invoice has tax (INVOT prefix)
     */
    public function hasTax(): bool
    {
        return str_starts_with($this->name, 'INVOT');
    }
}
