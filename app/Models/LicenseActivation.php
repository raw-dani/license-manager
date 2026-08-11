<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseActivation extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'fingerprint',
        'platform',
        'device_info',
        'domain',
        'ip_address',
        'status',
        'last_verified_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'last_verified_at' => 'datetime',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}