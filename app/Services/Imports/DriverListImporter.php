<?php

namespace App\Services\Imports;

use App\Domains\MasterData\Models\Driver;
use App\Filament\Resources\DriverResource;
use Illuminate\Support\Facades\DB;

/**
 * Imports the client's "Lorry & Driver Details" workbook (user, 21 Oct 2026) into Master Data → Drivers: the columns
 * are found by their titles (Lorry Group Code, Lorry Group, Lorry Company Code, Lorry Company, Lorry Number, Lorry
 * Type, Tonne, Driver Name / IC / Phone Number / Emergency Contact / Address, Attendant Name / IC / Phone Number /
 * Emergency Contact / Address). A driver already there (same IC, else same name) is updated; each driver's lorry is
 * kept in step in Master Data → Lorries. Uploaded on the Drivers page, so the personal data never sits in the code.
 */
class DriverListImporter
{
    private const COLUMNS = [
        'lorrygroupcode' => 'lorry_group_code',
        'lorrygroup' => 'lorry_group',
        'lorrygroupname' => 'lorry_group',
        'lorrycompanycode' => 'lorry_company_code',
        'lorrycompany' => 'lorry_company',
        'lorrycompanyname' => 'lorry_company',
        'lorrynumber' => 'lorry_number',
        'lorrytype' => 'lorry_type',
        'tonne' => 'tonnage',
        'drivername' => 'name',
        'driveric' => 'ic_number',
        'driverphonenumber' => 'phone',
        'driveremergencycontact' => 'emergency_contact',
        'driveraddress' => 'address',
        'attendantname' => 'attendant_name',
        'attendantic' => 'attendant_ic',
        'attendantphonenumber' => 'attendant_phone',
        'attendantemergencycontact' => 'attendant_emergency_contact',
        'attendantaddress' => 'attendant_address',
    ];

    private const ATTENDANT = ['attendant_name', 'attendant_ic', 'attendant_phone', 'attendant_emergency_contact', 'attendant_address'];

    public function __construct(private SimpleXlsxReader $reader) {}

    /** The master record (lorry company / group) for a code or name of the list: the one with that code, else created. */
    public static function masterId(string $model, ?int $companyId, ?string $code, ?string $name): ?int
    {
        $code = trim((string) $code) ?: null;
        $name = trim((string) $name) ?: $code;

        if ($name === null) {
            return null;
        }

        $row = $model::query()->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->where(fn ($q) => $code ? $q->where('code', $code) : $q->where('name', $name))->first()
            ?? $model::query()->create(['company_id' => $companyId, 'code' => $code, 'name' => $name, 'is_active' => true]);

        return (int) $row->id;
    }

    /** @return array{created: int, updated: int, lorries: int, skipped: list<string>} */
    public function importFile(string $path, ?int $companyId, ?int $branchId): array
    {
        $result = ['created' => 0, 'updated' => 0, 'lorries' => 0, 'skipped' => []];

        DB::transaction(function () use ($path, $companyId, $branchId, &$result) {
            foreach ($this->reader->readSheetsByRow($path) as $sheet => $rows) {
                $map = null;

                foreach ($rows as $rowNumber => $cells) {
                    if ($map === null) {
                        $map = $this->headerMap($cells);

                        continue;
                    }

                    $data = [];

                    foreach ($map as $column => $field) {
                        $value = trim((string) ($cells[$column] ?? ''));
                        $data[$field] = $value !== '' ? $value : null;
                    }

                    if (blank($data['name'] ?? null)) {
                        continue;
                    }

                    $this->saveDriver($data, $companyId, $branchId, $result);
                }

                if ($map === null) {
                    $result['skipped'][] = $sheet.': no "Driver Name" column';
                }
            }
        });

        return $result;
    }

    /** The row with the column titles (it has "Driver Name"): column letter → driver field. Null when not it. */
    private function headerMap(array $cells): ?array
    {
        $map = [];

        foreach ($cells as $column => $title) {
            $key = strtolower(preg_replace('/[^a-z0-9]/i', '', (string) $title));

            if (isset(self::COLUMNS[$key])) {
                $map[$column] = self::COLUMNS[$key];
            }
        }

        return in_array('name', $map, true) ? $map : null;
    }

    private function saveDriver(array $data, ?int $companyId, ?int $branchId, array &$result): void
    {
        // a placeholder IC ("xxxxxx-xx-xxxx") is not kept
        foreach (['ic_number', 'attendant_ic'] as $field) {
            if (filled($data[$field] ?? null) && ! preg_match('/\d/', (string) $data[$field])) {
                $data[$field] = null;
            }
        }

        // "NO ATTENDANT": no attendant
        if (preg_match('/^no\s+attendant$/i', (string) ($data['attendant_name'] ?? ''))) {
            foreach (self::ATTENDANT as $field) {
                $data[$field] = null;
            }
        }

        $data['tonnage'] = is_numeric($data['tonnage'] ?? null) ? (float) $data['tonnage'] : null;

        $driver = Driver::query()
            ->when($companyId, fn ($q, $id) => $q->where('company_id', $id))
            ->where(fn ($q) => filled($data['ic_number'] ?? null)
                ? $q->where('ic_number', $data['ic_number'])
                : $q->whereRaw('UPPER(name) = ?', [mb_strtoupper((string) $data['name'])]))
            ->first();

        $fields = array_intersect_key($data, array_flip(array_unique(array_values(self::COLUMNS))));

        if ($driver) {
            $driver->update($fields);
            $result['updated']++;
        } else {
            $driver = Driver::query()->create($fields + [
                'company_id' => $companyId,
                'branch_id' => $branchId,
                // a driver of a lorry company is a subcontractor
                'type' => filled($data['lorry_company'] ?? null) ? 'subcontractor' : 'internal',
                // the lorry company / group master records (user, 28 Oct 2026): found by code, else created
                'lorry_company_id' => static::masterId(\App\Domains\MasterData\Models\LorryCompany::class, $companyId, $data['lorry_company_code'] ?? null, $data['lorry_company'] ?? null),
                'lorry_group_id' => static::masterId(\App\Domains\MasterData\Models\LorryGroup::class, $companyId, $data['lorry_group_code'] ?? null, $data['lorry_group'] ?? null),
                'is_active' => true,
            ]);
            $result['created']++;
        }

        if (filled($driver->lorry_number)) {
            DriverResource::syncLorry($driver);
            $result['lorries']++;
        }
    }
}
