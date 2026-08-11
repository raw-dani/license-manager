<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhmcsSyncLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'license_id',
        'action',
        'status',
        'request_data',
        'response_data',
        'error',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
    ];

    public function license()
    {
        return $this->belongsTo(License::class);
    }
}