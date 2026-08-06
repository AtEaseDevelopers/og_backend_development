<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;

class SelectedBranch
{
    public const SESSION_KEY = 'filament.selected_branch_id';

    public static function set(Branch $branch): void
    {
        session([self::SESSION_KEY => $branch->id]);
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    public static function id(): ?int
    {
        $id = session(self::SESSION_KEY);

        return $id ? (int) $id : null;
    }

    public static function get(): ?Branch
    {
        $id = self::id();

        return $id ? Branch::query()->find($id) : null;
    }
}
