import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const emptyData = () => ({
    user_id: '',
    leave_type: 'casual',
    start_date: '',
    end_date: '',
    reason: '',
});

export default function LeaveFormDrawer({
    open,
    employees = [],
    leaveTypes = [],
    onClose,
}) {
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        form.setData(emptyData());

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e) => {
        e.preventDefault();

        form.post(route('admin.hr.leaves.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title="Request leave"
            description="Pending requests can be approved, rejected, or cancelled."
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

                        <Field label="Type" required error={form.errors.leave_type}>
                            <select
                                value={form.data.leave_type}
                                onChange={(e) => form.setData('leave_type', e.target.value)}
                                className={selectClass}
                            >
                                {leaveTypes.map((t) => (
                                    <option key={t} value={t} className="capitalize">
                                        {t.replace(/_/g, ' ')}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Start date" required error={form.errors.start_date}>
                            <Input
                                type="date"
                                value={form.data.start_date}
                                onChange={(e) => form.setData('start_date', e.target.value)}
                                error={!!form.errors.start_date}
                            />
                        </Field>

                        <Field label="End date" required error={form.errors.end_date}>
                            <Input
                                type="date"
                                value={form.data.end_date}
                                onChange={(e) => form.setData('end_date', e.target.value)}
                                error={!!form.errors.end_date}
                            />
                        </Field>
                    </div>

                    <Field label="Reason" error={form.errors.reason}>
                        <TextArea
                            rows={4}
                            value={form.data.reason}
                            onChange={(e) => form.setData('reason', e.target.value)}
                            error={!!form.errors.reason}
                            placeholder="Optional"
                        />
                    </Field>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Submit request
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
