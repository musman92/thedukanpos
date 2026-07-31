<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use App\Services\ProductService;
use App\Support\BranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(protected ProductService $products) {}

    public function index(Request $request): Response
    {
        $branch = BranchContext::ensure();
        $result = $this->products->paginate([
            'q' => $request->input('q'),
            'per_page' => $request->input('per_page'),
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
            'branch_id' => $branch->id,
        ]);

        return Inertia::render('Admin/Products/Index', $result);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Products/Form', [
            'product' => null,
            'options' => $this->products->options(),
            'branchId' => BranchContext::ensure()->id,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $payload = $request->payload();
        $this->products->create($payload, $payload['branch_id']);

        return redirect()->route('admin.products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product): Response
    {
        $branch = BranchContext::ensure();

        return Inertia::render('Admin/Products/Form', [
            'product' => $this->products->loadForBranch($product, $branch->id),
            'options' => $this->products->options(),
            'branchId' => $branch->id,
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $payload = $request->payload();
        $this->products->update($product, $payload, $payload['branch_id']);

        return redirect()->route('admin.products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            $this->products->delete($product);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete product.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Product deleted.');
    }

    public function duplicate(Product $product): RedirectResponse
    {
        $copy = $this->products->duplicate($product, BranchContext::ensure()->id);

        return redirect()
            ->route('admin.products.edit', $copy)
            ->with('status', 'Product duplicated. Update the copy as needed.');
    }
}
