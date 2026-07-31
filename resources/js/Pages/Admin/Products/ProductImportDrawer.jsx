import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';

export default function ProductImportDrawer({ open, onClose, result = null }) {
    const form = useForm({ file: null });

    const submit = (e) => {
        e.preventDefault();
        if (!form.data.file) return;

        form.post(route('admin.import-export.products.import'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Import products"
            description="Excel workbook with two sheets: products + variants (FoodPOS-style)."
            width="half"
        >
            <div className="flex h-full flex-col gap-5">
                {result && (
                    <div className="space-y-3 rounded-lg border border-theme-border p-4">
                        <p className="text-sm font-semibold text-theme-ink">Last import summary</p>
                        <div className="grid grid-cols-3 gap-2 text-center">
                            <div className="rounded-lg bg-emerald-50 px-2 py-3">
                                <p className="text-[11px] uppercase text-emerald-700">Created</p>
                                <p className="text-lg font-bold text-emerald-900">{result.created ?? 0}</p>
                            </div>
                            <div className="rounded-lg bg-sky-50 px-2 py-3">
                                <p className="text-[11px] uppercase text-sky-700">Updated</p>
                                <p className="text-lg font-bold text-sky-900">{result.updated ?? 0}</p>
                            </div>
                            <div className="rounded-lg bg-amber-50 px-2 py-3">
                                <p className="text-[11px] uppercase text-amber-700">Skipped</p>
                                <p className="text-lg font-bold text-amber-900">{result.skipped ?? 0}</p>
                            </div>
                        </div>
                        {!!result.errors?.length && (
                            <div className="max-h-40 overflow-auto rounded-lg border border-theme-border">
                                <table className="min-w-full text-left text-xs">
                                    <thead className="bg-theme-bg text-theme-ink-muted">
                                        <tr>
                                            <th className="px-2 py-1.5">Row</th>
                                            <th className="px-2 py-1.5">Message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {result.errors.map((err, i) => (
                                            <tr key={`${err.row}-${i}`} className="border-t border-theme-border">
                                                <td className="px-2 py-1.5 text-theme-ink-soft">{err.row}</td>
                                                <td className="px-2 py-1.5 text-theme-danger">{err.message}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                )}

                <div className="space-y-2">
                    <p className="text-sm font-medium text-theme-ink">1. Download sample</p>
                    <p className="text-xs text-theme-ink-muted">
                        Sheet <strong>products</strong>: one row per product (product_code, name, type,
                        brand, category, tax, variation_code…). Sheet <strong>variants</strong>: one row
                        per option (product_code, option_name/code, prices, units…). Link by product_code.
                    </p>
                    <a
                        href={route('admin.import-export.products.sample', { format: 'xlsx' })}
                        className="inline-flex rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                    >
                        Excel sample (2 sheets)
                    </a>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <p className="text-sm font-medium text-theme-ink">2. Upload workbook</p>
                    <Field
                        label="Excel file"
                        required
                        error={form.errors.file}
                        hint="Accepted: .xlsx / .xls — max 5 MB."
                    >
                        <input
                            type="file"
                            accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                            className="block w-full text-sm text-theme-ink file:mr-3 file:rounded-lg file:border-0 file:bg-theme-bg file:px-3 file:py-2 file:text-sm file:font-medium file:text-theme-ink"
                            onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                        />
                    </Field>
                    <Button type="submit" disabled={form.processing || !form.data.file}>
                        {form.processing ? 'Importing…' : 'Import products'}
                    </Button>
                </form>
            </div>
        </Drawer>
    );
}
