<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, $default = null): ?string
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getOdooConfig(): array
    {
        return [
            'url' => static::get('odoo_url', ''),
            'db' => static::get('odoo_db', ''),
            'user' => static::get('odoo_user', ''),
            'password' => static::get('odoo_password', ''),
        ];
    }

    public static function getValue(string $key, $default = null): ?string
    {
        return static::get($key, $default);
    }

    public static function setValue(string $key, $value): void
    {
        static::set($key, $value);
    }
}
