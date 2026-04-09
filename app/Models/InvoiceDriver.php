<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDriver extends Model
{
    protected $fillable = [
        'odoo_id',
        'name',
        'partner_name',
        'invoice_date',
        'invoice_date_due',
        'payment_term',
        'ref',
        'journal_name',
        'amount_untaxed',
        'amount_tax',
        'amount_total',
        'partner_bank',
        'manager_name',
        'spv_name',
        'partner_address',
        'partner_address_complete',
        'partner_npwp',
        'contract_ref',
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
        return $this->hasMany(InvoiceDriverLine::class);
    }
}
