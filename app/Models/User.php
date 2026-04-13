<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'auth_source',
        'preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
        ];
    }

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

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    public function getRoleDisplayName(): string
    {
        return match($this->role) {
            'admin' => 'Administrator',
            'invoice' => 'Invoice',
            default => 'User',
        };
    }

    public function getRawPrintDestination(string $docType): array
    {
        $pref = $this->preferences['print_queues'][$docType] ?? [];
        return [
            'queue'    => $pref['queue']    ?? '',
            'agent_id' => $pref['agent_id'] ?? '',
            'printer'  => $pref['printer']  ?? '',
        ];
    }

    public function getPrintDestination(string $docType): array
    {
        $pref = $this->preferences['print_queues'][$docType] ?? [];
        return [
            'queue'    => $pref['queue']    ?? \App\Models\Setting::get('print_hub_default_profile') ?? 'kuitansi',
            'agent_id' => $pref['agent_id'] ?? null,
            'printer'  => $pref['printer']  ?? null,
        ];
    }

    public function setPrintDestination(string $docType, string $queue, ?int $agentId, ?string $printer): void
    {
        $prefs = $this->preferences ?? [];
        $prefs['print_queues'][$docType] = array_filter([
            'queue'    => $queue,
            'agent_id' => $agentId,
            'printer'  => $printer,
        ], fn($v) => $v !== null && $v !== '');
        $this->preferences = $prefs;
        $this->save();
    }
}
