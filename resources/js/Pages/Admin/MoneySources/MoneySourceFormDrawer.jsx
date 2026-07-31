import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    type: 'CASH',
    opening_balance: '0',
    is_active: true,
    exclude_from_dashboard_profit: false,
    branch_ids: [],
});

export default function MoneySourceFormDrawer({
    open,
    source = null,
    branches = [],
    activeBranchId = null,
    onClose,
}) {
    const editing = !!source;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (source) {
            form.setData({
                name: source.name || '',
                type: source.type || 'CASH',
                opening_balance: String(source.opening_balance ?? 0),
                is_active: !!source.is_active,
                exclude_from_dashboard_profit: !!source.exclude_from_dashboard_profit,
                branch_ids: source.branch_ids || [],
            });
        } else {
            form.setData({
                ...emptyData(),
                branch_ids: activeBranchId ? [activeBranchId] : [],
            });
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, source?.id]);

    const toggleBranch = (id) => {
        const current = form.data.branch_ids || [];
        const next = current.includes(id)
            ? current.filter((b) => b !== id)
            : [...current, id];
        form.setData('branch_ids', next);
    };

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            ...(editing ? { _method: 'put' } : {}),
        }));

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                if (!editing) {
                    form.reset();
                }
                onClose();
            },
            onFinish: () => form.transform((data) => data),
        };

        if (editing) {
            form.post(route('admin.finance.money-sources.update', source.id), options);
            return;
        }

        form.post(route('admin.finance.money-sources.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit money source' : 'Add money source'}
            description="Physical location where money is stored — Cash, Bank, or App."
            width="half"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            autoFocus
                        />
                    </Field>

                    <Field label="Type" required error={form.errors.type}>
                        <select
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="CASH">Cash</option>
                            <option value="BANK">Bank</option>
                            <option value="APP">App (Digital Wallet)</option>
                        </select>
                        <p className="mt-1 text-xs text-theme-ink-muted">
                            CASH = Physical cash, BANK = Bank account/card, APP = Digital wallet
                        </p>
                    </Field>

                    {!editing && (
                        <Field
                            label="Opening Balance"
                            required
                            error={form.errors.opening_balance}
                            hint="Starting balance when you first set up this source. Current balance updates from transactions."
                        >
                            <Input
                                type="number"
                                step="0.01"
                                min="0"
                                value={form.data.opening_balance}
                                onChange={(e) =>
                                    form.setData('opening_balance', e.target.value)
                                }
                                error={!!form.errors.opening_balance}
                            />
                        </Field>
                    )}

                    {editing ? (
                        <Field label="Branches" error={form.errors.branch_ids}>
                            <div className="max-h-40 space-y-2 overflow-y-auto rounded-lg border border-theme-border p-3">
                                {branches.map((b) => (
                                    <label
                                        key={b.id}
                                        className="flex items-center gap-2 text-sm text-theme-ink"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={(form.data.branch_ids || []).includes(b.id)}
                                            onChange={() => toggleBranch(b.id)}
                                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                                        />
                                        {b.name}
                                    </label>
                                ))}
                                {branches.length === 0 && (
                                    <p className="text-xs text-theme-ink-muted">No branches available.</p>
                                )}
                            </div>
                        </Field>
                    ) : (
                        <p className="rounded-lg bg-theme-bg px-3 py-2 text-xs text-theme-ink-muted">
                            Assigned to the current branch on create. Edit later to assign more branches.
                        </p>
                    )}

                    <label className="flex items-start gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="mt-0.5 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        <span>
                            Active
                            <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                Money source will be available for shifts and transactions
                            </span>
                        </span>
                    </label>

                    <label className="flex items-start gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={form.data.exclude_from_dashboard_profit}
                            onChange={(e) =>
                                form.setData('exclude_from_dashboard_profit', e.target.checked)
                            }
                            className="mt-0.5 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        <span>
                            Exclude from dashboard net profit
                            <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                Payments from this source are ignored in dashboard Net Profit. Shift
                                balances and P&amp;L are unchanged.
                            </span>
                        </span>
                    </label>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create money source'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
