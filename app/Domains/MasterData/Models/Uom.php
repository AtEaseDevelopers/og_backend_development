<?php

namespace App\Domains\MasterData\Models;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
