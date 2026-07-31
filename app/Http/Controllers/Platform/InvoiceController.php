<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformInvoice;
use App\Models\Tenant;
use App\Support\PageLimit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $status = strtolower(trim((string) $request->input('status', 'all')));
        if (! in_array($status, ['all', 'open', 'paid', 'void'], true)) {
            $status = 'all';
        }
        $tenantId = $request->filled('tenant_id') ? (string) $request->input('tenant_id') : null;
        $perPage = PageLimit::resolve($request->input('per_page'), PageLimit::DEFAULT);
        [$sort, $direction] = $this->resolveSort(
            $request->input('sort'),
            $request->input('direction'),
        );

        $invoices = PlatformInvoice::query()
            ->with('tenant:id,code,name')
            ->when($q !== '', function (Builder $query) use ($q) {
                $query->where(function (Builder $inner) use ($q) {
                    $inner->where('number', 'like', "%{$q}%")
                        ->orWhere('notes', 'like', "%{$q}%")
                        ->orWhereHas('tenant', function (Builder $t) use ($q) {
                            $t->where('code', 'like', "%{$q}%")
                                ->orWhere('name', 'like', "%{$q}%");
                        });
                });
            })
            ->when($status !== 'all', fn (Builder $query) => $query->where('status', $status))
            ->when($tenantId, fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->orderBy($sort, $direction)
            ->when($sort !== 'id', fn (Builder $query) => $query->orderByDesc('id'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (PlatformInvoice $inv) => [
                'id' => $inv->id,
                'number' => $inv->number,
                'tenant_id' => $inv->tenant_id,
                'tenant_code' => $inv->tenant?->code,
                'tenant_name' => $inv->tenant?->name,
                'amount' => round((float) $inv->amount, 2),
                'status' => $inv->status,
                'invoice_date' => optional($inv->invoice_date)?->toDateString(),
                'due_date' => optional($inv->due_date)?->toDateString(),
                'paid_at' => optional($inv->paid_at)?->toDateString(),
                'notes' => $inv->notes,
            ]);

        return Inertia::render('Platform/Invoices/Index', [
            'invoices' => $invoices,
            'tenants' => Tenant::query()
                ->orderBy('code')
                ->get(['id', 'code', 'name'])
                ->map(fn (Tenant $t) => [
                    'value' => $t->id,
                    'label' => $t->name.' · '.$t->code,
                    'meta' => $t->code,
                ]),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'tenant_id' => $tenantId,
                'per_page' => $perPage,
                'sort' => $sort,
                'direction' => $direction,
                'company_page_limit' => PageLimit::DEFAULT,
            ],
            'form_open' => $request->boolean('open'),
            'auth' => [
                'user' => Auth::guard('platform')->user()?->only(['id', 'name', 'email']),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'tenant_id' => ['required', 'string', 'exists:tenants,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,paid,void'],
        ]);

        $tenant = Tenant::query()->findOrFail($data['tenant_id']);

        $prefix = 'INV-'.now()->format('Ymd').'-';
        $last = PlatformInvoice::query()->where('number', 'like', $prefix.'%')->orderByDesc('id')->value('number');
        $seq = $last ? ((int) substr($last, -4)) + 1 : 1;

        PlatformInvoice::query()->create([
            'tenant_id' => $tenant->id,
            'number' => $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
            'invoice_date' => $data['invoice_date'],
            'due_date' => $data['due_date'] ?? null,
            'amount' => $data['amount'],
            'status' => $data['status'] ?? 'open',
            'notes' => $data['notes'] ?? null,
            'paid_at' => ($data['status'] ?? '') === 'paid' ? now() : null,
        ]);

        return redirect()
            ->route('platform.invoices.index')
            ->with('status', "Invoice created for {$tenant->code}.");
    }

    public function markPaid(PlatformInvoice $invoice): RedirectResponse
    {
        $invoice->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return back()->with('status', "Invoice {$invoice->number} marked paid.");
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function resolveSort(mixed $sort, mixed $direction): array
    {
        $allowed = ['number', 'amount', 'status', 'invoice_date', 'due_date', 'id'];
        $sort = strtolower(trim((string) ($sort ?? 'id')));
        if (! in_array($sort, $allowed, true)) {
            $sort = 'id';
        }
        $direction = strtolower(trim((string) ($direction ?? 'desc')));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        return [$sort, $direction];
    }
}
