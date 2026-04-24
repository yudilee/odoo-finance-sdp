<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceRentalLine extends Model
{
    protected $fillable = [
        'invoice_rental_id',
        'sale_order_id',
        'description',
        'serial_number',
        'actual_start',
        'actual_end',
        'uom',
        'quantity',
        'rental_qty',
        'price_unit',
        'duration_price',
        'customer_name'
    ];

    protected $casts = [
        'actual_start' => 'string',
        'actual_end' => 'string',
        'quantity' => 'decimal:2',
        'rental_qty' => 'decimal:2',
        'price_unit' => 'decimal:2',
        'duration_price' => 'decimal:2',
    ];

    public function invoiceRental(): BelongsTo
    {
        return $this->belongsTo(InvoiceRental::class);
    }

    /**
     * Get description without brackets [ ]
     */
    public function getCleanDescriptionAttribute(): string
    {
        return str_replace(['[', ']'], '', $this->description ?? '');
    }
}
