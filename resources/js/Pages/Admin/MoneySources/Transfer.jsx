import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import MoneySourcesShell, { money } from '@/Pages/Admin/MoneySources/MoneySourcesShell';
import { Head, useForm } from '@inertiajs/react';

export default function Transfer({
    sources = [],
    branch = null,
    active_nav: activeNav = 'transfer',
}) {
    const form = useForm({
        from_money_source_id: sources[0]?.id || '',
        to_money_source_id: sources[1]?.id || sources[0]?.id || '',
        amount: '',
        transfer_date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const fromSource = sources.find((s) => String(s.id) === String(form.data.from_money_source_id));
    const toSource = sources.find((s) => String(s.id) === String(form.data.to_money_source_id));

    const useFullBalance = () => {
        if (fromSource) {
            form.setData('amount', String(fromSource.balance));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.finance.money-sources.transfer'), {
            preserveScroll: true,
            onSuccess: () => form.reset('amount', 'notes'),
        });
    };

    return (
        <AdminLayout title="Transfer">
            <Head title="Transfer — Money sources" />

            <MoneySourcesShell
                activeNav={activeNav}
                title="Transfer"
                description="Move money between operational sources. Not an expense — does not affect P&L."
            >
                <form onSubmit={submit} className="mx-auto max-w-lg space-y-4">
                    {branch && (
                        <p className="rounded-lg bg-theme-bg px-3 py-2 text-sm text-theme-ink-soft">
                            Branch: <span className="font-medium text-theme-ink">{branch.name}</span>
                        </p>
                    )}

                    <Field label="Transfer date" required error={form.errors.transfer_date}>
                        <Input
                            type="date"
                            value={form.data.transfer_date}
                            onChange={(e) => form.setData('transfer_date', e.target.value)}
                            error={!!form.errors.transfer_date}
                        />
                    </Field>

                    <Field label="From money source" required error={form.errors.from_money_source_id}>
                        <select
                            value={form.data.from_money_source_id}
                            onChange={(e) => form.setData('from_money_source_id', e.target.value)}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="">Select source</option>
                            {sources.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name} ({money(s.balance)})
                                </option>
                            ))}
                        </select>
                        {fromSource && (
                            <div className="mt-2 flex items-center justify-between rounded-lg bg-theme-bg px-3 py-2 text-xs">
                                <span className="text-theme-ink-muted">
                                    Available: <strong className="text-theme-ink">{money(fromSource.balance)}</strong>
                                </span>
                                <button
                                    type="button"
                                    onClick={useFullBalance}
                                    className="font-medium text-theme-primary hover:underline"
                                >
                                    Use full balance
                                </button>
                            </div>
                        )}
                    </Field>

                    <Field label="To money source" required error={form.errors.to_money_source_id}>
                        <select
                            value={form.data.to_money_source_id}
                            onChange={(e) => form.setData('to_money_source_id', e.target.value)}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="">Select source</option>
                            {sources.map((s) => (
                                <option key={s.id} value={s.id}>
                                    {s.name} ({money(s.balance)})
                                </option>
                            ))}
                        </select>
                        {toSource && (
                            <p className="mt-1 text-xs text-theme-ink-muted">
                                Current balance: {money(toSource.balance)}
                            </p>
                        )}
                    </Field>

                    <Field label="Amount" required error={form.errors.amount}>
                        <Input
                            type="number"
                            step="0.01"
                            min="0.01"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                            error={!!form.errors.amount}
                        />
                    </Field>

                    <Field label="Notes" error={form.errors.notes}>
                        <Input
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            error={!!form.errors.notes}
                            placeholder="Optional"
                        />
                    </Field>

                    <div className="flex justify-end pt-2">
                        <Button type="submit" disabled={form.processing}>
                            Transfer money
                        </Button>
                    </div>
                </form>
            </MoneySourcesShell>
        </AdminLayout>
    );
}
