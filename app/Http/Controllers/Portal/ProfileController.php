<?php

namespace App\Http\Controllers\Portal;

use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\CustomerAddress;
use App\Domains\MasterData\Models\CustomerPic;
use App\Domains\MasterData\Models\CustomerProfileChange;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\UsesPortalCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Section P: the customer keeps its own profile: company and e-invoice details (major changes wait for Admin
 * re-verification), persons in charge, pickup / delivery addresses and email notifications. Every change is
 * kept in customer_profile_changes as the audit trail.
 */
class ProfileController extends Controller
{
    use UsesPortalCustomer;

    public const ID_TYPES = ['BRN' => 'BRN', 'NRIC' => 'NRIC', 'PASSPORT' => 'Passport', 'ARMY' => 'Army ID'];

    public function show(): View
    {
        $customer = $this->portalCustomer()->load(['pics', 'addresses']);

        return view('portal.profile', [
            'customer' => $customer,
            'pending' => $customer->profileChanges()->where('status', CustomerProfileChange::STATUS_PENDING)->latest()->get(),
            'history' => $customer->profileChanges()->with(['user', 'reviewer'])->latest()->limit(50)->get(),
            'idTypes' => self::ID_TYPES,
        ]);
    }

    public function updateCompany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'brn' => ['required', 'string', 'max:100'],
            'tin' => ['nullable', 'string', 'max:100'],
            'sst_registration_no' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        return $this->saveCustomerFields($request, 'company', $data);
    }

    public function updateEinvoice(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'einvoice_buyer_name' => ['required', 'string', 'max:255'],
            'einvoice_tin' => ['required', 'string', 'max:100'],
            'einvoice_id_type' => ['required', 'in:'.implode(',', array_keys(self::ID_TYPES))],
            'einvoice_id_value' => ['required', 'string', 'max:100'],
            'einvoice_address' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->saveCustomerFields($request, 'einvoice', $data);
    }

    public function updateNotifications(Request $request): RedirectResponse
    {
        return $this->saveCustomerFields($request, 'notifications', [
            'email_notifications' => $request->boolean('email_notifications'),
        ]);
    }

    public function storePic(Request $request): RedirectResponse
    {
        $customer = $this->portalCustomer();
        $data = $this->validatePic($request);

        DB::transaction(function () use ($customer, $data, $request) {
            if ($data['is_default']) {
                $customer->pics()->update(['is_default' => false]);
            }
            $pic = $customer->pics()->create($data);
            $this->log($request, $customer, 'pic', 'create', $pic->id, $this->diff([], $data));
        });

        return back()->with('status', 'Person in charge added.');
    }

    public function updatePic(Request $request, CustomerPic $pic): RedirectResponse
    {
        $this->authorizeCustomerRecord($pic->customer_id);
        $data = $this->validatePic($request);

        DB::transaction(function () use ($pic, $data, $request) {
            if ($data['is_default']) {
                CustomerPic::query()->where('customer_id', $pic->customer_id)->whereKeyNot($pic->id)->update(['is_default' => false]);
            }
            $changes = $this->diff($pic->only(array_keys($data)), $data);
            $pic->update($data);
            $this->log($request, $pic->customer, 'pic', 'update', $pic->id, $changes);
        });

        return back()->with('status', 'Person in charge updated.');
    }

    public function destroyPic(Request $request, CustomerPic $pic): RedirectResponse
    {
        $this->authorizeCustomerRecord($pic->customer_id);
        $this->log($request, $pic->customer, 'pic', 'delete', $pic->id, $this->diff($pic->only(['name', 'phone', 'email']), []));
        $pic->delete();

        return back()->with('status', 'Person in charge removed.');
    }

    public function storeAddress(Request $request): RedirectResponse
    {
        $customer = $this->portalCustomer();
        $data = $this->validateAddress($request);

        DB::transaction(function () use ($customer, $data, $request) {
            $this->clearDefault($customer, $data);
            $address = $customer->addresses()->create($data);
            $this->log($request, $customer, 'address', 'create', $address->id, $this->diff([], $data));
        });

        return back()->with('status', ucfirst($data['type']).' address added.');
    }

    public function updateAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeCustomerRecord($address->customer_id);
        $data = $this->validateAddress($request);

        DB::transaction(function () use ($address, $data, $request) {
            $this->clearDefault($address->customer, $data, $address->id);
            $changes = $this->diff($address->only(array_keys($data)), $data);
            $address->update($data);
            $this->log($request, $address->customer, 'address', 'update', $address->id, $changes);
        });

        return back()->with('status', 'Address updated.');
    }

    public function destroyAddress(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeCustomerRecord($address->customer_id);
        $this->log($request, $address->customer, 'address', 'delete', $address->id, $this->diff($address->only(['type', 'label', 'address']), []));
        $address->delete();

        return back()->with('status', 'Address removed.');
    }

    /** Minor fields apply at once; major ones (company name, BRN, TIN, e-invoice) wait for Admin. */
    private function saveCustomerFields(Request $request, string $section, array $data): RedirectResponse
    {
        $customer = $this->portalCustomer();
        $changes = $this->diff($customer->only(array_keys($data)), $data);

        if ($changes === []) {
            return back()->with('status', 'Nothing changed.');
        }

        $major = array_intersect_key($changes, array_flip(CustomerProfileChange::MAJOR_FIELDS));
        $minor = array_diff_key($changes, $major);

        if ($major !== [] && $customer->profileChanges()->where('status', CustomerProfileChange::STATUS_PENDING)->where('section', $section)->exists()) {
            return back()->withErrors(['profile' => 'A change to these details is already waiting for O&G verification.'])->withInput();
        }

        DB::transaction(function () use ($customer, $request, $section, $major, $minor) {
            if ($minor !== []) {
                $customer->update(array_map(fn ($pair) => $pair[1], $minor));
                $this->log($request, $customer, $section, 'update', null, $minor);
            }

            if ($major !== []) {
                $this->log($request, $customer, $section, 'update', null, $major, CustomerProfileChange::STATUS_PENDING);
            }
        });

        return back()->with('status', $major !== []
            ? 'Saved. Changes to '.collect(array_keys($major))->map(fn ($f) => CustomerProfileChange::FIELD_LABELS[$f] ?? $f)->implode(', ').' will apply after O&G verifies them.'
            : 'Saved.');
    }

    /** @return array<string, array{0: mixed, 1: mixed}> field => [old, new] for the fields that differ */
    private function diff(array $old, array $new): array
    {
        $fields = array_unique(array_merge(array_keys($old), array_keys($new)));
        $changes = [];

        foreach ($fields as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;
            $norm = fn ($v) => is_bool($v) ? (int) $v : (is_string($v) ? trim($v) : $v);

            if ((string) $norm($before) !== (string) $norm($after)) {
                $changes[$field] = [$before, $after];
            }
        }

        return $changes;
    }

    private function log(Request $request, Customer $customer, string $section, string $action, ?int $subjectId, array $changes, string $status = CustomerProfileChange::STATUS_APPLIED): void
    {
        if ($changes === []) {
            return;
        }

        CustomerProfileChange::query()->create([
            'customer_id' => $customer->id,
            'user_id' => $request->user()->id,
            'section' => $section,
            'action' => $action,
            'subject_id' => $subjectId,
            'changes' => $changes,
            'status' => $status,
        ]);
    }

    /** @return array{name: string, phone: ?string, email: ?string, is_default: bool} */
    private function validatePic(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        return $data + ['phone' => null, 'email' => null, 'is_default' => $request->boolean('is_default')];
    }

    /** @return array<string, mixed> */
    private function validateAddress(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', 'in:pickup,delivery'],
            'label' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'postcode' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'google_maps_url' => ['nullable', 'url', 'max:500'],
        ]);

        return $data + ['postcode' => null, 'city' => null, 'state' => null, 'google_maps_url' => null, 'is_default' => $request->boolean('is_default')];
    }

    private function clearDefault(Customer $customer, array $data, ?int $exceptId = null): void
    {
        if (! $data['is_default']) {
            return;
        }

        $query = $data['type'] === 'pickup'
            ? $customer->addresses()->where('type', 'pickup')
            : $customer->addresses()->where('type', '!=', 'pickup');

        $query->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->update(['is_default' => false]);
    }
}
