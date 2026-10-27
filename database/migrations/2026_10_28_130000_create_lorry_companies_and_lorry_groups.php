<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lorry Company and Lorry Group as master data (user, 28 Oct 2026): simple records (code, name, contact, email,
 * address) a driver is linked to. The lorry companies / groups already typed on the drivers become records, and
 * the drivers are linked to them (the text columns on the driver stay in step for the PO and job sheet prints).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['lorry_companies', 'lorry_groups'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->id();
                    $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
                    $t->string('code', 50)->nullable();
                    $t->string('name', 150);
                    $t->string('contact', 50)->nullable();
                    $t->string('email')->nullable();
                    $t->text('address')->nullable();
                    $t->boolean('is_active')->default(true);
                    $t->timestamps();
                    $t->index(['company_id', 'code']);
                });
            }
        }

        Schema::table('drivers', function (Blueprint $t) {
            if (! Schema::hasColumn('drivers', 'lorry_company_id')) {
                $t->foreignId('lorry_company_id')->nullable()->after('lorry_company')->constrained('lorry_companies')->nullOnDelete();
            }

            if (! Schema::hasColumn('drivers', 'lorry_group_id')) {
                $t->foreignId('lorry_group_id')->nullable()->after('lorry_group')->constrained('lorry_groups')->nullOnDelete();
            }
        });

        $linked = 0;

        foreach ([['lorry_companies', 'lorry_company_code', 'lorry_company', 'lorry_company_id'], ['lorry_groups', 'lorry_group_code', 'lorry_group', 'lorry_group_id']] as [$table, $codeCol, $nameCol, $fk]) {
            $rows = DB::table('drivers')->whereNull($fk)
                ->where(fn ($q) => $q->whereNotNull($codeCol)->orWhereNotNull($nameCol))
                ->select(['company_id', $codeCol.' as code', $nameCol.' as name'])->distinct()->get();

            foreach ($rows as $row) {
                $code = trim((string) $row->code) ?: null;
                $name = trim((string) $row->name) ?: $code;

                if ($name === null) {
                    continue;
                }

                $id = DB::table($table)->where('company_id', $row->company_id)
                    ->where(fn ($q) => $code ? $q->where('code', $code) : $q->where('name', $name))->value('id')
                    ?? DB::table($table)->insertGetId(['company_id' => $row->company_id, 'code' => $code, 'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

                $linked += DB::table('drivers')->whereNull($fk)->where('company_id', $row->company_id)
                    ->where($codeCol, $row->code)->where($nameCol, $row->name)->update([$fk => $id]);
            }
        }

        echo "  {$linked} driver link(s) to lorry companies / groups made from the drivers' own text.\n";
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('lorry_company_id');
            $t->dropConstrainedForeignId('lorry_group_id');
        });
        Schema::dropIfExists('lorry_groups');
        Schema::dropIfExists('lorry_companies');
    }
};
