<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    public function index(Request $request): JsonResponse
    {
        $page = $this->subscriptions->page([
            'q' => $request->input('q'),
            'status' => $request->input('status'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);
        $invoices = $page['invoices'];

        return response()->json([
            'subscription' => $page['subscription'],
            'data' => array_values($invoices->items()),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'total' => $invoices->total(),
            ],
        ]);
    }
}
