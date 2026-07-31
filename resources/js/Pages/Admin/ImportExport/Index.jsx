import AdminLayout from '@/Layouts/AdminLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, useForm, usePage } from '@inertiajs/react';

function EntityImport({
    title,
    resultEntity = null,
    columnsHint,
    exportCsvRoute,
    exportExcelRoute,
    sampleCsvRoute,
    sampleExcelRoute,
    importRoute,
    accept = '.csv,text/csv,.xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel',
}) {
    const form = useForm({ file: null });
    const { flash } = usePage().props;
    const result = flash?.import_result?.entity === resultEntity ? flash.import_result : null;

    return (
        <section className="dp-card p-5">
            <h2 className="text-lg font-semibold text-theme-ink">{title}</h2>
            <p className="mt-1 text-sm text-theme-ink-muted">{columnsHint}</p>

            {(sampleCsvRoute || sampleExcelRoute) && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {sampleExcelRoute && (
                        <a
                            href={sampleExcelRoute}
                            className="rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                        >
                            Excel sample
                        </a>
                    )}
                    {sampleCsvRoute && (
                        <a
                            href={sampleCsvRoute}
                            className="rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                        >
                            CSV sample
                        </a>
                    )}
                </div>
            )}

            <div className="mt-4 flex flex-wrap items-center gap-3">
                {exportCsvRoute && (
                    <a
                        href={exportCsvRoute}
                        className="rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                    >
                        Export CSV
                    </a>
                )}
                {exportExcelRoute && (
                    <a
                        href={exportExcelRoute}
                        className="rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                    >
                        Export Excel
                    </a>
                )}
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(importRoute, { forceFormData: true });
                    }}
                    className="flex flex-wrap items-center gap-2"
                >
                    <input
                        type="file"
                        accept={accept}
                        onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                        className="text-sm text-theme-ink file:mr-2 file:rounded-lg file:border-0 file:bg-theme-bg file:px-3 file:py-1.5 file:text-sm"
                    />
                    <PrimaryButton disabled={form.processing || !form.data.file}>Import</PrimaryButton>
                </form>
            </div>
            {form.errors.file && <p className="mt-2 text-sm text-theme-danger">{form.errors.file}</p>}

            {result && (
                <div className="mt-4 space-y-2 rounded-lg border border-theme-border p-3 text-sm">
                    <p className="font-medium text-theme-ink">
                        Created {result.created}, updated {result.updated}, skipped {result.skipped}
                    </p>
                    {!!result.errors?.length && (
                        <ul className="max-h-32 list-disc space-y-1 overflow-auto pl-5 text-theme-danger">
                            {result.errors.map((err, i) => (
                                <li key={`${err.row}-${i}`}>
                                    Row {err.row}: {err.message}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}
        </section>
    );
}

export default function Index() {
    return (
        <AdminLayout title="Import / export">
            <Head title="Import / export" />
            <div className="grid max-w-3xl gap-6">
                <EntityImport
                    title="Brands"
                    resultEntity="brands"
                    columnsHint="Columns: name, code, is_active — CSV or Excel (.xlsx). Upserts by code; blank code auto-assigns B01, B02…"
                    sampleCsvRoute={route('admin.import-export.brands.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.brands.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.brands.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.brands.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.brands.import')}
                />

                <EntityImport
                    title="Categories"
                    resultEntity="categories"
                    columnsHint="Columns: name, code, parent_code, default_tax_code, is_active — CSV or Excel. Upserts by code; blank code auto-assigns C01, C02… Import parents before children."
                    sampleCsvRoute={route('admin.import-export.categories.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.categories.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.categories.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.categories.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.categories.import')}
                />

                <EntityImport
                    title="Units"
                    resultEntity="units"
                    columnsHint="Columns: name, code, is_active — CSV or Excel. Upserts by code; blank code auto-assigns u01, u02…"
                    sampleCsvRoute={route('admin.import-export.units.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.units.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.units.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.units.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.units.import')}
                />

                <EntityImport
                    title="Variations"
                    resultEntity="variations"
                    columnsHint="Columns: name, code, option_name, option_code, is_active — one row per option; same name/code groups into one type."
                    sampleCsvRoute={route('admin.import-export.variations.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.variations.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.variations.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.variations.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.variations.import')}
                />

                <EntityImport
                    title="Sections"
                    resultEntity="sections"
                    columnsHint="Columns: name, code, rack_name, rack_code, is_active — one row per rack; same name/code groups into one section."
                    sampleCsvRoute={route('admin.import-export.sections.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.sections.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.sections.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.sections.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.sections.import')}
                />

                <EntityImport
                    title="Racks"
                    resultEntity="racks"
                    columnsHint="Columns: section_code, name, code, is_active — section must exist; blank code auto-assigns R01… per section."
                    sampleCsvRoute={route('admin.import-export.racks.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.racks.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.racks.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.racks.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.racks.import')}
                />

                <EntityImport
                    title="Products"
                    resultEntity="products"
                    columnsHint="Excel only — sheet products (one row per product) + sheet variants (one row per option). Link by product_code. type=single needs products only; type=variant also needs variation_code + variants rows."
                    sampleExcelRoute={route('admin.import-export.products.sample', { format: 'xlsx' })}
                    exportExcelRoute={route('admin.import-export.products.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.products.import')}
                    accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                />

                <EntityImport
                    title="Customers"
                    resultEntity="customers"
                    columnsHint="CSV / Excel columns: name, code, phone, email, address, opening_balance, is_active. Blank code auto-assigns C01… Opening balance sets what they already owe (also accepts “balance”)."
                    sampleCsvRoute={route('admin.import-export.customers.sample', { format: 'csv' })}
                    sampleExcelRoute={route('admin.import-export.customers.sample', { format: 'xlsx' })}
                    exportCsvRoute={route('admin.import-export.customers.export', { format: 'csv' })}
                    exportExcelRoute={route('admin.import-export.customers.export', { format: 'xlsx' })}
                    importRoute={route('admin.import-export.customers.import')}
                />
            </div>
        </AdminLayout>
    );
}
