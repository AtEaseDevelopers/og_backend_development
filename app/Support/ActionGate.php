<?php

namespace App\Support;

use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Resources\RelationManagers\RelationManager;
use Throwable;

/**
 * The buttons of a module's screens follow the module's Create / Edit / Delete ticks (user, 13 Nov 2026: a role can
 * be "view only"). Every Filament action (page header, table row, bulk, form and infolist actions) is asked here
 * once, from AppServiceProvider: on a screen of a module, an action that only reads (view, preview, print, export,
 * back, history …) needs nothing more than the screen; "create"-like actions need Create; "delete"-like need Delete;
 * everything else changes something and needs Edit. Screens that are not of a module are not touched.
 *
 * The built-in Edit / Delete / Create buttons of a resource are already decided by the resource
 * (HasModulePermissions); this covers the custom buttons (Assign to lorry, Add payment, Import …).
 */
class ActionGate
{
    /** Actions that only read or navigate. */
    private const READ = '/^(view|preview|print|pdf|export|download|open|back|refresh|show|copy|filter|toggle|apply|reset|removeFilter|close|cancel|sort|group|search|expand|collapse|prices|portalOrderLink|orderingLink|nextTrip|activeFilters)|(History|Log|Details)$/i';

    private const CREATE = '/^(create|replicate|duplicate|import|upload|add[A-Z]?)/';

    private const DELETE = '/^(delete|forceDelete|restore|remove|bulkDelete)/i';

    /**
     * Buttons that belong to one tick of Roles & Permission: without the tick the button is not shown (user,
     * 16 Nov 2026: a button a user sees is one they may use — no "you do not have permission" after the click).
     * "<Resource or page>:<action name>" => the permission.
     */
    private const SPECIAL = [
        'ConsignmentNoteResource:claim' => 'csn.claim',
        'ConsignmentNoteResource:takeBack' => 'csn.claim',
        'ConsignmentNoteResource:transferLorry' => 'dispatch.transfer_task',
        'JobSheetResource:transfer_task' => 'dispatch.transfer_task',
        'CreditApprovalRequestResource:approve' => 'orders.credit_approval',
        'CreditApprovalRequestResource:reject' => 'orders.credit_approval',
        'OrderDetail:creditDecision' => 'orders.credit_approval',
        'OrderDetail:release' => 'orders.release',
        'OrderDetail:rejectPayment' => 'billing.review_payment',
        'OrderDetail:editPayment' => 'billing.edit_payment',
        'PaymentSubmissionResource:verify' => 'billing.review_payment',
        'PaymentSubmissionResource:approve' => 'billing.review_payment',
        'PaymentSubmissionResource:reject' => 'billing.review_payment',
    ];

    /** The ability an action name stands for: view, create, edit or delete. */
    public static function abilityOf(string $name): string
    {
        return match (true) {
            preg_match(self::READ, $name) === 1 => 'view',
            preg_match(self::DELETE, $name) === 1 => 'delete',
            preg_match(self::CREATE, $name) === 1 => 'create',
            default => 'edit',
        };
    }

    /** May the signed-in user use the action on the screen it is on? Anything unclear is allowed (the screen's own rules still apply). */
    public static function allows(object $action): bool
    {
        try {
            $name = (string) $action->getName();
            $screen = static::screenOf($action);

            // a button of one tick needs that tick
            $special = $screen === null ? null : (self::SPECIAL[class_basename($screen).':'.$name] ?? null);
            if ($special !== null) {
                return Permissions::allows($special);
            }

            $ability = static::abilityOf($name);

            if ($ability === 'view') {
                return true;
            }

            return $screen === null ? true : Permissions::can($screen, $ability);
        } catch (Throwable) {
            return true;
        }
    }

    /** The resource or page class of the Livewire component the action is on. */
    private static function screenOf(object $action): ?string
    {
        $livewire = method_exists($action, 'getLivewire') ? $action->getLivewire() : null;

        if (! is_object($livewire)) {
            return null;
        }

        if ($livewire instanceof ResourcePage) {
            return $livewire::getResource();
        }

        if ($livewire instanceof RelationManager) {
            $page = $livewire->getPageClass();

            return is_string($page) && is_subclass_of($page, ResourcePage::class) ? $page::getResource() : null;
        }

        return $livewire::class;
    }
}
