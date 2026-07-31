<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\SerialNumber;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SerialController extends Controller
{
    public function index(Request $request): Response
    {
        $q = $request->string('q')->toString();

        $serials = SerialNumber::query()
            ->with(['variant.product', 'variant'])
            ->when($q, fn ($query) => $query->where('serial', 'like', "%{$q}%"))
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/Serials/Index', [
            'serials' => $serials,
            'filters' => ['q' => $q],
            'variants' => ProductVariant::query()
                ->with('product')
                ->where('track_serial', true)
                ->where('is_active', true)
                ->orderBy('short_code')
                ->get()
                ->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'label' => $v->displayName(),
                    'product_id' => $v->product_id,
                ]),
            'branch' => BranchContext::ensure()->only(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = BranchContext::ensure();
        $data = $request->validate([
            'variant_id' => ['required', 'exists:product_variants,id'],
            'serials' => ['required', 'string'],
        ]);

        $variant = ProductVariant::query()->findOrFail($data['variant_id']);
        if (! $variant->track_serial) {
            return back()->withErrors(['variant_id' => 'This variant is not marked for serial tracking.']);
        }

        $lines = preg_split('/\r\n|\r|\n|,/', $data['serials']) ?: [];
        $created = 0;

        foreach ($lines as $line) {
            $serial = trim($line);
            if ($serial === '') {
                continue;
            }
            SerialNumber::query()->firstOrCreate(
                ['serial' => $serial],
                [
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'branch_id' => $branch->id,
                    'status' => 'in_stock',
                ],
            );
            $created++;
        }

        return back()->with('status', "Processed {$created} serial(s).");
    }
}
