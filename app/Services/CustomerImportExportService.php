<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImportExportService
{
    /** @var list<string> */
    public const HEADERS = ['name', 'code', 'phone', 'email', 'address', 'opening_balance', 'is_active'];

    /** @var list<array{name:string, code:string, phone:string, email:string, address:string, opening_balance:string, is_active:string}> */
    public const SAMPLE_ROWS = [
        [
            'name' => 'Walk-in Customer',
            'code' => 'C01',
            'phone' => '03001234567',
            'email' => '',
            'address' => '',
            'opening_balance' => '0',
            'is_active' => '1',
        ],
        [
            'name' => 'Ahmed Traders',
            'code' => '',
            'phone' => '03007654321',
            'email' => 'ahmed@example.com',
            'address' => 'Main Bazaar',
            'opening_balance' => '1500.00',
            'is_active' => '1',
        ],
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
     * @return array{created:int, updated:int, skipped:int, errors:list<array{row:int, message:string}>, entity?:string}
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
                'entity' => 'customers',
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => [[
                    'row' => 1,
                    'message' => 'Missing required "name" column. Download the sample file for the expected format.',
                ]],
            ];
        }

        $result = $this->upsertRows($header, $rows);
        $result['entity'] = 'customers';

        return $result;
    }

    protected function exportCsv(): StreamedResponse
    {
        $filename = 'customers-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, self::HEADERS);

            Customer::query()->orderBy('name')->chunk(200, function ($customers) use ($out) {
                foreach ($customers as $customer) {
                    fputcsv($out, $this->rowValues($customer));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function exportExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');
        $this->writeHeaderRow($sheet, self::HEADERS);

        $rowNum = 2;
        Customer::query()->orderBy('name')->chunk(200, function ($customers) use ($sheet, &$rowNum) {
            foreach ($customers as $customer) {
                $sheet->fromArray($this->rowValues($customer), null, 'A'.$rowNum);
                $rowNum++;
            }
        });

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'customers-'.now()->format('Ymd-His').'.xlsx');
    }

    /**
     * @return list<string|float|int>
     */
    protected function rowValues(Customer $customer): array
    {
        return [
            $customer->name,
            $customer->code,
            $customer->phone,
            $customer->email,
            $customer->address,
            (float) $customer->balance,
            $customer->is_active ? 1 : 0,
        ];
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
                    $row['phone'],
                    $row['email'],
                    $row['address'],
                    $row['opening_balance'],
                    $row['is_active'],
                ]);
            }
            fclose($out);
        }, 'customers-import-sample.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function sampleExcel(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customers');
        $this->writeHeaderRow($sheet, self::HEADERS);

        foreach (self::SAMPLE_ROWS as $i => $row) {
            $sheet->fromArray([
                $row['name'],
                $row['code'],
                $row['phone'],
                $row['email'],
                $row['address'],
                $row['opening_balance'],
                $row['is_active'],
            ], null, 'A'.($i + 2));
        }

        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return $this->downloadSpreadsheet($spreadsheet, 'customers-import-sample.xlsx');
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
            $value = str_replace([' ', '-'], '_', $value);

            return match ($value) {
                'balance', 'openingbalance', 'opening_bal', 'credit_balance', 'amount_owed' => 'opening_balance',
                default => $value,
            };
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

            if (mb_strlen($name) > 255) {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Name is too long (max 255 characters).'];

                continue;
            }

            $codeInput = trim((string) ($data['code'] ?? ''));
            if (mb_strlen($codeInput) > 50) {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Code is too long (max 50 characters).'];

                continue;
            }

            $openingRaw = $data['opening_balance'] ?? null;
            $hasOpening = $openingRaw !== null && trim((string) $openingRaw) !== '';
            $openingBalance = $hasOpening ? (float) $openingRaw : 0.0;
            if ($hasOpening && $openingBalance < 0) {
                $skipped++;
                $errors[] = ['row' => $excelRow, 'message' => 'Opening balance cannot be negative.'];

                continue;
            }

            $isActive = array_key_exists('is_active', $data) && $data['is_active'] !== null && $data['is_active'] !== ''
                ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN)
                : true;

            $attrs = [
                'name' => $name,
                'phone' => $this->nullableString($data['phone'] ?? null),
                'email' => $this->nullableString($data['email'] ?? null),
                'address' => $this->nullableString($data['address'] ?? null),
                'is_active' => $isActive,
            ];

            try {
                $existingByName = Customer::query()
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                    ->first();

                if ($codeInput !== '') {
                    $code = strtoupper($codeInput);
                    $customer = Customer::query()
                        ->whereRaw('UPPER(code) = ?', [$code])
                        ->first();

                    if ($customer) {
                        if ($existingByName && $existingByName->id !== $customer->id) {
                            $skipped++;
                            $errors[] = [
                                'row' => $excelRow,
                                'message' => 'Customer name is already used by another customer.',
                            ];

                            continue;
                        }

                        $update = $attrs;
                        if ($hasOpening) {
                            $update['balance'] = $openingBalance;
                        }
                        $customer->update($update);
                        $updated++;
                    } else {
                        if ($existingByName) {
                            $skipped++;
                            $errors[] = [
                                'row' => $excelRow,
                                'message' => 'Customer name is already taken.',
                            ];

                            continue;
                        }

                        Customer::query()->create([
                            ...$attrs,
                            'code' => $code,
                            'balance' => $openingBalance,
                        ]);
                        $created++;
                    }

                    continue;
                }

                if ($existingByName) {
                    $update = $attrs;
                    if ($hasOpening) {
                        $update['balance'] = $openingBalance;
                    }
                    $existingByName->update($update);
                    $updated++;
                } else {
                    Customer::query()->create([
                        ...$attrs,
                        'code' => Customer::resolveCode(null),
                        'balance' => $openingBalance,
                    ]);
                    $created++;
                }
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

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
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
        $tempPath = tempnam(sys_get_temp_dir(), 'customers_').'.xlsx';
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
