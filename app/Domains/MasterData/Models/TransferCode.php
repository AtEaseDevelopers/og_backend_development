<?php

namespace App\Domains\MasterData\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TransferCode extends Model
{
    /** The type of a code as it is shown (user, 13 Nov 2026): Transfer / Break-bulk, and Pickup (was "Incoming PSI"). */
    public const TYPES = [
        'transfer' => 'Transfer / Break-bulk',
        'incoming' => 'Pickup',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    protected $fillable = ['code', 'name', 'destination_branch_id', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    /** The staff who handle this code's subsheets (assign them to a lorry from the CSN page). */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
