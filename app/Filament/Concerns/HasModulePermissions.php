<?php

namespace App\Filament\Concerns;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;

/**
 * A resource of a module (customers, billing, fleet …) follows the module's Create / Edit / Delete ticks of
 * Master Data → Roles & Permission (user, 13 Nov 2026: a role can be "view only"). Opening the list is the View tick
 * (the resource's own canViewAny). A resource that has its own rule for one of these keeps it (Create is switched off
 * on several resources, whatever the role).
 */
trait HasModulePermissions
{
    public static function canCreate(): bool
    {
        return Permissions::can(static::class, 'create') && parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        return Permissions::can(static::class, 'edit') && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return Permissions::can(static::class, 'delete') && parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        return Permissions::can(static::class, 'delete') && parent::canDeleteAny();
    }

    public static function canForceDelete(Model $record): bool
    {
        return Permissions::can(static::class, 'delete') && parent::canForceDelete($record);
    }

    public static function canRestore(Model $record): bool
    {
        return Permissions::can(static::class, 'delete') && parent::canRestore($record);
    }

    public static function canReorder(): bool
    {
        return Permissions::can(static::class, 'edit') && parent::canReorder();
    }
}
