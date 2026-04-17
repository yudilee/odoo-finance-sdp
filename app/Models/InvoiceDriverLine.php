<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDriverLine extends Model
{
    protected $fillable = [
        'invoice_driver_id',
        'description',
        'quantity',
        'price_unit',
        'duration_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price_unit' => 'decimal:2',
        'duration_price' => 'decimal:2',
    ];

    public function invoiceDriver()
    {
        return $this->belongsTo(InvoiceDriver::class);
    }
}
