<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['name', 'type', 'postcode', 'state', 'city', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
