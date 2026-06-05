<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceProformaLine extends Model
{
    protected $fillable = [
        'invoice_proforma_id',
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
        'customer_name',
        'product_name',
        'license_plate',
    ];

    protected $casts = [
        'actual_start' => 'datetime',
        'actual_end' => 'datetime',
        'quantity' => 'decimal:2',
        'rental_qty' => 'decimal:2',
        'price_unit' => 'decimal:2',
        'duration_price' => 'decimal:2',
    ];

    public function invoiceProforma(): BelongsTo
    {
        return $this->belongsTo(InvoiceProforma::class);
    }

    /**
     * Get description without brackets [ ]
     */
    public function getCleanDescriptionAttribute(): string
    {
        return str_replace(['[', ']'], '', $this->description ?? '');
    }
}
