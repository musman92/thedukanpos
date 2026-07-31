import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    code: '',
    is_active: true,
});

export default function UnitFormDrawer({ open, unit = null, onClose }) {
    const editing = !!unit;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (unit) {
            form.setData({
                name: unit.name || '',
                code: unit.code || '',
                is_active: !!unit.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, unit?.id]);

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
            form.post(route('admin.units.update', unit.id), options);
            return;
        }

        form.post(route('admin.units.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit unit' : 'Add unit'}
            description="Units of measure used on products (pcs, carton, kg…)."
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
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional. Lowercase labels like pcs, kg. Blank assigns u01, u02…"
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. pcs"
                        />
                    </Field>

                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        />
                        Active
                    </label>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create unit'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
