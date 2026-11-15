<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Master Data → Area shows only the client's Area list of O & G TRANSPORT (KL) (user, 16 Nov 2026: "clear all, only
// what is listed in the PDF"). Every area that is not one of the 183 names is taken off the list (soft deleted): the
// orders, CSNs and documents that used it still show its name, and its price rows stay in the database but can no
// longer be picked. Adding an area again under the same name brings it back with its prices.
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
        $keep = array_flip(self::AREAS);
        $remove = DB::table('locations')->whereNull('deleted_at')->get(['id', 'name'])
            ->reject(fn ($row) => isset($keep[Str::upper(trim($row->name))]))
            ->pluck('id');

        if ($remove->isNotEmpty()) {
            DB::table('locations')->whereIn('id', $remove)->update(['deleted_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        //
    }
};
