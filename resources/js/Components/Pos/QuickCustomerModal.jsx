import Button from '@/Components/Ui/Button';
import Modal from '@/Components/Modal';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import axios from 'axios';
import { useEffect, useState } from 'react';

const empty = () => ({
    name: '',
    phone: '',
    address: '',
});

export default function QuickCustomerModal({ open, onClose, onCreated }) {
    const [form, setForm] = useState(empty());
    const [errors, setErrors] = useState({});
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        if (!open) return;
        setForm(empty());
        setErrors({});
        setMessage('');
    }, [open]);

    const submit = async (e) => {
        e.preventDefault();
        setBusy(true);
        setMessage('');
        setErrors({});
        try {
            const { data } = await axios.post(route('pos.customers.store'), form);
            onCreated?.(data.customer);
            onClose?.();
        } catch (err) {
            const payload = err.response?.data;
            setErrors(payload?.errors || {});
            setMessage(payload?.message || 'Could not create customer.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Modal show={open} onClose={onClose} maxWidth="md">
            <form onSubmit={submit} className="p-5">
                <h3 className="font-display text-xl tracking-tight text-theme-ink">
                    New customer
                </h3>
                <p className="mt-1 text-sm text-theme-ink-muted">
                    Address is required for delivery orders.
                </p>

                {message && (
                    <div className="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        {message}
                    </div>
                )}

                <div className="mt-4 space-y-3">
                    <Field label="Name" required error={errors.name?.[0]}>
                        <Input
                            value={form.name}
                            onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))}
                            autoFocus
                            error={!!errors.name}
                        />
                    </Field>
                    <Field label="Phone" error={errors.phone?.[0]}>
                        <Input
                            value={form.phone}
                            onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))}
                            error={!!errors.phone}
                        />
                    </Field>
                    <Field label="Address" required error={errors.address?.[0]}>
                        <TextArea
                            rows={3}
                            value={form.address}
                            onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))}
                            error={!!errors.address}
                        />
                    </Field>
                </div>

                <div className="mt-5 flex justify-end gap-2">
                    <Button type="button" variant="secondary" onClick={onClose} disabled={busy}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={busy}>
                        {busy ? 'Saving…' : 'Save & select'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
