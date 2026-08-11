<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'license_key',
        'action',
        'platform',
        'ip_address',
        'user_agent',
        'fingerprint',
        'device_info',
        'notes',
    ];

    protected $casts = [
        'device_info' => 'array',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}