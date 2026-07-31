<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Models\Brand;
use App\Services\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BrandController extends Controller
{
    public function __construct(protected BrandService $brands) {}

    public function index(Request $request): Response
    {
        $result = $this->brands->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
        ]);

        return Inertia::render('Admin/Brands/Index', $result);
    }

    public function store(StoreBrandRequest $request): RedirectResponse
    {
        $this->brands->create($request->payload());

        return back()->with('status', 'Brand created.');
    }

    public function update(UpdateBrandRequest $request, Brand $brand): RedirectResponse
    {
        $this->brands->update($brand, $request->payload());

        return back()->with('status', 'Brand updated.');
    }

    public function destroy(Brand $brand): RedirectResponse
    {
        try {
            $this->brands->delete($brand);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete brand.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Brand deleted.');
    }
}
