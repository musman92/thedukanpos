<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\BrandImportExportService;
use App\Services\CategoryImportExportService;
use App\Services\CustomerImportExportService;
use App\Services\ProductImportExportService;
use App\Services\RackImportExportService;
use App\Services\SectionImportExportService;
use App\Services\UnitImportExportService;
use App\Services\VariationImportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportExportController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ImportExport/Index');
    }

    public function exportProducts(Request $request, ProductImportExportService $products): StreamedResponse|BinaryFileResponse
    {
        return $products->export($request->query('format', 'xlsx'));
    }

    public function sampleProducts(Request $request, ProductImportExportService $products): StreamedResponse|BinaryFileResponse
    {
        return $products->sample($request->query('format', 'xlsx'));
    }

    public function importProducts(Request $request, ProductImportExportService $products, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(ProductImportExportService::fileRules(), [
            'file.extensions' => 'Upload an Excel workbook (.xlsx / .xls) with products + variants sheets.',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $products->import($request->file('file'));
        $logger->log(
            'import.products',
            "Imported products (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Products import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportCustomers(Request $request, CustomerImportExportService $customers): StreamedResponse|BinaryFileResponse
    {
        return $customers->export($request->query('format', 'csv'));
    }

    public function sampleCustomers(Request $request, CustomerImportExportService $customers): StreamedResponse|BinaryFileResponse
    {
        return $customers->sample($request->query('format', 'csv'));
    }

    public function importCustomers(Request $request, CustomerImportExportService $customers, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(CustomerImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv / .xlsx / .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $customers->import($request->file('file'));
        $logger->log(
            'import.customers',
            "Imported customers (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Customers import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportBrands(Request $request, BrandImportExportService $brands): StreamedResponse|BinaryFileResponse
    {
        return $brands->export($request->query('format', 'csv'));
    }

    public function sampleBrands(Request $request, BrandImportExportService $brands): StreamedResponse|BinaryFileResponse
    {
        return $brands->sample($request->query('format', 'csv'));
    }

    public function importBrands(Request $request, BrandImportExportService $brands, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(BrandImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $brands->import($request->file('file'));
        $result['entity'] = 'brands';
        $logger->log(
            'import.brands',
            "Imported brands (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Brands import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportCategories(Request $request, CategoryImportExportService $categories): StreamedResponse|BinaryFileResponse
    {
        return $categories->export($request->query('format', 'csv'));
    }

    public function sampleCategories(Request $request, CategoryImportExportService $categories): StreamedResponse|BinaryFileResponse
    {
        return $categories->sample($request->query('format', 'csv'));
    }

    public function importCategories(Request $request, CategoryImportExportService $categories, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(CategoryImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $categories->import($request->file('file'));
        $logger->log(
            'import.categories',
            "Imported categories (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Categories import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportUnits(Request $request, UnitImportExportService $units): StreamedResponse|BinaryFileResponse
    {
        return $units->export($request->query('format', 'csv'));
    }

    public function sampleUnits(Request $request, UnitImportExportService $units): StreamedResponse|BinaryFileResponse
    {
        return $units->sample($request->query('format', 'csv'));
    }

    public function importUnits(Request $request, UnitImportExportService $units, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(UnitImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $units->import($request->file('file'));
        $logger->log(
            'import.units',
            "Imported units (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Units import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportVariations(Request $request, VariationImportExportService $variations): StreamedResponse|BinaryFileResponse
    {
        return $variations->export($request->query('format', 'csv'));
    }

    public function sampleVariations(Request $request, VariationImportExportService $variations): StreamedResponse|BinaryFileResponse
    {
        return $variations->sample($request->query('format', 'csv'));
    }

    public function importVariations(Request $request, VariationImportExportService $variations, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(VariationImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $variations->import($request->file('file'));
        $logger->log(
            'import.variations',
            "Imported variations (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Variations import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportSections(Request $request, SectionImportExportService $sections): StreamedResponse|BinaryFileResponse
    {
        return $sections->export($request->query('format', 'csv'));
    }

    public function sampleSections(Request $request, SectionImportExportService $sections): StreamedResponse|BinaryFileResponse
    {
        return $sections->sample($request->query('format', 'csv'));
    }

    public function importSections(Request $request, SectionImportExportService $sections, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(SectionImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $sections->import($request->file('file'));
        $logger->log(
            'import.sections',
            "Imported sections (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Sections import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }

    public function exportRacks(Request $request, RackImportExportService $racks): StreamedResponse|BinaryFileResponse
    {
        return $racks->export($request->query('format', 'csv'));
    }

    public function sampleRacks(Request $request, RackImportExportService $racks): StreamedResponse|BinaryFileResponse
    {
        return $racks->sample($request->query('format', 'csv'));
    }

    public function importRacks(Request $request, RackImportExportService $racks, ActivityLogger $logger): RedirectResponse
    {
        $request->validate(RackImportExportService::fileRules(), [
            'file.extensions' => 'Upload a CSV or Excel file (.csv, .xlsx, .xls).',
            'file.max' => 'Import file must be at most 5 MB.',
        ]);

        $result = $racks->import($request->file('file'));
        $logger->log(
            'import.racks',
            "Imported racks (+{$result['created']} / ~{$result['updated']} updated / {$result['skipped']} skipped)",
        );

        $status = "Racks import done: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.";

        return back()
            ->with('status', $status)
            ->with('import_result', $result);
    }
}
