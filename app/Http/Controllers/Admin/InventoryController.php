<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStockAdjustmentRequest;
use App\Http\Requests\Admin\StoreStockDamageRequest;
use App\Http\Requests\Admin\StoreStockTransferRequest;
use App\Models\StockAdjustment;
use App\Models\StockDamage;
use App\Models\StockTransfer;
use App\Services\StockAdjustmentService;
use App\Services\StockDamageService;
use App\Services\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        protected StockAdjustmentService $adjustments,
        protected StockTransferService $transferService,
        protected StockDamageService $damageService,
    ) {}

    public function adjustments(Request $request): Response
    {
        $editing = null;
        if ($request->filled('form_adjustment_id')) {
            $editing = $this->adjustments->findForEdit($request->integer('form_adjustment_id'));
        }

        $formOpen = $request->boolean('open') || $editing !== null;

        return Inertia::render('Admin/Inventory/Adjustments', [
            ...$this->adjustments->paginate([
                'q' => $request->input('q'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            'form_open' => $formOpen,
            'editing_adjustment' => $editing,
        ]);
    }

    public function adjustmentStock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'exclude_adjustment_id' => ['nullable', 'integer', 'exists:stock_adjustments,id'],
        ]);

        try {
            return response()->json($this->adjustments->stockContext(
                (int) $data['variant_id'],
                isset($data['exclude_adjustment_id']) ? (int) $data['exclude_adjustment_id'] : null,
            ));
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? 'Invalid request.',
            ], 422);
        }
    }

    public function storeAdjustment(StoreStockAdjustmentRequest $request): RedirectResponse
    {
        try {
            $this->adjustments->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.inventory.adjustments')
            ->with('status', 'Stock adjustment saved.');
    }

    public function updateAdjustment(
        StoreStockAdjustmentRequest $request,
        StockAdjustment $stockAdjustment,
    ): RedirectResponse {
        try {
            $this->adjustments->update($stockAdjustment, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.inventory.adjustments')
            ->with('status', 'Stock adjustment updated.');
    }

    public function destroyAdjustment(StockAdjustment $stockAdjustment): RedirectResponse
    {
        try {
            $this->adjustments->delete($stockAdjustment);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete adjustment.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Stock adjustment deleted.');
    }

    public function transfers(Request $request): Response
    {
        $formOpen = $request->boolean('open');

        return Inertia::render('Admin/Inventory/Transfers', [
            ...$this->transferService->paginate([
                'q' => $request->input('q'),
                'to_branch_id' => $request->input('to_branch_id'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            'form_open' => $formOpen,
        ]);
    }

    public function storeTransfer(StoreStockTransferRequest $request): RedirectResponse
    {
        try {
            $this->transferService->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.inventory.transfers')
            ->with('status', 'Stock transfer completed.');
    }

    public function destroyTransfer(StockTransfer $stockTransfer): RedirectResponse
    {
        try {
            $this->transferService->delete($stockTransfer);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete transfer.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Stock transfer deleted and stock reversed.');
    }

    public function damages(Request $request): Response
    {
        $editing = null;
        if ($request->filled('form_damage_id')) {
            $editing = $this->damageService->findForEdit($request->integer('form_damage_id'));
        }

        $formOpen = $request->boolean('open') || $editing !== null;

        return Inertia::render('Admin/Inventory/Damages', [
            ...$this->damageService->paginate([
                'q' => $request->input('q'),
                'reason' => $request->input('reason'),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'per_page' => $request->input('per_page'),
                'sort' => $request->input('sort'),
                'direction' => $request->input('direction'),
            ]),
            'form_open' => $formOpen,
            'editing_damage' => $editing,
        ]);
    }

    public function storeDamage(StoreStockDamageRequest $request): RedirectResponse
    {
        try {
            $this->damageService->create($request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.inventory.damages')
            ->with('status', 'Damage recorded.');
    }

    public function updateDamage(
        StoreStockDamageRequest $request,
        StockDamage $stockDamage,
    ): RedirectResponse {
        try {
            $this->damageService->update($stockDamage, $request->payload());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            return back()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.inventory.damages')
            ->with('status', 'Damage updated.');
    }

    public function destroyDamage(StockDamage $stockDamage): RedirectResponse
    {
        try {
            $this->damageService->delete($stockDamage);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first() ?? 'Cannot delete damage.';

            return back()->with('error', $message);
        }

        return back()->with('status', 'Damage deleted and stock restored.');
    }
}
