<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'odoo_id',
        'date',
        'journal_name',
        'move_name',
        'partner_name',
        'ref',
        'amount_total_signed',
    ];

    protected $casts = [
        'date' => 'date',
        'amount_total_signed' => 'decimal:2',
    ];

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    public function firstLine()
    {
        return $this->hasOne(JournalLine::class)->orderBy('id', 'asc');
    }
}
