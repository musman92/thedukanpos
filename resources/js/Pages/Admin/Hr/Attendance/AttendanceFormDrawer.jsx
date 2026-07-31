import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const emptyData = (date) => ({
    user_id: '',
    attendance_date: date || new Date().toISOString().slice(0, 10),
    clock_in: '',
    clock_out: '',
    status: 'present',
    notes: '',
});

export default function AttendanceFormDrawer({
    open,
    record = null,
    employees = [],
    statuses = [],
    defaultDate,
    onClose,
}) {
    const editing = !!record;
    const form = useForm(emptyData(defaultDate));

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (record) {
            form.setData({
                user_id: record.user_id ? String(record.user_id) : '',
                attendance_date: record.attendance_date || defaultDate,
                clock_in: record.clock_in || '',
                clock_out: record.clock_out || '',
                status: record.status || 'present',
                notes: record.notes || '',
            });
        } else {
            form.setData(emptyData(defaultDate));
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, record?.id, defaultDate]);

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
            form.post(route('admin.hr.attendance.update', record.id), options);
            return;
        }

        form.post(route('admin.hr.attendance.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit attendance' : 'Mark attendance'}
            description="Clock times are optional for leave or absent days."
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

                        <Field label="Date" required error={form.errors.attendance_date}>
                            <Input
                                type="date"
                                value={form.data.attendance_date}
                                onChange={(e) => form.setData('attendance_date', e.target.value)}
                                error={!!form.errors.attendance_date}
                            />
                        </Field>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Status" required error={form.errors.status}>
                            <select
                                value={form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)}
                                className={selectClass}
                            >
                                {statuses.map((s) => (
                                    <option key={s} value={s}>
                                        {s.replace(/_/g, ' ')}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field label="Clock in" error={form.errors.clock_in}>
                            <Input
                                type="time"
                                value={form.data.clock_in}
                                onChange={(e) => form.setData('clock_in', e.target.value)}
                                error={!!form.errors.clock_in}
                            />
                        </Field>

                        <Field label="Clock out" error={form.errors.clock_out}>
                            <Input
                                type="time"
                                value={form.data.clock_out}
                                onChange={(e) => form.setData('clock_out', e.target.value)}
                                error={!!form.errors.clock_out}
                            />
                        </Field>
                    </div>

                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea
                            rows={3}
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
                        {editing ? 'Save changes' : 'Save'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
