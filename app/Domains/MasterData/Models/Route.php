<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = ['code', 'name', 'from_state', 'to_state', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
