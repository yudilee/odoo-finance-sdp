<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class InvoiceSubscription extends Model
{
    protected $fillable = [
        'period_odoo_id',
        'period_numeric_id',
        'so_name',
        'partner_name',
        'rental_status',
        'rental_type',
        'actual_start_rental',
        'actual_end_rental',
        'period_type',
        'product_name',
        'period_start',
        'period_end',
        'invoice_date',
        'price_unit',
        'rental_uom',
        'invoice_name',
        'invoice_ref',
        'invoice_state',
        'payment_state',
        'synced_at',
    ];

    protected $casts = [
        'actual_start_rental' => 'date',
        'actual_end_rental'   => 'date',
        'period_start'        => 'date',
        'period_end'          => 'date',
        'invoice_date'        => 'date',
        'price_unit'          => 'decimal:2',
        'synced_at'           => 'datetime',
    ];

    // ─── Computed status for display ───

    /**
     * Get the display status: not_invoiced | draft | paid | unpaid
     */
    public function getStatusAttribute(): string
    {
        if (empty($this->invoice_name) && empty($this->invoice_state)) {
            return 'not_invoiced';
        }
        if (strtolower($this->invoice_state ?? '') === 'draft') {
            return 'draft';
        }
        if (strtolower($this->payment_state ?? '') === 'paid') {
            return 'paid';
        }
        if (strtolower($this->invoice_state ?? '') === 'posted') {
            return 'unpaid';
        }
        // catch-all: has something but doesn't match known states
        return 'draft';
    }

    /**
     * Whether the invoice date has already passed (overdue, not yet invoiced)
     */
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->invoice_date || $this->status !== 'not_invoiced') {
            return false;
        }
        return \Carbon\Carbon::parse($this->invoice_date)->lt(\Carbon\Carbon::today());
    }

    // ─── Scopes ───

    public function scopeStatus(Builder $q, string $status): Builder
    {
        return match($status) {
            'not_invoiced' => $q->whereNull('invoice_name')->orWhere('invoice_name', ''),
            'draft'        => $q->whereRaw("LOWER(invoice_state) = 'draft'"),
            'paid'         => $q->whereRaw("LOWER(payment_state) = 'paid'"),
            'unpaid'       => $q->whereRaw("LOWER(invoice_state) = 'posted'")->whereRaw("LOWER(payment_state) != 'paid'"),
            default        => $q,
        };
    }

    public function scopeSearch(Builder $q, string $term): Builder
    {
        return $q->where(function ($inner) use ($term) {
            $inner->where('so_name', 'like', "%{$term}%")
                  ->orWhere('partner_name', 'like', "%{$term}%")
                  ->orWhere('invoice_name', 'like', "%{$term}%")
                  ->orWhere('product_name', 'like', "%{$term}%");
        });
    }
}
