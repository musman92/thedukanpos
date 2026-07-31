<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubscriptionController extends Controller
{
    public function __construct(protected SubscriptionService $subscriptions) {}

    public function index(Request $request): Response
    {
        return Inertia::render(
            'Admin/Subscription/Index',
            $this->subscriptions->page([
                'q' => $request->input('q'),
                'status' => $request->input('status'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
        );
    }
}
