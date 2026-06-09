<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    protected $fillable = ['invoice_name', 'print_mode', 'print_count', 'kuitansi_print_count', 'kuitansi_pembayaran', 'preferences'];

    protected $casts = [
        'preferences' => 'array',
    ];

    public function getPreference(string $key, $default = null)
    {
        return $this->preferences[$key] ?? $default;
    }

    public function setPreference(string $key, $value)
    {
        $prefs = $this->preferences ?? [];
        $prefs[$key] = $value;
        $this->preferences = $prefs;
        return $this->save();
    }
}
