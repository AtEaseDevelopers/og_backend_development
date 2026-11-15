<?php

namespace App\Support;

use App\Filament\Pages;
use App\Filament\Resources;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * What each role may do (user, 13 Nov 2026: Master Data → Roles & Permission, ticked per role).
 *
 * Two kinds of permission:
 *  - a MODULE (orders, customers, billing …) is split into View / Create / Edit / Delete, each its own tick, so a role
 *    can be given "view only": the permission is "<module>.<ability>" (customers.view, customers.edit …);
 *  - an ACTION is one tick for one thing (release an order, review a payment, open reports …).
 *
 * The list is fixed here; which role has which is kept in the database (the roles' permissions) and changed on the
 * Roles & Permission page. A user gets a permission from any of their roles; their own exception
 * (user_permission_overrides: allow or deny) comes first; a super admin (and HQ) may do everything.
 *
 * `roles` is what the role could do before the page existed: applied once, when the permissions are first created
 * (syncDefaults), so nobody gained or lost access on the day it went in.
 */
class Permissions
{
    /** The staff roles that enter the admin panel (User::canAccessPanel). */
    public const STAFF = ['super_admin', 'branch_admin', 'branch_manager', 'counter', 'finance', 'dispatcher', 'salesperson', 'storekeeper'];

    private const MANAGERS = ['super_admin', 'branch_admin', 'branch_manager'];

    private const DISPATCH = ['super_admin', 'branch_admin', 'branch_manager', 'dispatcher'];

    private const ADMINS = ['super_admin', 'branch_admin'];

    public const ABILITIES = ['view' => 'View', 'create' => 'Create', 'edit' => 'Edit', 'delete' => 'Delete'];

    /**
     * The modules, each split into its abilities. `note` says what the module covers.
     *
     * @var array<string, array{group: string, label: string, note: string, abilities: list<string>, roles: list<string>}>
     */
    public const MODULES = [
        'orders' => ['group' => 'Orders', 'label' => 'Orders', 'note' => 'orders, customer order review, credit approval list', 'abilities' => ['view', 'create', 'edit'], 'roles' => self::STAFF],
        'quotations' => ['group' => 'Quotations', 'label' => 'Quotations', 'note' => 'quotations and price lists', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'csn' => ['group' => 'CSN & Dispatch', 'label' => 'Consignment notes', 'note' => 'CSNs, delivery orders, subsheets, missing CSNs; Edit covers assign to lorry, subsheets and the other buttons', 'abilities' => ['view', 'edit'], 'roles' => self::STAFF],
        'jobsheets' => ['group' => 'CSN & Dispatch', 'label' => 'Job sheets', 'note' => 'job sheets, daily lorry schedule, break-bulk, failed deliveries', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'billing' => ['group' => 'Billing', 'label' => 'Billing', 'note' => 'invoices, payments, refund notes, statements, COD, cash bill counter', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'commission' => ['group' => 'Commission', 'label' => 'Commission', 'note' => 'commission batches, rules and purchase orders', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'customers' => ['group' => 'Customers', 'label' => 'Customers', 'note' => 'customers, consignors / consignees, leads', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'products' => ['group' => 'Master Data', 'label' => 'Products & pricing', 'note' => 'measurements, items, chartered lorry, drop-off charges, areas', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'fleet' => ['group' => 'Master Data', 'label' => 'Fleet', 'note' => 'drivers, fleet vehicles, lorry companies, vehicle maintenance', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'transfer_stores' => ['group' => 'Master Data', 'label' => 'Transfer codes & stores', 'note' => 'transfer codes and stores', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::STAFF],
        'company' => ['group' => 'Master Data', 'label' => 'Company & branches', 'note' => 'the company and its branches', 'abilities' => ['view', 'create', 'edit'], 'roles' => self::ADMINS],
        'users' => ['group' => 'System Settings', 'label' => 'Users', 'note' => 'users and the organisation chart', 'abilities' => ['view', 'create', 'edit', 'delete'], 'roles' => self::ADMINS],
    ];

    /**
     * The single actions. `kinds` are the tags shown behind the name (View, Edit, Approve …).
     *
     * @var array<string, array{group: string, label: string, kinds: list<string>, roles: list<string>}>
     */
    public const ACTIONS = [
        'orders.override_price' => ['group' => 'Orders', 'label' => 'Enter a price different from the price list', 'kinds' => ['Edit'], 'roles' => self::MANAGERS],
        'orders.release' => ['group' => 'Orders', 'label' => 'Release an order held for payment or credit', 'kinds' => ['Approve'], 'roles' => ['super_admin', 'branch_admin', 'branch_manager', 'finance']],
        'orders.credit_approval' => ['group' => 'Orders', 'label' => 'Approve or reject a credit approval', 'kinds' => ['Approve'], 'roles' => self::MANAGERS],
        'orders.revise_confirmed' => ['group' => 'Orders', 'label' => 'Revise an order after it is confirmed', 'kinds' => ['Edit'], 'roles' => ['super_admin']],
        'orders.reassign_salesperson' => ['group' => 'Orders', 'label' => 'Change a locked salesperson of an order', 'kinds' => ['Edit'], 'roles' => ['super_admin']],
        'csn.claim' => ['group' => 'CSN & Dispatch', 'label' => 'Claim a consignment note', 'kinds' => ['Edit'], 'roles' => ['super_admin', 'branch_admin', 'branch_manager', 'dispatcher', 'storekeeper', 'counter']],
        'csn.returned_desk' => ['group' => 'CSN & Dispatch', 'label' => 'Returned CSNs desk', 'kinds' => ['View', 'Edit'], 'roles' => ['super_admin', 'branch_admin', 'branch_manager', 'counter', 'storekeeper', 'finance']],
        'dispatch.monitoring' => ['group' => 'CSN & Dispatch', 'label' => 'Delivery Monitoring', 'kinds' => ['View'], 'roles' => self::DISPATCH],
        'dispatch.shared' => ['group' => 'CSN & Dispatch', 'label' => 'Shared Dispatch', 'kinds' => ['View', 'Edit'], 'roles' => self::DISPATCH],
        'dispatch.transfer_task' => ['group' => 'CSN & Dispatch', 'label' => 'Move a delivery to another lorry or driver', 'kinds' => ['Edit'], 'roles' => self::DISPATCH],
        'billing.review_payment' => ['group' => 'Billing', 'label' => 'Review a payment (approve or reject)', 'kinds' => ['Approve'], 'roles' => ['super_admin', 'branch_admin', 'branch_manager', 'finance', 'counter']],
        'billing.edit_payment' => ['group' => 'Billing', 'label' => 'Edit a recorded payment', 'kinds' => ['Edit'], 'roles' => ['super_admin', 'branch_admin', 'branch_manager', 'finance', 'counter']],
        'customers.portal_approvals' => ['group' => 'Customers', 'label' => 'Portal registrations and customer profile changes', 'kinds' => ['Approve'], 'roles' => ['super_admin', 'branch_manager', 'finance']],
        'reports.view' => ['group' => 'Reports & Integrations', 'label' => 'Reports', 'kinds' => ['View'], 'roles' => ['super_admin', 'branch_admin', 'branch_manager', 'finance', 'dispatcher']],
        'integrations.autocount' => ['group' => 'Reports & Integrations', 'label' => 'AutoCount Sync', 'kinds' => ['View', 'Edit'], 'roles' => ['super_admin', 'finance', 'branch_manager']],
        'integrations.view' => ['group' => 'Reports & Integrations', 'label' => 'MyInvois e-Invoice and the notifications log', 'kinds' => ['View'], 'roles' => self::STAFF],
        'settings.notifications' => ['group' => 'System Settings', 'label' => 'Order notification settings', 'kinds' => ['Edit'], 'roles' => self::ADMINS],
        'settings.roles' => ['group' => 'System Settings', 'label' => 'Roles and permissions', 'kinds' => ['Edit'], 'roles' => ['super_admin']],
    ];

    /** The order of the groups on the page. */
    private const GROUPS = ['Orders', 'Quotations', 'CSN & Dispatch', 'Billing', 'Commission', 'Customers', 'Master Data', 'Reports & Integrations', 'System Settings'];

    /**
     * The permission a screen needs to open: a resource or page class => a permission, or several (any one opens it).
     * A class not listed is open to all staff. A class of a module also has its buttons checked (ActionGate).
     */
    public const SCREENS = [
        Pages\Orders::class => 'orders.view',
        Pages\OrderDetail::class => 'orders.view',
        Pages\PortalEnquiries::class => 'orders.view',
        Pages\CreditCustomerApproval::class => 'orders.view',
        Resources\CreditApprovalRequestResource::class => 'orders.view',
        Resources\OcrUploadResource::class => 'orders.view',
        Pages\CreateOrder::class => 'orders.create',
        Pages\EditOrder::class => 'orders.edit',
        Resources\QuoteResource::class => 'quotations.view',
        Pages\CreateQuotation::class => ['quotations.create', 'quotations.edit'],
        Resources\ConsignmentNoteResource::class => 'csn.view',
        Resources\DeliveryOrderResource::class => 'csn.view',
        Resources\SubsheetResource::class => 'csn.view',
        Resources\MissingCsnLogResource::class => 'csn.view',
        Pages\ReturnedCsnDesk::class => 'csn.returned_desk',
        Resources\JobSheetResource::class => 'jobsheets.view',
        Pages\DailyLorrySchedule::class => 'jobsheets.view',
        Resources\BreakBulkResource::class => 'jobsheets.view',
        Resources\FailedDeliveryResource::class => 'jobsheets.view',
        Pages\DeliveryMonitoring::class => 'dispatch.monitoring',
        Pages\SharedDispatch::class => 'dispatch.shared',
        Resources\InvoiceResource::class => 'billing.view',
        Resources\OtherInvoiceResource::class => 'billing.view',
        Resources\OtherItemResource::class => 'products.view',
        Resources\PaymentResource::class => 'billing.view',
        Resources\PaymentSubmissionResource::class => 'billing.view',
        Resources\RefundNoteResource::class => 'billing.view',
        Resources\StatementResource::class => 'billing.view',
        Pages\CodListing::class => 'billing.view',
        Pages\CodReconciliation::class => 'billing.view',
        Pages\CashBillCalculator::class => 'billing.view',
        Resources\CommissionBatchResource::class => 'commission.view',
        Resources\CommissionRuleResource::class => 'commission.view',
        Resources\CommissionSlipResource::class => 'commission.view',
        Resources\CustomerResource::class => 'customers.view',
        Resources\ConsignorConsigneeResource::class => 'customers.view',
        Resources\LeadResource::class => 'customers.view',
        Pages\PortalAccessApprovals::class => 'customers.portal_approvals',
        Pages\CustomerProfileChanges::class => 'customers.portal_approvals',
        Resources\UomResource::class => 'products.view',
        Resources\ItemProductResource::class => 'products.view',
        Resources\ItemResource::class => 'products.view',
        Resources\LorryProductResource::class => 'products.view',
        Resources\DropOffMinChargeResource::class => 'products.view',
        Resources\LocationResource::class => 'products.view',
        Resources\DriverResource::class => 'fleet.view',
        Resources\FleetVehicleResource::class => 'fleet.view',
        Resources\LorryResource::class => 'fleet.view',
        Resources\LorryCompanyResource::class => 'fleet.view',
        Resources\VehicleMaintenanceResource::class => 'fleet.view',
        Resources\TransferCodeResource::class => 'transfer_stores.view',
        Resources\StoreResource::class => 'transfer_stores.view',
        Resources\BranchResource::class => 'company.view',
        Resources\SaLocationResource::class => 'company.view',
        Pages\ReportsHub::class => 'reports.view',
        Pages\ReportDetail::class => 'reports.view',
        Pages\AutoCountSync::class => 'integrations.autocount',
        Resources\EinvoiceSubmissionResource::class => 'integrations.view',
        Resources\NotificationLogResource::class => 'integrations.view',
        Resources\UserResource::class => 'users.view',
        Pages\OrganisationChartPage::class => 'users.view',
        Pages\SystemSettingsPage::class => 'settings.notifications',
        Pages\RolesPermissionsPage::class => 'settings.roles',
    ];

    /** @var array<int, array<string, bool>> the user's answers of this request */
    private static array $cache = [];

    /** @var array<string, array{group: string, label: string, note: ?string, kinds: list<string>, roles: list<string>}>|null */
    private static ?array $catalog = null;

    /**
     * Every permission, in the order of the page: per group its modules (one entry per ability), then its actions.
     *
     * @return array<string, array{group: string, label: string, note: ?string, kinds: list<string>, roles: list<string>}>
     */
    public static function catalog(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $catalog = [];
        foreach (self::GROUPS as $group) {
            foreach (self::MODULES as $module => $entry) {
                if ($entry['group'] !== $group) {
                    continue;
                }
                foreach ($entry['abilities'] as $ability) {
                    $catalog[$module.'.'.$ability] = [
                        'group' => $group,
                        'label' => $entry['label'],
                        // what the module covers, said once, on its first row
                        'note' => $ability === $entry['abilities'][0] ? $entry['note'] : null,
                        'kinds' => [self::ABILITIES[$ability]],
                        'roles' => $entry['roles'],
                    ];
                }
            }
            foreach (self::ACTIONS as $name => $entry) {
                if ($entry['group'] === $group) {
                    $catalog[$name] = $entry + ['note' => null];
                }
            }
        }

        return self::$catalog = $catalog;
    }

    /** May the user (the signed-in one by default) do it? */
    public static function allows(string $permission, ?User $user = null): bool
    {
        $user ??= auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return static::granted($user)[$permission] ?? false;
    }

    /** May the signed-in user open the screen (a resource or page class)? A class without a permission is open. */
    public static function screen(string $class): bool
    {
        $needed = self::SCREENS[$class] ?? null;

        if ($needed === null) {
            return auth()->check();
        }

        foreach ((array) $needed as $permission) {
            if (static::allows($permission)) {
                return true;
            }
        }

        return false;
    }

    /** The module a screen belongs to (orders, customers …), or null for a screen that is not of a module. */
    public static function moduleOf(string $class): ?string
    {
        $needed = self::SCREENS[$class] ?? null;

        if ($needed === null) {
            return null;
        }

        $module = explode('.', ((array) $needed)[0])[0];

        return isset(self::MODULES[$module]) ? $module : null;
    }

    /**
     * May the signed-in user create / edit / delete on the screen's module? A module without that ability (orders have
     * no Delete tick) falls back to Edit; a screen that is not of a module has no such split (true).
     */
    public static function can(string $class, string $ability): bool
    {
        $module = static::moduleOf($class);

        if ($module === null) {
            return true;
        }

        $abilities = self::MODULES[$module]['abilities'];
        if (! in_array($ability, $abilities, true)) {
            $ability = in_array('edit', $abilities, true) ? 'edit' : 'view';
        }

        return static::allows($module.'.'.$ability);
    }

    /**
     * Every permission for the user: from their roles, then their own exceptions.
     *
     * @return array<string, bool>
     */
    public static function granted(User $user): array
    {
        if (isset(self::$cache[$user->id])) {
            return self::$cache[$user->id];
        }

        try {
            $fromRoles = $user->getPermissionsViaRoles()->pluck('name')->flip();
            $overrides = Schema::hasTable('user_permission_overrides')
                ? DB::table('user_permission_overrides')->where('user_id', $user->id)->pluck('allowed', 'permission')
                : collect();
        } catch (Throwable) {
            return [];
        }

        $granted = [];
        foreach (array_keys(static::catalog()) as $permission) {
            $granted[$permission] = $overrides->has($permission) ? (bool) $overrides[$permission] : $fromRoles->has($permission);
        }

        return self::$cache[$user->id] = $granted;
    }

    /** What the user's roles give, without their own exceptions. @return array<string, bool> */
    public static function fromRoles(User $user): array
    {
        $held = $user->getPermissionsViaRoles()->pluck('name')->flip();

        return collect(array_keys(static::catalog()))->mapWithKeys(fn (string $permission) => [$permission => $held->has($permission)])->all();
    }

    /** The user's own exceptions: permission => 'allow' | 'deny'. @return array<string, string> */
    public static function overridesFor(User $user): array
    {
        return DB::table('user_permission_overrides')->where('user_id', $user->id)->pluck('allowed', 'permission')
            ->map(fn ($allowed) => $allowed ? 'allow' : 'deny')
            ->all();
    }

    /**
     * Replaces the user's exceptions: permission => 'allow' | 'deny'; anything else (or one left out) follows the role.
     *
     * @param  array<string, ?string>  $overrides
     */
    public static function setOverrides(User $user, array $overrides): void
    {
        $catalog = static::catalog();
        $rows = [];
        foreach ($overrides as $permission => $choice) {
            if (isset($catalog[$permission]) && in_array($choice, ['allow', 'deny'], true)) {
                $rows[] = ['user_id' => $user->id, 'permission' => $permission, 'allowed' => $choice === 'allow', 'created_at' => now(), 'updated_at' => now()];
            }
        }

        DB::transaction(function () use ($user, $rows): void {
            DB::table('user_permission_overrides')->where('user_id', $user->id)->delete();
            if ($rows !== []) {
                DB::table('user_permission_overrides')->insert($rows);
            }
        });

        static::flush();
    }

    /** Forget the answers kept for this request (after the roles' permissions or a user's exceptions change). */
    public static function flush(): void
    {
        self::$cache = [];
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Creates the permissions that are missing, and gives a role that has no permission at all what it could do before
     * (`roles`). Run by the migration (existing roles) and by the seeder (a new install, where the roles are created
     * after the migrations). A role an admin has already set up is never touched.
     */
    public static function syncDefaults(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        // the list kept in memory may be older than the table
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $catalog = static::catalog();
        foreach (array_keys($catalog) as $name) {
            Permission::findOrCreate($name, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Role::query()->where('guard_name', 'web')->whereIn('name', self::STAFF)->get() as $role) {
            if ($role->permissions()->exists()) {
                continue;
            }

            $role->syncPermissions(collect($catalog)->filter(fn (array $entry) => in_array($role->name, $entry['roles'], true))->keys()->all());
        }

        static::flush();
    }

    /** The catalog by group, for the pages. @return array<string, array<string, array{label: string, note: ?string, kinds: list<string>}>> */
    public static function grouped(): array
    {
        $groups = [];
        foreach (static::catalog() as $name => $entry) {
            $groups[$entry['group']][$name] = ['label' => $entry['label'], 'note' => $entry['note'], 'kinds' => $entry['kinds']];
        }

        return $groups;
    }
}
