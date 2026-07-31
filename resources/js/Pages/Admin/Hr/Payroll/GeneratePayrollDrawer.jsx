import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

function monthDefaults() {
    const now = new Date();
    const start = new Date(now.getFullYear(), now.getMonth(), 1);
    const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    const iso = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };
    return { period_start: iso(start), period_end: iso(end) };
}

export default function GeneratePayrollDrawer({ open, onClose }) {
    const form = useForm(monthDefaults());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();
        form.setData(monthDefaults());

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const submit = (e) => {
        e.preventDefault();

        form.post(route('admin.hr.payroll.store'), {
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
            title="Generate payroll"
            description="Creates a draft run for active employees in the current branch, including pending bonuses and deductions."
            width="md"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Period start" required error={form.errors.period_start}>
                        <Input
                            type="date"
                            value={form.data.period_start}
                            onChange={(e) => form.setData('period_start', e.target.value)}
                            error={!!form.errors.period_start}
                            autoFocus
                        />
                    </Field>
                    <Field label="Period end" required error={form.errors.period_end}>
                        <Input
                            type="date"
                            value={form.data.period_end}
                            onChange={(e) => form.setData('period_end', e.target.value)}
                            error={!!form.errors.period_end}
                        />
                    </Field>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        Generate
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
