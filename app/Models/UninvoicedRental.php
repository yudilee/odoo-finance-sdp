<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UninvoicedRental extends Model
{
    use HasFactory;

    protected $table = 'uninvoiced_rentals';

    protected $fillable = [
        'kode_cust',
        'nomor_so',
        'status',
        'nomor_po',
        'nomor_kontrak',
        'kontrak_ref',
        'nama_user',
        'nopol',
        'model',
        'tahun_mobil',
        'start',
        'end',
        'tanggal_periode_belum_cetak',
        'start_rental_period',
        'end_rental_period',
        'price_di_so',
        'duration_price',
        'invoice_period',
        'payment_terms',
        'area_pemakaian_unit',
        'chassis',
        'invoice_pic',
        'first_invoice_date',
        'rental_method',
        'recipient_bank',
        'tax_id',
        'id_tku',
        'kode_transaksi',
        'address',
        'tax_address',
    ];

    /**
     * Format a date/timestamp to DD/MMM/YYYY with uppercase month abbreviation (e.g. 01/SEP/2027)
     */
    public static function formatDate($value)
    {
        if (empty($value)) {
            return '';
        }
        $time = strtotime($value);
        if ($time === false || $time <= 0) {
            return (string)$value;
        }
        return strtoupper(date('d/M/Y', $time));
    }
}
