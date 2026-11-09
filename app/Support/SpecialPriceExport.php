<?php

namespace App\Support;

use App\Domains\MasterData\Models\Branch;
use App\Domains\MasterData\Models\Customer;
use App\Domains\MasterData\Models\Uom;
use App\Domains\MasterData\Models\UomRateTier;
use App\Enums\DropOffType;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A customer's special prices as an Excel file (user, 10 Nov 2026), in the layout of the client's "O&G Customer
 * Pricing Setup Template - Special Price": one sheet named by the debtor code, the customer's name and debtor code
 * on top, then the template's column titles on the "Record No." row and one row per price row (product, route,
 * drop-off type, minimum charges, dates and the quantity ranges A–E). The template's columns come first, in its
 * order, so the file can be imported back (Customers → Import special price); the system's extra columns (extra
 * drop-off charge, fixed charges, where the price came from) follow them.
 */
class SpecialPriceExport
{
    private const RANGES = ['a', 'b', 'c', 'd', 'e'];

    /** The row of the column titles (the template's: row 6, data from row 7). */
    private const HEADER_ROW = 6;

    public static function filename(Customer $customer): string
    {
        return 'Special Price - '.Str::slug($customer->code ?: 'customer').' - '.Str::limit(preg_replace('/[^\w\s&.-]/u', '', (string) $customer->company_name), 60, '').' - '.now()->format('Y-m-d').'.xlsx';
    }

