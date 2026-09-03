<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class License extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_key',
        'product_id',
        'customer_name',
        'customer_email',
        'status',
        'max_activations',
        'current_activations',
        'expires_at',
        'activated_at',
        'last_verified_at',
        'suspended_at',
        'webhook_url',
        'webhook_secret',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'activated_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function activations()
    {
        return $this->hasMany(LicenseActivation::class);
    }

    public function installations()
    {
        return $this->hasMany(LicenseInstallation::class);
    }

    public function activeInstallation()
    {
        return $this->hasOne(LicenseInstallation::class)->where('is_active', true);
    }

    public function logs()
    {
        return $this->hasMany(ActivationLog::class);
    }

    public function whmcsSyncLogs()
    {
        return $this->hasMany(WhmcsSyncLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function canActivate(): bool
    {
        return $this->isActive() && !$this->isSuspended() && $this->current_activations < $this->max_activations;
    }
}