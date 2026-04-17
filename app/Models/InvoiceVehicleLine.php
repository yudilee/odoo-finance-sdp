<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceVehicleLine extends Model
{
    protected $fillable = [
        'invoice_vehicle_id',
        'description',
        'serial_number',
        'license_plate',
        'product_name',
        'quantity',
        'price_unit',
        'duration_price',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'price_unit' => 'decimal:2',
        'duration_price' => 'decimal:2',
    ];

    public function invoice()
    {
        return $this->belongsTo(InvoiceVehicle::class, 'invoice_vehicle_id');
    }

    /**
     * Get description without brackets [ ]
     */
    public function getCleanDescriptionAttribute(): string
    {
        return str_replace(['[', ']'], '', $this->description ?? '');
    }
}
