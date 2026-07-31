import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const emptyData = () => ({
    user_id: '',
    type: 'bonus',
    amount: '',
    effective_date: new Date().toISOString().slice(0, 10),
    notes: '',
});

export default function AdjustmentFormDrawer({
    open,
    adjustment = null,
    employees = [],
    onClose,
}) {
    const editing = !!adjustment;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (adjustment) {
            form.setData({
                user_id: adjustment.user_id ? String(adjustment.user_id) : '',
                type: adjustment.type || 'bonus',
                amount: adjustment.amount != null ? String(adjustment.amount) : '',
                effective_date: adjustment.effective_date || new Date().toISOString().slice(0, 10),
                notes: adjustment.notes || '',
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, adjustment?.id]);

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
            form.post(route('admin.hr.adjustments.update', adjustment.id), options);
            return;
        }

        form.post(route('admin.hr.adjustments.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit bonus / deduction' : 'Add bonus / deduction'}
            description="Pending items are picked up when you generate payroll for that date range."
            width="lg"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Employee" required error={form.errors.user_id}>
                            <select
                                value={form.data.user_id}
                                onChange={(e) => form.setData('user_id', e.target.value)}
                                className={selectClass}
                                autoFocus
                            >
                                <option value="">Select employee</option>
                                {employees.map((e) => (
                                    <option key={e.id} value={e.id}>
                                        {e.name}
                                        {e.employee_number ? ` · #${e.employee_number}` : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Type" required error={form.errors.type}>
                            <select
                                value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}
                                className={selectClass}
                            >
                                <option value="bonus">Bonus</option>
                                <option value="deduction">Deduction</option>
                            </select>
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
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

                        <Field label="Effective date" required error={form.errors.effective_date}>
                            <Input
                                type="date"
                                value={form.data.effective_date}
                                onChange={(e) => form.setData('effective_date', e.target.value)}
                                error={!!form.errors.effective_date}
                            />
                        </Field>
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={4}
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            error={!!form.errors.notes}
                            placeholder="Optional"
                        />
                    </Field>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Add'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
