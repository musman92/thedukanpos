<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\OperationalReportService;
use App\Support\BranchContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(OperationalReportService $reports): JsonResponse
    {
        return response()->json([
            'data' => $reports->catalog(),
        ]);
    }

    public function show(Request $request, string $report, OperationalReportService $reports): JsonResponse
    {
        $doc = $reports->document($report, $request->query());

        $sections = array_map(
            fn (array $section) => $section + [
                'totals' => $reports->columnTotals($section['rows'], $section['columns']),
            ],
            $doc['sections'] ?? [[
                'title' => null,
                'columns' => $doc['columns'],
                'rows' => $doc['rows'],
            ]],
        );

        return response()->json([
            'data' => $doc['rows'],
            'meta' => [
                'from' => $doc['filters']['from'] ?? null,
                'to' => $doc['filters']['to'] ?? null,
                'category_id' => $doc['filters']['category_id'] ?? null,
                'customer_id' => $doc['filters']['customer_id'] ?? null,
                'order_number' => $doc['filters']['order_number'] ?? null,
                'type' => $doc['filters']['type'] ?? null,
                'party_id' => $doc['filters']['party_id'] ?? null,
                'branch_id' => $doc['filters']['branch_id'] ?? null,
                'money_source_id' => $doc['filters']['money_source_id'] ?? null,
                'direction' => $doc['filters']['direction'] ?? null,
                'include_transfers' => $doc['filters']['include_transfers'] ?? null,
                'total' => $doc['total'] ?? count($doc['rows']),
                'branch' => $doc['branch']['name'] ?? BranchContext::ensure()->name,
                'branches' => $doc['branches'] ?? [],
                'money_sources' => $doc['money_sources'] ?? [],
                'title' => $doc['title'],
                'columns' => $doc['columns'],
                'summary' => $doc['summary_cards'],
                'controls' => $doc['controls'],
                'categories' => $doc['categories'] ?? [],
                'customers' => $doc['customers'] ?? [],
                'sections' => $sections,
                'pick' => $doc['pick'] ?? null,
                'party_types' => $doc['party_types'] ?? [],
                'party_name' => $doc['party_name'] ?? null,
                'note' => $doc['note'] ?? null,
                'money_decimals' => money_decimals(),
            ],
        ]);
    }
}
