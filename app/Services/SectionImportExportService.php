<?php

namespace App\Services;

use App\Models\Section;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SectionImportExportService
{
    /** @var list<string> */
    public const HEADERS = ['name', 'code', 'rack_name', 'rack_code', 'is_active'];

    /** @var list<array{name:string, code:string, rack_name:string, rack_code:string, is_active:string}> */
    public const SAMPLE_ROWS = [
        ['name' => 'Aisle A', 'code' => 'A', 'rack_name' => 'Shelf 1', 'rack_code' => 'A1', 'is_active' => '1'],
        ['name' => 'Aisle A', 'code' => 'A', 'rack_name' => 'Shelf 2', 'rack_code' => 'A2', 'is_active' => '1'],
        ['name' => 'Cold Store', 'code' => 'COLD', 'rack_name' => 'Bay 1', 'rack_code' => 'C1', 'is_active' => '1'],
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
                'entity' => 'sections',
            ];
        }

        return [...$this->upsertRows($header, $rows), 'entity' => 'sections'];
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'sections-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);

            Section::query()
                ->with(['racks' => fn ($q) => $q->orderBy('name')->orderBy('id')])
                ->orderBy('name')
                ->chunk(100, function ($sections) use ($out) {
                    foreach ($sections as $section) {
                        if ($section->racks->isEmpty()) {
                            fputcsv($out, [
                                $section->name,
                                $section->code,
                                '',
                                '',
                                $section->is_active ? 1 : 0,
                            ]);

                            continue;
                        }

                        foreach ($section->racks as $rack) {
                            fputcsv($out, [
                                $section->name,
                                $section->code,
                                $rack->name,
                                $rack->code,
                                $section->is_active ? 1 : 0,
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
        $sheet->setTitle('Sections');
        $this->writeHeaderRow($sheet, self::HEADERS);

        $rowNum = 2;
        Section::query()
            ->with(['racks' => fn ($q) => $q->orderBy('name')->orderBy('id')])
            ->orderBy('name')
            ->chunk(100, function ($sections) use ($sheet, &$rowNum) {
                foreach ($sections as $section) {
                    if ($section->racks->isEmpty()) {
                        $sheet->fromArray([
                            $section->name,
                            $section->code,
                            '',
                            '',
                            $section->is_active ? 1 : 0,
                        ], null, 'A'.$rowNum);
                        $rowNum++;

                        continue;
                    }

                    foreach ($section->racks as $rack) {
                        $sheet->fromArray([
                            $section->name,
                            $section->code,
                            $rack->name,
                            $rack->code,
                            $section->is_active ? 1 : 0,
                        ], null, 'A'.$rowNum);
                        $rowNum++;
                    }
                }
            });

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'sections-'.now()->format('Ymd-His').'.xlsx');
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
                    $row['rack_name'],
                    $row['rack_code'],
                    $row['is_active'],
                ]);
            }
            fclose($out);
        }, 'sections-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function sampleExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sections');
        $this->writeHeaderRow($sheet, self::HEADERS);

        foreach (self::SAMPLE_ROWS as $i => $row) {
            $sheet->fromArray([
                $row['name'],
                $row['code'],
                $row['rack_name'],
                $row['rack_code'],
                $row['is_active'],
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'sections-import-sample.xlsx');
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

        /** @var array<string, array{name:string, code:string|null, is_active:bool, racks:list<array{name:string, code:string|null}>, rows:list<int>}> $groups */
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
                    'racks' => [],
                    'rows' => [],
                ];
            }

            $groups[$groupKey]['rows'][] = $excelRow;

            $rackName = trim((string) ($data['rack_name'] ?? ''));
            if ($rackName !== '') {
                $groups[$groupKey]['racks'][] = [
                    'name' => $rackName,
                    'code' => trim((string) ($data['rack_code'] ?? '')),
                ];
            }
        }

        $service = app(SectionService::class);

        foreach ($groups as $group) {
            $rowLabel = $group['rows'][0] ?? 0;

            try {
                $existing = null;
                if ($group['code']) {
                    $existing = Section::query()
                        ->whereRaw('UPPER(code) = ?', [$group['code']])
                        ->first();
                }
                if (! $existing) {
                    $existing = Section::query()
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($group['name'])])
                        ->first();
                }

                $racksPayload = [];
                foreach ($group['racks'] as $rack) {
                    $racksPayload[] = [
                        'id' => null,
                        'name' => $rack['name'],
                        'code' => $rack['code'] !== '' ? $rack['code'] : null,
                        'is_active' => true,
                    ];
                }

                if ($existing && $racksPayload !== []) {
                    $existing->load('racks');
                    foreach ($racksPayload as $i => $rack) {
                        $match = $existing->racks->first(
                            fn ($r) => mb_strtolower($r->name) === mb_strtolower($rack['name']),
                        );
                        if ($match) {
                            $racksPayload[$i]['id'] = $match->id;
                        }
                    }
                }

                if ($existing) {
                    $service->update($existing, [
                        'name' => $group['name'],
                        'code' => $group['code'] ?? $existing->code,
                        'is_active' => $group['is_active'],
                        'racks' => $racksPayload !== []
                            ? $racksPayload
                            : $existing->racks->map(fn ($r) => [
                                'id' => $r->id,
                                'name' => $r->name,
                                'code' => $r->code,
                                'is_active' => $r->is_active,
                            ])->all(),
                    ]);
                    $updated++;
                } else {
                    $service->create([
                        'name' => $group['name'],
                        'code' => $group['code'],
                        'is_active' => $group['is_active'],
                        'racks' => $racksPayload,
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
        $tempPath = tempnam(sys_get_temp_dir(), 'sections_').'.xlsx';
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
