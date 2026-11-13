import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const selectClass =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const empty = {
    product_id: '',
    customer_name: '',
    customer_phone: '',
    customer_email: '',
    address: '',
    notes: '',
    scheduled_at: '',
    status: 'pending',
    assigned_user_id: '',
    branch_id: '',
};

function phoneDigits(value) {
    const hasPlus = String(value || '').trim().startsWith('+');
    const digits = String(value || '').replace(/\D/g, '');

    return hasPlus ? `+${digits}` : digits;
}

function shopDateTime(value, timeZone) {
    if (!value) return '';
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timeZone || undefined,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date(value));
    const read = (type) => parts.find((part) => part.type === type)?.value || '';
    const hour = read('hour') === '24' ? '00' : read('hour');

    return `${read('year')}-${read('month')}-${read('day')}T${hour}:${read('minute')}`;
}

export default function BookingFormDrawer({
    open,
    booking,
    services,
    assignees = [],
    branches = [],
    defaultBranchId = null,
    defaults = null,
    onClose,
}) {
    const timeZone = usePage().props.company?.timezone;
    const editing = !!booking;
    const form = useForm(empty);

    useEffect(() => {
        if (!open) return;
        form.clearErrors();
        form.setData(booking ? {
            product_id: booking.product_id || '',
            customer_name: booking.customer_name || '',
            customer_phone: booking.customer_phone || '',
            address: booking.address || '',
            notes: booking.notes || '',
            scheduled_at: shopDateTime(booking.scheduled_at, timeZone),
            status: booking.status,
            assigned_user_id: booking.assigned_user_id || '',
            branch_id: booking.branch_id || defaultBranchId || '',
        } : { ...empty, scheduled_at: defaults?.scheduled_at || '', branch_id: defaultBranchId || '' });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, booking?.id, defaults?.scheduled_at, timeZone]);

    const selected = services.find((service) => String(service.id) === String(form.data.product_id));

    const submit = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (editing) {
            form.put(route('admin.bookings.update', booking.id), options);
        } else {
            form.post(route('admin.bookings.store'), options);
        }
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? `Edit ${booking.number}` : 'Add booking'}
            description="Bookings use business hours and capacity checks."
            width="half"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    {!editing && (
                        <Field label="Service" required error={form.errors.product_id}>
                            <select
                                className={selectClass}
                                value={form.data.product_id}
                                onChange={(e) => form.setData('product_id', e.target.value)}
                            >
                                <option value="">Choose service</option>
                                {services.map((service) => (
                                    <option key={service.id} value={service.id}>{service.name}</option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Customer name" required error={form.errors.customer_name}>
                            <Input value={form.data.customer_name} disabled={editing}
                                onChange={(e) => form.setData('customer_name', e.target.value)} />
                        </Field>
                        <Field label="Phone" required error={form.errors.customer_phone}>
                            <Input value={form.data.customer_phone} disabled={editing} inputMode="numeric"
                                onChange={(e) => form.setData('customer_phone', phoneDigits(e.target.value))} />
                        </Field>
                    </div>
                    {!editing && (
                        <Field label="Email" hint="Optional. Confirmation and the thank-you go here." error={form.errors.customer_email}>
                            <Input type="email" value={form.data.customer_email}
                                onChange={(e) => form.setData('customer_email', e.target.value)} />
                        </Field>
                    )}
                    {(editing ? !!booking.address : selected?.requires_address) && (
                        <Field label="Address" required={selected?.requires_address} error={form.errors.address}>
                            <Input value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)} />
                        </Field>
                    )}
                    <Field label="Date and time" required error={form.errors.scheduled_at}
                        hint={timeZone ? `Shop time (${timeZone.replace(/_/g, ' ')})` : undefined}>
                        <Input type="datetime-local" value={form.data.scheduled_at}
                            onChange={(e) => form.setData('scheduled_at', e.target.value)} />
                    </Field>
                    {branches.length > 1 && (
                        <Field label="Branch" required error={form.errors.branch_id}>
                            <select
                                className={selectClass}
                                value={form.data.branch_id}
                                onChange={(e) => form.setData('branch_id', e.target.value)}
                            >
                                <option value="">Choose branch</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>{branch.name}</option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field label="Person" error={form.errors.assigned_user_id}>
                        <select
                            className={selectClass}
                            value={form.data.assigned_user_id}
                            onChange={(e) => form.setData('assigned_user_id', e.target.value)}
                        >
                            <option value="">Nobody yet</option>
                            {assignees.map((person) => (
                                <option key={person.id} value={person.id}>{person.name}</option>
                            ))}
                        </select>
                    </Field>
                    {editing && (
                        <Field label="Status" error={form.errors.status}>
                            <select className={selectClass} value={form.data.status}
                                onChange={(e) => form.setData('status', e.target.value)}>
                                {['pending', 'confirmed', 'done', 'cancelled', 'expired'].map((status) => (
                                    <option key={status} value={status}>{status}</option>
                                ))}
                            </select>
                        </Field>
                    )}
                    <Field label="Notes" error={form.errors.notes}>
                        <TextArea rows={3} value={form.data.notes} error={!!form.errors.notes}
                            onChange={(e) => form.setData('notes', e.target.value)} />
                    </Field>
                </div>
                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>Cancel</Button>
                    <Button type="submit" disabled={form.processing}>Save booking</Button>
                </div>
            </form>
        </Drawer>
    );
}
