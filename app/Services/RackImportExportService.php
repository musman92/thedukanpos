<?php

namespace App\Services;

use App\Models\Rack;
use App\Models\Section;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RackImportExportService
{
    /** @var list<string> */
    public const HEADERS = ['section_code', 'name', 'code', 'is_active'];

    /** @var list<array{section_code:string, name:string, code:string, is_active:string}> */
    public const SAMPLE_ROWS = [
        ['section_code' => 'A', 'name' => 'Shelf 1', 'code' => 'A1', 'is_active' => '1'],
        ['section_code' => 'A', 'name' => 'Shelf 2', 'code' => 'A2', 'is_active' => '1'],
        ['section_code' => 'COLD', 'name' => 'Bay 1', 'code' => 'C1', 'is_active' => '1'],
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

        if (! in_array('name', $header, true) || ! in_array('section_code', $header, true)) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 1,
                    'message' => 'Missing required "section_code" and/or "name" columns. Download the sample file for the expected format.',
                ]],
                'entity' => 'racks',
            ];
        }

        return [...$this->upsertRows($header, $rows), 'entity' => 'racks'];
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'racks-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);

            Rack::query()
                ->with('section:id,code')
                ->orderBy('name')
                ->chunk(200, function ($racks) use ($out) {
                    foreach ($racks as $rack) {
                        fputcsv($out, [
                            $rack->section?->code,
                            $rack->name,
                            $rack->code,
                            $rack->is_active ? 1 : 0,
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function exportExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Racks');
        $this->writeHeaderRow($sheet, self::HEADERS);

        $rowNum = 2;
        Rack::query()
            ->with('section:id,code')
            ->orderBy('name')
            ->chunk(200, function ($racks) use ($sheet, &$rowNum) {
                foreach ($racks as $rack) {
                    $sheet->fromArray([
                        $rack->section?->code,
                        $rack->name,
                        $rack->code,
                        $rack->is_active ? 1 : 0,
                    ], null, 'A'.$rowNum);
                    $rowNum++;
                }
            });

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'racks-'.now()->format('Ymd-His').'.xlsx');
    }

    protected function sampleCsv(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);
            foreach (self::SAMPLE_ROWS as $row) {
                fputcsv($out, [$row['section_code'], $row['name'], $row['code'], $row['is_active']]);
            }
            fclose($out);
        }, 'racks-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function sampleExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Racks');
        $this->writeHeaderRow($sheet, self::HEADERS);

        foreach (self::SAMPLE_ROWS as $i => $row) {
            $sheet->fromArray([$row['section_code'], $row['name'], $row['code'], $row['is_active']], null, 'A'.($i + 2));
        }

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'racks-import-sample.xlsx');
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
        $service = app(RackService::class);

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

            $sectionCode = trim((string) ($data['section_code'] ?? ''));
            if ($sectionCode === '') {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Section code is required.'];

                continue;
            }

            $section = Section::query()
                ->whereRaw('UPPER(code) = ?', [strtoupper($sectionCode)])
                ->first();

            if (! $section) {
                $skipped++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => "Section code \"{$sectionCode}\" not found. Create the section first.",
                ];

                continue;
            }

            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Name is required.'];

                continue;
            }

            $codeInput = trim((string) ($data['code'] ?? ''));
            $isActive = array_key_exists('is_active', $data) && $data['is_active'] !== null && $data['is_active'] !== ''
                ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true;

            try {
                $existing = null;
                if ($codeInput !== '') {
                    $existing = Rack::query()
                        ->where('section_id', $section->id)
                        ->whereRaw('UPPER(code) = ?', [strtoupper($codeInput)])
                        ->first();
                }
                if (! $existing) {
                    $existing = Rack::query()
                        ->where('section_id', $section->id)
                        ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                        ->first();
                }

                if ($existing) {
                    $service->update($existing, [
                        'section_id' => $section->id,
                        'name' => $name,
                        'code' => $codeInput !== '' ? $codeInput : $existing->code,
                        'is_active' => $isActive,
                    ]);
                    $updated++;
                } else {
                    $service->create([
                        'section_id' => $section->id,
                        'name' => $name,
                        'code' => $codeInput !== '' ? $codeInput : null,
                        'is_active' => $isActive,
                    ]);
                    $created++;
                }
            } catch (ValidationException $e) {
                $skipped++;
                $errors[] = [
                    'row' => $excelRow,
                    'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                ];
            } catch (\Throwable $e) {
                $skipped++;
                $errors[] = [
                    'row' => $excelRow,
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
        $tempPath = tempnam(sys_get_temp_dir(), 'racks_').'.xlsx';
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
