<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// The client's actual Area list of O & G TRANSPORT (KL) (their "Area Listing" of 14 Nov 2026, 183 areas; the names
// only, their codes are not used). Every name not in Master Data → Area yet is added (an area deleted earlier comes
// back). Nothing is removed here: the areas already there carry the price list's rows.
return new class extends Migration
{
    private const AREAS = [
    'ALOR GAJAH', 'AIR HITAM', 'AYER KEROH', 'AMPANG', 'BATU ANAM', 'BUKIT BAKRI', 'BAKAR BATU', 'BATANG BERJUNTAI', 'BATU BERENDAM', 'BATU CAVES',
    'BATANG KALI', 'BATU KIKIR', 'BATU PAHAT', 'BUKIT PASIR', 'BANDAR PENAWAR', 'BUKIT RAMBAI', 'BANDAR TENGGARA', 'BAHAU', 'BALAKONG', 'BANGI',
    'BANTING', 'BENUT', 'BERANANG', 'BESUT', "CHA'AH", 'CHERAS', 'CYBERJAYA', 'DAMANSARA', 'DENGKIL', 'DESA JAYA',
    'DESARU', 'ENDAU', 'FOREST CITY', 'GELANG PATAH', 'GEMAS', 'GEMENCHEH', 'GENTING HIGHLAND', 'GENUANG', 'GUA MUSANG', 'HULU LANGAT',
    'HULU SELANGOR', 'IJOK', 'IPOH', 'ISKANDAR PUTERI', 'JASIN', 'JOHOR BAHRU', 'JB OFFICE', 'JELEBU', 'JEMENTAH', 'JEMPOL',
    'JENJAROM', 'JERANTUT', 'JOHOR JAYA', 'KUALA LANGAT', 'KELANA JAYA',
    'KUALA KLAWANG', 'KUALA LIPIS', 'KUALA PILAH', 'KUALA ROMPIN', 'KUALA SELANGOR', 'KAHANG', 'KAJANG', 'KAPAR', 'KEDAH', 'KELANTAN',
    'KEMPAS', 'KEPONG', 'KUALA LUMPUR', 'KLANG', 'KLUANG', 'KOTA TINGGI', 'KUKUP', 'KULAI', 'LABIS', 'LANGKAWI',
    'LARKIN', 'LAYANG-LAYANG', 'LEDANG', 'LUKUT', 'LUMUT', 'MACHAP', 'MALIM JAYA', 'MAMBAU', 'MANTIN', 'MASAI',
    'MELAKA', 'MERLIMAU', 'MERSING', 'MERU', 'MOUNT AUSTIN', 'MUAR', 'NEGERI SEMBILAN', 'NILAI', 'NUSA JAYA', 'PASIR GUDANG',
    'PEKAN NENAS', 'PAGOH', 'PAHANG', 'PALOH', 'PANDAMARAN', 'PANDAN', 'PAROI', 'PORT DICKSON', 'PEDAS', 'PENANG',
    'PENGERANG', 'PERAK', 'PERLING', 'PERLIS', 'PERMAS JAYA',
    'PETALING JAYA', 'PLENTONG', 'PONTIAN', 'PORT KLANG', 'PRAI', 'PARIT JAWA', 'PARIT RAJA', 'PARIT SULONG', 'PUCHONG', 'PULAI',
    'PULAU INDAH', 'PUNCAK ALAM', 'PUTRAJAYA', 'RANTAU', 'RASAH', 'RAWANG', 'REMBAU', 'RENGGAM', 'RENGIT', 'ROMPIN',
    'SHAH ALAM', 'SABAK BERNAM', 'SUNGAI BESAR', 'SUNGAI BULOH', 'SETIA INDAH', 'SEELONG JAYA', 'SIMPANG PERTANG', 'SIMPANG RENGAM', 'SEDENAK', 'SEDILI',
    'SEGAMAT', 'SEGAMBUT', 'SELANGOR', 'SELAYANG', 'SEMENYIH', 'SENAI', 'SENAWANG', 'SENDAYAN', 'SENGARANG', 'SENGKANG',
    'SEPANG', 'SERDANG', 'SEREMBAN', 'SERENDAH', 'SUNGAI CHOH', 'SUNGAI PELAK', 'SUNGAI RENGGIT', 'SINGAPORE', 'SERI KEMBANGAN', 'SKUDAI',
    'SUBANG', 'TAMAN DAYA', 'TELOK GONG', 'TELUK INTAN', 'TANJUNG LANGSAT',
    'TANJUNG MALIM', 'TAMAN MOLEK', 'TANJUNG PELEPAS', 'TELOK RAMUNIA', 'TAIPING', 'TAMPIN', 'TAMPOI', 'TANGKAK', 'TEBRAU', 'TELOK PANGLIMA',
    'TERENGGANU', 'TANJUNG KARANG', 'TANJUNG KUPANG', 'TITI', 'TANJUNG BIN', 'ULU CHOH', 'ULU TIRAM', 'YONG PENG',
    ];

    public function up(): void
    {
        $existing = DB::table('locations')->get(['id', 'name', 'code', 'deleted_at']);
        $byName = $existing->keyBy(fn ($row) => Str::upper(trim($row->name)));
        $codes = $existing->pluck('code')->filter()->map(fn ($code) => Str::upper($code))->flip();

        foreach (self::AREAS as $name) {
            $row = $byName->get($name);

            if ($row) {
                if ($row->deleted_at !== null) {
                    DB::table('locations')->where('id', $row->id)->update(['deleted_at' => null, 'is_active' => true, 'updated_at' => now()]);
                }

                continue;
            }

            // written as the areas already there ("Johor Bahru"); JB stays in capitals
            $label = str_replace('Jb ', 'JB ', Str::title(Str::lower($name)));
            $base = Str::upper(trim(preg_replace('/[^A-Za-z0-9]+/', '_', $name), '_'));
            $code = $base;

            for ($n = 2; $codes->has($code); $n++) {
                $code = $base.'_'.$n;
            }

            $codes->put($code, true);
            DB::table('locations')->insert([
                'code' => $code, 'name' => $label, 'type' => 'delivery', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};
