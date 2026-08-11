<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'platform',
        'version',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function licenses()
    {
        return $this->hasMany(License::class);
    }
}