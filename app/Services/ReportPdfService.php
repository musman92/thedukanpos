<?php

namespace App\Services;

use App\Support\BranchContext;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Response;

/**
 * Renders any tabular report into the shared A4 report document.
 *
 * Callers describe a report once — title, columns, rows — and every report
 * comes out with the same letterhead, meta strip, table styling and footer.
 */
class ReportPdfService
{
    /**
     * Column formats understood by the renderer. Anything else is treated as text.
     */
    public const FORMAT_MONEY = 'money';

    public const FORMAT_QTY = 'qty';

    public const FORMAT_INT = 'int';

    public const FORMAT_TEXT = 'text';

    /**
     * Beyond this many columns the page is flipped to landscape so cells
     * stay readable instead of wrapping into unreadable slivers.
     */
    protected const LANDSCAPE_COLUMN_THRESHOLD = 7;

    /**
     * @param  array{
     *     key?: string,
     *     title: string,
     *     subtitle?: ?string,
     *     meta?: array<int, array{label: string, value: mixed}>,
     *     columns: array<int, array{key: string, label: string, format?: string, align?: string, width?: string}>,
     *     rows: iterable<int, array<string, mixed>>,
     *     summary?: array<int, array{label: string, value: mixed, format?: string}>,
     *     totals?: array<string, mixed>,
     *     totals_label?: string,
     *     note?: ?string,
     *     orientation?: ?string,
     * }  $document
     */
    public function download(array $document): Response
    {
        return $this->render($document)->download($this->filename($document));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    public function stream(array $document): Response
    {
        return $this->render($document)->stream($this->filename($document));
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected function render(array $document): \Barryvdh\DomPDF\PDF
    {
        $columns = $this->normalizeColumns($document['columns'] ?? []);
        $rows = $this->normalizeRows($document['rows'] ?? [], $columns);
        $orientation = $document['orientation']
            ?? (count($columns) >= self::LANDSCAPE_COLUMN_THRESHOLD ? 'landscape' : 'portrait');

        return Pdf::loadView('pdf.report', [
            'company' => PdfBranding::company(),
            'title' => (string) $document['title'],
            'subtitle' => $document['subtitle'] ?? null,
            'meta' => $this->normalizeMeta($document),
            'columns' => $columns,
            'rows' => $rows,
            'summary' => $this->normalizeSummary($document['summary'] ?? []),
            'totals' => $this->normalizeTotals($document['totals'] ?? [], $columns),
            'totalsLabel' => $document['totals_label'] ?? 'Total',
            'note' => $document['note'] ?? null,
            'generatedAt' => format_company_datetime(now()),
        ])->setPaper('a4', $orientation);
    }

    /**
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array{key: string, label: string, format: string, align: string, width: ?string}>
     */
    protected function normalizeColumns(array $columns): array
    {
        $out = [];

        foreach ($columns as $column) {
            $format = $column['format'] ?? self::FORMAT_TEXT;

            $out[] = [
                'key' => (string) $column['key'],
                'label' => (string) $column['label'],
                'format' => $format,
                'align' => $column['align'] ?? $this->defaultAlign($format),
                'width' => $column['width'] ?? null,
            ];
        }

        return $out;
    }

    protected function defaultAlign(string $format): string
    {
        return in_array($format, [self::FORMAT_MONEY, self::FORMAT_QTY, self::FORMAT_INT], true)
            ? 'right'
            : 'left';
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<int, array<string, string>>
     */
    protected function normalizeRows(iterable $rows, array $columns): array
    {
        $out = [];

        foreach ($rows as $row) {
            $row = $this->toArray($row);
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column['key']] = $this->formatValue($row[$column['key']] ?? null, $column['format']);
            }

            $out[] = $cells;
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    protected function toArray(mixed $row): array
    {
        if (is_array($row)) {
            return $row;
        }

        if ($row instanceof Arrayable) {
            return $row->toArray();
        }

        return (array) $row;
    }

    public function formatValue(mixed $value, string $format): string
    {
        if ($value === null || $value === '') {
            return $format === self::FORMAT_TEXT ? '' : '—';
        }

        return match ($format) {
            self::FORMAT_MONEY => format_amount($value),
            self::FORMAT_INT => number_format((float) $value, 0, '.', ','),
            self::FORMAT_QTY => $this->formatQuantity((float) $value),
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }

    /**
     * Quantities keep up to 4 decimals but drop trailing zeros, so whole
     * units read as "12" rather than "12.0000".
     */
    protected function formatQuantity(float $value): string
    {
        $trimmed = rtrim(rtrim(number_format($value, 4, '.', ','), '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<int, array{label: string, value: string}>
     */
    protected function normalizeMeta(array $document): array
    {
        $meta = [];

        foreach ($document['meta'] ?? [] as $item) {
            if (($item['value'] ?? null) === null || $item['value'] === '') {
                continue;
            }

            $meta[] = [
                'label' => (string) $item['label'],
                'value' => (string) $item['value'],
            ];
        }

        return $meta;
    }

    /**
     * @param  array<int, array<string, mixed>>  $summary
     * @return array<int, array{label: string, value: string}>
     */
    protected function normalizeSummary(array $summary): array
    {
        $out = [];

        foreach ($summary as $item) {
            $out[] = [
                'label' => (string) $item['label'],
                'value' => $this->formatValue($item['value'] ?? null, $item['format'] ?? self::FORMAT_TEXT),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $totals
     * @param  array<int, array<string, mixed>>  $columns
     * @return array<string, string>|null
     */
    protected function normalizeTotals(array $totals, array $columns): ?array
    {
        if ($totals === []) {
            return null;
        }

        $out = [];

        foreach ($columns as $column) {
            $out[$column['key']] = array_key_exists($column['key'], $totals)
                ? $this->formatValue($totals[$column['key']], $column['format'])
                : '';
        }

        return $out;
    }

    /**
     * Build a summary/meta strip describing the branch and period a report covers.
     *
     * @return array<int, array{label: string, value: mixed}>
     */
    public static function periodMeta(?string $from = null, ?string $to = null, ?string $branchName = null): array
    {
        $meta = [];

        $meta[] = [
            'label' => 'Branch',
            'value' => $branchName ?? BranchContext::ensure()->name,
        ];

        if ($from || $to) {
            $meta[] = [
                'label' => 'Period',
                'value' => trim(format_company_date($from).' — '.format_company_date($to), ' —'),
            ];
        }

        return $meta;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    protected function filename(array $document): string
    {
        $base = $document['key'] ?? $document['title'];
        $safe = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', (string) $base));

        return trim($safe, '-').'-'.now()->format('Ymd-His').'.pdf';
    }
}
