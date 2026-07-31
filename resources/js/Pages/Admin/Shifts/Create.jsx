import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';

export default function Create({ branches, moneySources, defaults }) {
    const branchOptions = useMemo(
        () => branches.map((b) => ({ value: b.id, label: b.name, meta: b.code })),
        [branches],
    );

    const { data, setData, post, processing, errors } = useForm({
        branch_id: defaults.branch_id,
        shift_date: defaults.shift_date,
        notes: '',
        money_sources: moneySources.map((ms) => ({
            money_source_id: ms.id,
            opening_balance: 0,
        })),
    });

    const setBalance = (moneySourceId, value) => {
        setData(
            'money_sources',
            data.money_sources.map((row) =>
                row.money_source_id === moneySourceId
                    ? { ...row, opening_balance: value }
                    : row,
            ),
        );
    };

    const balanceFor = (id) =>
        data.money_sources.find((r) => r.money_source_id === id)?.opening_balance ?? 0;

    return (
        <AdminLayout
            title="Start New Shift"
            description="Record opening balances for each money source, then start the shift for POS and sales tracking."
        >
            <Head title="Start New Shift" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('admin.shifts.store'));
                }}
                className="dp-card mx-auto max-w-3xl space-y-6 p-6"
            >
                <Field label="Branch" required error={errors.branch_id}>
                    <SearchableSelect
                        options={branchOptions}
                        value={data.branch_id}
                        onChange={(v) => setData('branch_id', v)}
                        placeholder="Select branch"
                        error={!!errors.branch_id}
                    />
                </Field>

                <Field label="Shift Date" required error={errors.shift_date}>
                    <Input
                        type="date"
                        value={data.shift_date}
                        onChange={(e) => setData('shift_date', e.target.value)}
                        error={!!errors.shift_date}
                    />
                </Field>

                <div>
                    <p className="mb-2 text-sm font-medium text-theme-ink">
                        Opening Balances <span className="text-theme-danger">*</span>
                    </p>
                    <div className="overflow-hidden rounded-lg border border-theme-border">
                        {moneySources.length === 0 && (
                            <p className="px-4 py-6 text-center text-sm text-theme-ink-muted">
                                No active money sources. Add them under Financials → Money sources.
                            </p>
                        )}
                        {moneySources.map((ms, i) => (
                            <div
                                key={ms.id}
                                className={`flex items-center justify-between gap-4 px-4 py-3 ${
                                    i > 0 ? 'border-t border-theme-border' : ''
                                }`}
                            >
                                <div>
                                    <p className="text-sm font-medium text-theme-ink">{ms.name}</p>
                                    <p className="text-[11px] uppercase tracking-wide text-theme-ink-muted">
                                        {ms.type}
                                    </p>
                                </div>
                                <div className="w-36">
                                    <Input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value={balanceFor(ms.id)}
                                        onChange={(e) => setBalance(ms.id, e.target.value)}
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                    {errors.money_sources && (
                        <p className="mt-1 text-xs text-theme-danger">{errors.money_sources}</p>
                    )}
                </div>

                <Field label="Opening Notes (Optional)" error={errors.notes}>
                    <TextArea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        placeholder="Any notes about this shift…"
                    />
                </Field>

                <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                    <Link href={route('admin.shifts.index')}>
                        <Button type="button" variant="secondary">
                            Cancel
                        </Button>
                    </Link>
                    <Button type="submit" disabled={processing || moneySources.length === 0}>
                        Start Shift
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
