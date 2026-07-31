import Drawer from '@/Components/Ui/Drawer';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

export default function InvoiceFormDrawer({ open, onClose, tenants = [] }) {
    const form = useForm({
        tenant_id: '',
        amount: '',
        invoice_date: new Date().toISOString().slice(0, 10),
        due_date: '',
        notes: '',
        status: 'open',
    });

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData({
            tenant_id: '',
            amount: '',
            invoice_date: new Date().toISOString().slice(0, 10),
            due_date: '',
            notes: '',
            status: 'open',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e) => {
        e.preventDefault();
        form.post(route('platform.invoices.store'), {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Create invoice"
            description="Issue a billing invoice for a tenant."
            width="sm"
        >
            <form onSubmit={submit} className="space-y-4">
                <Field label="Tenant" required error={form.errors.tenant_id}>
                    <SearchableSelect
                        options={tenants}
                        value={form.data.tenant_id || null}
                        onChange={(value) => form.setData('tenant_id', value ? String(value) : '')}
                        placeholder="Search tenant…"
                        error={!!form.errors.tenant_id}
                    />
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
                <Field label="Invoice date" required error={form.errors.invoice_date}>
                    <Input
                        type="date"
                        value={form.data.invoice_date}
                        onChange={(e) => form.setData('invoice_date', e.target.value)}
                        error={!!form.errors.invoice_date}
                    />
                </Field>
                <Field label="Due date" error={form.errors.due_date}>
                    <Input
                        type="date"
                        value={form.data.due_date}
                        onChange={(e) => form.setData('due_date', e.target.value)}
                        error={!!form.errors.due_date}
                    />
                </Field>
                <Field label="Status">
                    <select
                        className={selectClass}
                        value={form.data.status}
                        onChange={(e) => form.setData('status', e.target.value)}
                    >
                        <option value="open">Open</option>
                        <option value="paid">Paid</option>
                        <option value="void">Void</option>
                    </select>
                </Field>
                <Field label="Notes" error={form.errors.notes}>
                    <Input
                        value={form.data.notes}
                        onChange={(e) => form.setData('notes', e.target.value)}
                        error={!!form.errors.notes}
                    />
                </Field>
                <div className="flex justify-end gap-2 border-t border-theme-border pt-4">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Create invoice
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
