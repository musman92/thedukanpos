<?php

namespace App\Services;

use App\Models\Quotation;
use App\Support\BranchContext;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class QuotationPdfService
{
    public function __construct(protected SettingService $settings) {}

    /**
     * @return array{quotation: Quotation, company: array<string, mixed>, money: callable}
     */
    public function viewData(Quotation $quotation): array
    {
        $branch = BranchContext::ensure();
        if ((int) $quotation->branch_id !== (int) $branch->id) {
            abort(404);
        }

        $quotation->load([
            'items.product:id,name',
            'items.variant:id,name,short_code',
            'items.unit:id,name,code',
            'customer:id,name,phone,email,address',
            'branch:id,name',
            'creator:id,name',
        ]);

        return [
            'quotation' => $quotation,
            'company' => PdfBranding::company(),
            'statusLabel' => ucfirst((string) $quotation->status),
        ];
    }

    public function stream(Quotation $quotation): Response
    {
        $data = $this->viewData($quotation);
        $filename = $this->filename($quotation);

        return Pdf::loadView('pdf.quotation', $data)
            ->setPaper('a4')
            ->stream($filename);
    }

    public function download(Quotation $quotation): Response
    {
        $data = $this->viewData($quotation);
        $filename = $this->filename($quotation);

        return Pdf::loadView('pdf.quotation', $data)
            ->setPaper('a4')
            ->download($filename);
    }

    protected function filename(Quotation $quotation): string
    {
        $safe = preg_replace('/[^A-Za-z0-9\-]+/', '-', $quotation->number) ?: 'quotation';

        return $safe.'.pdf';
    }
}
