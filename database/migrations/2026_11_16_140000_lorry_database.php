<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Master Data (client, 16 Nov 2026): a lorry is under a lorry company (Lorry Database, the main page to search a lorry
// and see its company, driver and attendant); a lorry company has a PIC and a company no.; a lead can be set
// inactive. Each lorry takes the lorry company of its driver, and a driver's lorry number links the two when the
// lorry had no driver yet. Nothing is removed from the drivers.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lorries', function (Blueprint $table) {
            if (! Schema::hasColumn('lorries', 'lorry_company_id')) {
                $table->foreignId('lorry_company_id')->nullable()->constrained('lorry_companies')->nullOnDelete();
            }
        });
        Schema::table('lorry_companies', function (Blueprint $table) {
            if (! Schema::hasColumn('lorry_companies', 'pic')) {
                $table->string('pic', 150)->nullable();
            }
            if (! Schema::hasColumn('lorry_companies', 'company_no')) {
                $table->string('company_no', 50)->nullable();
            }
        });
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });

        $plain = fn (?string $number): string => strtoupper(preg_replace('/\s+/', '', (string) $number));
        $drivers = DB::table('drivers')->get(['id', 'lorry_number', 'lorry_company_id']);
        $byNumber = $drivers->filter(fn ($d) => $plain($d->lorry_number) !== '')->keyBy(fn ($d) => $plain($d->lorry_number));
        $byId = $drivers->keyBy('id');

        foreach (DB::table('lorries')->get(['id', 'registration_no', 'default_driver_id', 'lorry_company_id']) as $lorry) {
            $driver = $lorry->default_driver_id ? $byId->get($lorry->default_driver_id) : $byNumber->get($plain($lorry->registration_no));

            if (! $driver) {
                continue;
            }

            $changes = array_filter([
                'default_driver_id' => $lorry->default_driver_id ? null : $driver->id,
                'lorry_company_id' => $lorry->lorry_company_id ? null : $driver->lorry_company_id,
            ]);

            if ($changes !== []) {
                DB::table('lorries')->where('id', $lorry->id)->update($changes);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lorries', function (Blueprint $table) {
            if (Schema::hasColumn('lorries', 'lorry_company_id')) {
                $table->dropConstrainedForeignId('lorry_company_id');
            }
        });
        Schema::table('lorry_companies', function (Blueprint $table) {
            foreach (['pic', 'company_no'] as $column) {
                if (Schema::hasColumn('lorry_companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
