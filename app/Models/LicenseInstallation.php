<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseInstallation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'license_activation_id',
        'install_id',
        'fingerprint',
        'platform',
        'domain',
        'ip_address',
        'hostname',
        'server_info',
        'bound_at',
        'last_verified_at',
        'is_active',
    ];

    protected $guarded = [
        'transfer_token',
        'transfer_token_expires_at',
        'id',
    ];

    protected $casts = [
        'server_info' => 'array',
        'transfer_token_expires_at' => 'datetime',
        'bound_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }

    public function activation()
    {
        return $this->belongsTo(LicenseActivation::class, 'license_activation_id');
    }

    public function isTransferTokenValid(): bool
    {
        return $this->transfer_token
            && $this->transfer_token_expires_at
            && $this->transfer_token_expires_at->isFuture();
    }
}
