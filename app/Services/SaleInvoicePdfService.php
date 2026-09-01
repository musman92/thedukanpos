<?php

namespace App\Services;

use App\Models\Sale;
use App\Support\BranchContext;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class SaleInvoicePdfService
{
    public function __construct(protected SettingService $settings) {}

    /**
     * @return array{sale: Sale, company: array<string, mixed>, paymentLabel: string}
     */
    public function viewData(Sale $sale): array
    {
        $branch = BranchContext::ensure();
        if ((int) $sale->branch_id !== (int) $branch->id) {
            abort(404);
        }

        if ($sale->isParked()) {
            abort(404);
        }

        $sale->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'customer:id,name,phone,email,address',
            'branch:id,name',
            'cashier:id,name,username',
            'rider:id,name,username',
            'payments.moneySource:id,name',
        ]);

        $total = (float) $sale->total;
        $paidTotal = (float) $sale->paid_total;
        if ($paidTotal + 0.01 >= $total) {
            $paymentStatus = 'paid';
        } elseif ($paidTotal > 0.01) {
            $paymentStatus = 'partial';
        } else {
            $paymentStatus = 'pending';
        }

        return [
            'sale' => $sale,
            'company' => PdfBranding::company(),
            'paymentLabel' => match ($paymentStatus) {
                'paid' => 'Paid in full',
                'partial' => 'Partially paid',
                default => 'Payment pending',
            },
        ];
    }

    public function stream(Sale $sale): Response
    {
        $data = $this->viewData($sale);

        return Pdf::loadView('pdf.sale-invoice', $data)
            ->setPaper('a4')
            ->stream($this->filename($sale));
    }

    protected function filename(Sale $sale): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-]+/', '-', $sale->number) ?: 'invoice';

        return $safe.'.pdf';
    }
}
