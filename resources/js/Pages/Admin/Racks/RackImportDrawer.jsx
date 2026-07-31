import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';

export default function RackImportDrawer({ open, onClose, result = null }) {
    const form = useForm({ file: null });

    const submit = (e) => {
        e.preventDefault();
        if (!form.data.file) return;

        form.post(route('admin.import-export.racks.import'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Import racks"
            description="Upload CSV or Excel. Columns: section_code, name, code, is_active."
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
                        Section codes must already exist. Blank rack code auto-assigns R01, R02… per section.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route('admin.import-export.racks.sample', { format: 'xlsx' })}
                            className="rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                        >
                            Excel sample
                        </a>
                        <a
                            href={route('admin.import-export.racks.sample', { format: 'csv' })}
                            className="rounded-lg border border-theme-border px-3 py-2 text-sm font-medium text-theme-ink hover:bg-theme-bg"
                        >
                            CSV sample
                        </a>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-3">
                    <p className="text-sm font-medium text-theme-ink">2. Upload file</p>
                    <Field
                        label="File"
                        required
                        error={form.errors.file}
                        hint="Accepted: .csv, .xlsx, .xls — max 5 MB, up to 1,000 rows."
                    >
                        <input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                            onChange={(e) => form.setData('file', e.target.files?.[0] || null)}
                            className="block w-full text-sm text-theme-ink file:mr-3 file:rounded-lg file:border-0 file:bg-theme-bg file:px-3 file:py-2 file:text-sm file:font-medium file:text-theme-ink hover:file:bg-theme-border/40"
                        />
                    </Field>
                    <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                        <Button type="button" variant="secondary" onClick={onClose}>
                            Close
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.file}>
                            {form.processing ? 'Importing…' : 'Import racks'}
                        </Button>
                    </div>
                </form>
            </div>
        </Drawer>
    );
}
