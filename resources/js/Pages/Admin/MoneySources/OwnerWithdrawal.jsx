import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import MoneySourcesShell, { money } from '@/Pages/Admin/MoneySources/MoneySourcesShell';
import { Head, useForm } from '@inertiajs/react';

export default function OwnerWithdrawal({
    sources = [],
    owner_bucket: ownerBucket = null,
    branch = null,
    active_nav: activeNav = 'owner-withdrawal',
}) {
    const form = useForm({
        from_money_source_id: sources[0]?.id || '',
        amount: '',
        date: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    const fromSource = sources.find((s) => String(s.id) === String(form.data.from_money_source_id));

    const useFullBalance = () => {
        if (fromSource) {
            form.setData('amount', String(fromSource.balance));
        }
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route('admin.finance.money-sources.owner-withdrawal'), {
            preserveScroll: true,
            onSuccess: () => form.reset('amount', 'notes'),
        });
    };

    return (
        <AdminLayout title="Owner withdrawal">
            <Head title="Owner withdrawal — Money sources" />

            <MoneySourcesShell
                activeNav={activeNav}
                title="Owner withdrawal"
                description="Withdraw funds to the owner bucket. Not recorded as transactions and do not affect profit & loss."
            >
                <form onSubmit={submit} className="mx-auto max-w-lg space-y-4">
                    {branch && (
                        <p className="rounded-lg bg-theme-bg px-3 py-2 text-sm text-theme-ink-soft">
                            Branch: <span className="font-medium text-theme-ink">{branch.name}</span>
                        </p>
                    )}

                    <Field label="Withdrawal date" required error={form.errors.date}>
                        <Input
                            type="date"
                            value={form.data.date}
                            onChange={(e) => form.setData('date', e.target.value)}
                            error={!!form.errors.date}
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
                                    Available:{' '}
                                    <strong className="text-theme-ink">{money(fromSource.balance)}</strong>
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

                    <div className="rounded-lg border border-theme-border bg-theme-bg px-3 py-3 text-sm">
                        <p className="text-xs font-medium uppercase tracking-wide text-theme-ink-muted">
                            To
                        </p>
                        <p className="mt-1 font-medium text-theme-ink">
                            {ownerBucket?.name || 'Owner Withdrawal'}
                        </p>
                        {ownerBucket && (
                            <p className="mt-0.5 text-xs text-theme-ink-muted">
                                Total withdrawn: {money(ownerBucket.balance)}
                            </p>
                        )}
                    </div>

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
                        <Button type="submit" disabled={form.processing || !ownerBucket}>
                            Record withdrawal
                        </Button>
                    </div>
                </form>
            </MoneySourcesShell>
        </AdminLayout>
    );
}
