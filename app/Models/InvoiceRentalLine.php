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
        'price_unit',
        'customer_name'
    ];

    protected $casts = [
        'actual_start' => 'date',
        'actual_end' => 'date',
        'quantity' => 'decimal:2',
        'price_unit' => 'decimal:2',
    ];

    public function invoiceRental(): BelongsTo
    {
        return $this->belongsTo(InvoiceRental::class);
    }
}