    public static function download(Customer $customer): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'og-sp-').'.xlsx';
        static::write($customer, $path);

        return response()->download($path, static::filename($customer), ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])->deleteFileAfterSend();
    }

    /** Writes the workbook to a file. */
    public static function write(Customer $customer, string $path): void
    {
        static::writeWorkbook($customer, $path);
        static::fixSheetView($path);
    }

    /**
     * OpenSpout writes the sheet view's "show zeros" as showZeroes, which is not an Excel attribute (Excel may offer
     * to repair the file, other readers refuse it): the right name is showZeros.
     */
    private static function fixSheetView(string $path): void
    {
        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return;
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);

            if (str_starts_with($name, 'xl/worksheets/') && str_ends_with($name, '.xml')) {
                $xml = (string) $zip->getFromIndex($i);

                if (str_contains($xml, 'showZeroes=')) {
                    $zip->addFromString($name, str_replace('showZeroes=', 'showZeros=', $xml));
                }
            }
        }

        $zip->close();
    }

    private static function writeWorkbook(Customer $customer, string $path): void
    {
        $rows = static::rows($customer);
        $hasFixed = collect($rows)->contains(fn (array $row) => collect(self::RANGES)->contains(fn ($l) => filled($row["range_{$l}_fixed"] ?? null)));
        $headers = static::headers($hasFixed);
        $lastColumn = count($headers) - 1;

        $options = new Options();
        $widths = [10, 14, 15, 30, 16, 16, 20, 11, 11, 11, 11, 11, 11, 11, 11, 18, 20, 16, 14, 16, 14, 13, 13];
        foreach ($widths as $i => $width) {
            $options->setColumnWidth($width, $i + 1);
        }
        $options->setColumnWidthForRange(11, count($widths) + 1, $lastColumn + 1);
        $options->mergeCells(0, 3, min(22, $lastColumn), 3);
        $options->mergeCells(0, 4, min(22, $lastColumn), 4);

        $writer = new Writer($options);
        $writer->openToFile($path);
        $sheet = $writer->getCurrentSheet();
        $sheet->setName(Str::limit(preg_replace('/[\\\\\/?*\[\]:]/', '-', $customer->code ?: 'Special price'), 31, ''));
        $sheet->setSheetView((new SheetView())->setFreezeRow(self::HEADER_ROW + 1)->setFreezeColumn('E'));

        $title = (new Style())->setFontBold()->setFontSize(16);
        $name = (new Style())->setFontBold()->setFontSize(12);
        $note = (new Style())->setFontItalic()->setFontColor('595959')->setShouldWrapText(false);
        $head = (new Style())->setFontBold()->setFontColor('FFFFFF')->setBackgroundColor('1F3864')->setShouldWrapText()->setCellVerticalAlignment('center');
        $cell = (new Style())->setBackgroundColor('FFF2CC');
        $money = (new Style())->setBackgroundColor('FFF2CC')->setFormat('#,##0.00');
        $date = (new Style())->setBackgroundColor('FFF2CC')->setFormat('dd/mm/yyyy');

        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['O&G Customer Special Price List'], $title));
        $writer->addRow(Row::fromValues([trim(($customer->company_name ?? '').' · Debtor code '.($customer->code ?? '—'))], $name));
        $writer->addRow(Row::fromValues([
            'Exported '.now()->format('d/m/Y H:i').(auth()->user() ? ' by '.auth()->user()->name : '').' · '.count($rows).' price row(s), each with up to five quantity ranges (A–E) · prices in RM.',
        ], $note));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($headers, $head));

        $moneyColumns = ['pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge', 'extra_drop_charge'];

        foreach ($rows as $index => $row) {
            $cells = [];

            foreach (static::fields($hasFixed) as $field) {
                $value = $field === 'record_no' ? $index + 1 : ($row[$field] ?? null);

                if ($value === null || $value === '') {
                    $cells[] = Cell::fromValue(null, $cell);
                } elseif (in_array($field, ['effective_from', 'expires_on'], true)) {
                    $cells[] = Cell::fromValue(\Carbon\Carbon::parse($value)->toDateTimeImmutable(), $date);
                } elseif (in_array($field, $moneyColumns, true) || str_ends_with($field, '_price') || str_ends_with($field, '_fixed')) {
                    $cells[] = Cell::fromValue((float) $value, $money);
                } elseif (is_numeric($value) && ! in_array($field, ['customer_code'], true)) {
                    $cells[] = Cell::fromValue((float) $value + 0, $cell);
                } else {
                    $cells[] = Cell::fromValue((string) $value, $cell);
                }
            }

            $writer->addRow(new Row($cells));
        }

        if ($rows === []) {
            $writer->addRow(Row::fromValues(['', 'No special price for this customer.'], $note));
        }

        $writer->close();
    }

    /**
     * The customer's special price rows with the template's fields: branch, customer, the product's category,
     * sub-category, item and size limits, the route, drop-off type, minimum charges, dates and the ranges A–E.
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(Customer $customer): array
    {
        $code = trim((string) $customer->code);

        if ($code === '') {
            return [];
        }

        $branches = Branch::query()->pluck('code', 'id');
        $products = Uom::query()->get()->keyBy('id');

        return collect(UomPriceRows::fromTiers(
            UomRateTier::query()->where('customer_code', $code)->with(['uom:id,name', 'location:id,name'])->get()
        ))->map(function (array $row) use ($customer, $code, $branches, $products): array {
            $uom = $products->get($row['uom_id']);
            $category = $uom?->category ?: Uom::MEASUREMENT;
            $branchCode = $row['branch_id'] ? $branches->get($row['branch_id']) : null;

            return $row + [
                'branch' => $branchCode ? 'O & G '.$branchCode : '',
                'customer_code' => $code,
                'customer_name' => (string) $customer->company_name,
                'category' => Uom::CATEGORIES[$category] ?? 'Measurement',
                'sub_category' => $category === Uom::LORRY ? 'Chartered Lorry' : (string) ($uom?->sub_category ?? ''),
                'item' => (string) ($uom?->base_item ?: $uom?->name ?? ''),
                'min_lwh' => $uom?->min_lwh,
                'max_lwh' => $uom?->max_lwh,
                'min_length' => $uom?->min_length,
                'max_length' => $uom?->max_length,
                'max_width' => $uom?->max_width,
                'max_height' => $uom?->max_height,
                'min_weight' => $uom?->min_weight,
                'max_weight' => $uom?->max_weight,
                'drop_off_location' => (string) ($row['location'] ?? ''),
                'drop_off_type_label' => DropOffType::tryFrom((string) $row['drop_off_type'])?->getLabel() ?? DropOffType::Default->getLabel(),
                'product_name' => (string) ($uom?->name ?? ''),
            ];
        })->sortBy([
            fn (array $a, array $b) => strcmp($a['category'], $b['category']),
            fn (array $a, array $b) => strcmp($a['product_name'], $b['product_name']),
            fn (array $a, array $b) => strcmp($a['drop_off_location'], $b['drop_off_location']),
        ])->values()->all();
    }

    /** Field of each column, in order: the template's columns, then the system's own. @return list<string> */
    private static function fields(bool $hasFixed): array
    {
        $fields = [
            'record_no', 'branch', 'customer_code', 'customer_name', 'category', 'sub_category', 'item',
            'min_lwh', 'max_lwh', 'min_length', 'max_length', 'max_width', 'max_height', 'min_weight', 'max_weight',
            'pickup_location', 'drop_off_location', 'drop_off_type_label',
            'pickup_min_charge', 'construction_min_charge', 'drop_off_min_charge', 'effective_from', 'expires_on',
        ];

        foreach (self::RANGES as $l) {
            array_push($fields, "range_{$l}_from", "range_{$l}_to", "range_{$l}_price");
        }

        $fields[] = 'extra_drop_charge';

        if ($hasFixed) {
            foreach (self::RANGES as $l) {
                $fields[] = "range_{$l}_fixed";
            }
        }

        $fields[] = 'product_name';
        $fields[] = 'source';

        return $fields;
    }

    /** The column titles (the template's, word for word, then the system's own). @return list<string> */
    private static function headers(bool $hasFixed): array
    {
        $titles = [
            'Record No.', 'Branch', 'Customer Code', 'Customer Name', 'Category', 'Sub-Category', 'Item',
            'Min L+W+H (CM)', 'Max L+W+H (CM)', 'Min Length, L (CM)', 'Max Length, L (CM)', 'Max Width, W (CM)', 'Max Height, H (CM)',
            'Min Weight (KG)', 'Max Weight (KG)', 'Pickup Location', 'Drop-Off Location', 'Drop-Off Type',
            'Pick Up Minimum Charge (RM)', 'Construction Site Drop Off Minimum Charge (RM)', 'Drop Off Minimum Charge (RM)',
            'Effective Date', 'Expiry Date',
        ];

        foreach (self::RANGES as $l) {
            $u = strtoupper($l);
            array_push($titles, "Quantity From {$u}", "Quantity To {$u}", "Price for This Range (RM) {$u}");
        }

        $titles[] = 'Extra Drop-Off Charge (RM)';

        if ($hasFixed) {
            foreach (self::RANGES as $l) {
                $titles[] = 'Fixed Charge (RM) '.strtoupper($l);
            }
        }

        $titles[] = 'Product (system name)';
        $titles[] = 'From';

        return $titles;
    }
}
