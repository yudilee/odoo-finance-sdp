<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSchedule extends Model
{
    protected $guarded = [];

    protected $casts = [
        'enabled' => 'boolean',
        'prune_enabled' => 'boolean',
        'session_cleanup_enabled' => 'boolean',
    ];
}
