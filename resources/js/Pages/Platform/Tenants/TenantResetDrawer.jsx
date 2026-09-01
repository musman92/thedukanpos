import Drawer from '@/Components/Ui/Drawer';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import { router, useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function TenantResetDrawer({ open, onClose, tenant, resetGroups = [] }) {
    const form = useForm({
        groups: [],
        confirm_code: '',
    });

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData({
            groups: [],
            confirm_code: '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, tenant?.id]);

    if (!tenant) return null;

    const toggleGroup = (key) => {
        const next = form.data.groups.includes(key)
            ? form.data.groups.filter((item) => item !== key)
            : [...form.data.groups, key];
        form.setData('groups', next);
    };

    const selectAll = () => {
        form.setData('groups', resetGroups.map((group) => group.key));
    };

    const clearAll = () => {
        form.setData('groups', []);
    };

    const canSubmit =
        form.data.groups.length > 0 &&
        form.data.confirm_code.trim().toLowerCase() === tenant.code.toLowerCase();

    const submit = (e) => {
        e.preventDefault();
        if (!canSubmit) return;

        const labels = resetGroups
            .filter((group) => form.data.groups.includes(group.key))
            .map((group) => group.label)
            .join(', ');

        if (
            !window.confirm(
                `Permanently delete selected data for ${tenant.name}?\n\n${labels}\n\nThis cannot be undone.`,
            )
        ) {
            return;
        }

        form.post(route('platform.tenants.reset-data', tenant.id), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Reset company data"
            description={`${tenant.name} · selective wipe for a fresh start`}
            width="lg"
        >
            <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p className="font-semibold">Destructive action</p>
                <p className="mt-1">
                    Selected data is permanently removed from this company&apos;s database.
                    Branches, admin login, company settings, units, taxes, and till definitions
                    are kept unless you choose options that clear them.
                </p>
            </div>

            <form onSubmit={submit} className="space-y-5">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-sm font-semibold text-theme-ink">What to reset</p>
                    <div className="flex gap-2 text-xs">
                        <button
                            type="button"
                            className="font-medium text-theme-primary hover:underline"
                            onClick={selectAll}
                        >
                            Select all
                        </button>
                        <span className="text-theme-ink-muted">·</span>
                        <button
                            type="button"
                            className="font-medium text-theme-ink-muted hover:text-theme-ink hover:underline"
                            onClick={clearAll}
                        >
                            Clear
                        </button>
                    </div>
                </div>

                <ul className="space-y-2">
                    {resetGroups.map((group) => {
                        const checked = form.data.groups.includes(group.key);
                        return (
                            <li key={group.key}>
                                <label
                                    className={`flex cursor-pointer gap-3 rounded-lg border px-3 py-3 transition ${
                                        checked
                                            ? 'border-theme-primary bg-[var(--color-primary-soft)]'
                                            : 'border-theme-border bg-theme-surface hover:bg-theme-bg'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        className="mt-1 h-4 w-4 rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                                        checked={checked}
                                        onChange={() => toggleGroup(group.key)}
                                    />
                                    <span className="min-w-0">
                                        <span className="block text-sm font-semibold text-theme-ink">
                                            {group.label}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-theme-ink-muted">
                                            {group.description}
                                        </span>
                                    </span>
                                </label>
                            </li>
                        );
                    })}
                </ul>
                {form.errors.groups && (
                    <p className="text-sm text-rose-600">{form.errors.groups}</p>
                )}

                <Field
                    label={`Type ${tenant.code} to confirm`}
                    required
                    error={form.errors.confirm_code}
                >
                    <Input
                        value={form.data.confirm_code}
                        onChange={(e) => form.setData('confirm_code', e.target.value)}
                        placeholder={tenant.code}
                        autoComplete="off"
                        error={!!form.errors.confirm_code}
                    />
                </Field>

                <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={!canSubmit || form.processing} variant="danger">
                        {form.processing ? 'Resetting…' : 'Reset selected data'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
