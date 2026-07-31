<?php

namespace App\Services;

use App\Models\Variation;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VariationImportExportService
{
    /** @var list<string> */
    public const HEADERS = ['name', 'code', 'option_name', 'option_code', 'is_active'];

    /** @var list<array{name:string, code:string, option_name:string, option_code:string, is_active:string}> */
    public const SAMPLE_ROWS = [
        ['name' => 'Size', 'code' => 'SIZE', 'option_name' => 'Small', 'option_code' => 'S', 'is_active' => '1'],
        ['name' => 'Size', 'code' => 'SIZE', 'option_name' => 'Medium', 'option_code' => 'M', 'is_active' => '1'],
        ['name' => 'Size', 'code' => 'SIZE', 'option_name' => 'Large', 'option_code' => 'L', 'is_active' => '1'],
        ['name' => 'Color', 'code' => 'COLOR', 'option_name' => 'Red', 'option_code' => 'RED', 'is_active' => '1'],
        ['name' => 'Color', 'code' => 'COLOR', 'option_name' => 'Blue', 'option_code' => 'BLU', 'is_active' => '1'],
    ];

    public const MAX_ROWS = 1000;

    /**
     * @return StreamedResponse|BinaryFileResponse
     */
    public function export(string $format = 'csv')
    {
        $format = strtolower($format);

        return match ($format) {
            'xlsx', 'excel' => $this->exportExcel(),
            default => $this->exportCsv(),
        };
    }

    /**
     * @return StreamedResponse|BinaryFileResponse
     */
    public function sample(string $format = 'csv')
    {
        $format = strtolower($format);

        return match ($format) {
            'xlsx', 'excel' => $this->sampleExcel(),
            default => $this->sampleCsv(),
        };
    }

    /**
     * @return array{created:int, updated:int, skipped:int, errors:list<array{row:int, message:string}>, entity:string}
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');

        [$header, $rows] = in_array($extension, ['xlsx', 'xls'], true)
            ? $this->readExcel($file)
            : $this->readCsv($file);

        $header = $this->normalizeHeader($header);

        if (! in_array('name', $header, true)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 1,
                    'message' => 'Missing required "name" column. Download the sample file for the expected format.',
                ]],
                'entity' => 'variations',
            ];
        }

        return [...$this->upsertRows($header, $rows), 'entity' => 'variations'];
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'variations-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);

            Variation::query()
                ->with(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
                ->orderBy('name')
                ->chunk(100, function ($variations) use ($out) {
                    foreach ($variations as $variation) {
                        if ($variation->options->isEmpty()) {
                            fputcsv($out, [
                                $variation->name,
                                $variation->code,
                                '',
                                '',
                                $variation->is_active ? 1 : 0,
                            ]);

                            continue;
                        }

                        foreach ($variation->options as $option) {
                            fputcsv($out, [
                                $variation->name,
                                $variation->code,
                                $option->name,
                                $option->code,
                                $variation->is_active ? 1 : 0,
                            ]);
                        }
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function exportExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Variations');
        $this->writeHeaderRow($sheet, self::HEADERS);

        $rowNum = 2;
        Variation::query()
            ->with(['options' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')])
            ->orderBy('name')
            ->chunk(100, function ($variations) use ($sheet, &$rowNum) {
                foreach ($variations as $variation) {
                    if ($variation->options->isEmpty()) {
                        $sheet->fromArray([
                            $variation->name,
                            $variation->code,
                            '',
                            '',
                            $variation->is_active ? 1 : 0,
                        ], null, 'A'.$rowNum);
                        $rowNum++;

                        continue;
                    }

                    foreach ($variation->options as $option) {
                        $sheet->fromArray([
                            $variation->name,
                            $variation->code,
                            $option->name,
                            $option->code,
                            $variation->is_active ? 1 : 0,
                        ], null, 'A'.$rowNum);
                        $rowNum++;
                    }
                }
            });

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'variations-'.now()->format('Ymd-His').'.xlsx');
    }

    protected function sampleCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);
            foreach (self::SAMPLE_ROWS as $row) {
                fputcsv($out, [
                    $row['name'],
                    $row['code'],
                    $row['option_name'],
                    $row['option_code'],
                    $row['is_active'],
                ]);
            }
            fclose($out);
        }, 'variations-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function sampleExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Variations');
        $this->writeHeaderRow($sheet, self::HEADERS);

        foreach (self::SAMPLE_ROWS as $i => $row) {
            $sheet->fromArray([
                $row['name'],
                $row['code'],
                $row['option_name'],
                $row['option_code'],
                $row['is_active'],
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'variations-import-sample.xlsx');
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    protected function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => 'Unable to read the uploaded file.',
            ]);
        }

        $header = fgetcsv($handle) ?: [];
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }
            $rows[] = $row;
        }

        fclose($handle);

        return [$header, $rows];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    protected function readExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        if ($raw === []) {
            return [[], []];
        }

        $header = array_shift($raw) ?: [];
        $rows = [];
        foreach ($raw as $row) {
            if (! is_array($row) || $this->rowIsEmpty($row)) {
                continue;
            }
            $rows[] = $row;
        }

        return [$header, $rows];
    }

    /**
     * @param  list<mixed>  $header
     * @return list<string>
     */
    protected function normalizeHeader(array $header): array
    {
        return array_map(function ($h) {
            $value = strtolower(trim((string) $h));
            $value = preg_replace('/^\xef\xbb\xbf/', '', $value) ?? $value;

            return $value;
        }, $header);
    }

    /**
     * Groups rows by variation, then upserts type + replaces options for that type when any option rows present.
     *
     * @param  list<string>  $header
     * @param  list<list<mixed>>  $rows
     * @return array{created:int, updated:int, skipped:int, errors:list<array{row:int, message:string}>}
     */
    protected function upsertRows(array $header, array $rows): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        /** @var array<string, array{name:string, code:string|null, is_active:bool, options:list<array{name:string, code:string|null}>, rows:list<int>}> $groups */
        $groups = [];

        foreach ($rows as $index => $row) {
            $excelRow = $index + 2;

            if (count($rows) > self::MAX_ROWS && $index >= self::MAX_ROWS) {
                $errors[] = [
                    'row' => $excelRow,
                    'message' => 'Import limit reached ('.self::MAX_ROWS.' rows per file).',
                ];
                break;
            }

            $data = @array_combine($header, array_pad($row, count($header), null));
            if (! is_array($data)) {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Could not read this row.'];

                continue;
            }

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Name is required.'];

                continue;
            }

            $codeInput = trim((string) ($data['code'] ?? ''));
            $groupKey = $codeInput !== ''
                ? 'code:'.strtoupper($codeInput)
                : 'name:'.mb_strtolower($name);

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'name' => $name,
                    'code' => $codeInput !== '' ? strtoupper($codeInput) : null,
                    'is_active' => array_key_exists('is_active', $data) && $data['is_active'] !== null && $data['is_active'] !== ''
                        ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                        : true,
                    'options' => [],
                    'rows' => [],
                ];
            }

            $groups[$groupKey]['rows'][] = $excelRow;

            $optionName = trim((string) ($data['option_name'] ?? ''));
            if ($optionName !== '') {
                $groups[$groupKey]['options'][] = [
                    'name' => $optionName,
                    'code' => trim((string) ($data['option_code'] ?? '')),
                ];
            }
        }

        $service = app(VariationService::class);

        foreach ($groups as $group) {
            $rowLabel = $group['rows'][0] ?? 0;

            try {
                $existing = null;
                if ($group['code']) {
                    $existing = Variation::query()
                        ->whereRaw('UPPER(code) = ?', [$group['code']])
                        ->first();
                }
                if (! $existing) {
                    $existing = Variation::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($group['name'])])
                        ->first();
                }

                $optionsPayload = [];
                foreach ($group['options'] as $i => $opt) {
                    $optionsPayload[] = [
                        'id' => null,
                        'name' => $opt['name'],
                        'code' => $opt['code'] !== '' ? $opt['code'] : null,
                        'sort_order' => $i,
                        'is_active' => true,
                    ];
                }

                // Keep existing option ids when updating by matching name (so we don't thrash IDs unnecessarily).
                if ($existing && $optionsPayload !== []) {
                    $existing->load('options');
                    foreach ($optionsPayload as $i => $opt) {
                        $match = $existing->options->first(
                            fn ($o) => mb_strtolower($o->name) === mb_strtolower($opt['name']),
                        );
                        if ($match) {
                            $optionsPayload[$i]['id'] = $match->id;
                        }
                    }
                }

                if ($existing) {
                    $service->update($existing, [
                        'name' => $group['name'],
                        'code' => $group['code'] ?? $existing->code,
                        'is_active' => $group['is_active'],
                        'options' => $optionsPayload !== [] || $group['options'] !== []
                            ? $optionsPayload
                            : $existing->options->map(fn ($o) => [
                                'id' => $o->id,
                                'name' => $o->name,
                                'code' => $o->code,
                                'sort_order' => $o->sort_order,
                                'is_active' => $o->is_active,
                            ])->all(),
                    ]);
                    $updated++;
                } else {
                    $service->create([
                        'name' => $group['name'],
                        'code' => $group['code'],
                        'is_active' => $group['is_active'],
                        'options' => $optionsPayload,
                    ]);
                    $created++;
                }
            } catch (ValidationException $e) {
                $skipped++;
                $errors[] = [
                    'row' => $rowLabel,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row' => $rowLabel,
                    'message' => 'Failed to save: '.$e->getMessage(),
                ];
            }
        }

        return compact('created', 'updated', 'skipped', 'errors');
    }

    /**
     * @param  list<mixed>  $row
     */
    protected function rowIsEmpty(array $row): bool
    {
        return count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0;
    }

    /**
     * @param  list<string>  $headers
     */
    protected function writeHeaderRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $headers): void
    {
        foreach ($headers as $columnIndex => $header) {
            $cell = $sheet->getCell([$columnIndex + 1, 1]);
            $cell->setValue($header);
            $cell->getStyle()->getFont()->setBold(true);
            $cell->getStyle()->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE5E7EB');
        }
    }

    protected function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): BinaryFileResponse
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'variations_').'.xlsx';
        (new Xlsx($spreadsheet))->save($tempPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()
            ->download($tempPath, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * @return array<string, mixed>
     */
    public static function fileRules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120',
                'extensions:csv,txt,xlsx,xls',
            ],
        ];
    }
}
