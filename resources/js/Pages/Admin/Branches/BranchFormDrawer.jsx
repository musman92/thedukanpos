import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field, TextArea } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    name: '',
    code: '',
    phone: '',
    address: '',
    is_active: true,
});

export default function BranchFormDrawer({ open, branch = null, onClose }) {
    const editing = !!branch;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (branch) {
            form.setData({
                name: branch.name || '',
                code: branch.code || '',
                phone: branch.phone || '',
                address: branch.address || '',
                is_active: !!branch.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, branch?.id]);

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
            onFinish: () => form.transform((d) => d),
        };

        if (editing) {
            form.post(route('admin.branches.update', branch.id), options);
            return;
        }

        form.post(route('admin.branches.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit branch' : 'Add branch'}
            description="Branches isolate stock, sales, and shifts. Inactive branches are hidden from the switcher."
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
                            placeholder="e.g. Main Branch"
                        />
                    </Field>
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional. If empty, we assign BR01, BR02, … automatically."
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. MAIN"
                        />
                    </Field>
                    <Field label="Phone" error={form.errors.phone}>
                        <Input
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                            error={!!form.errors.phone}
                            placeholder="Optional"
                        />
                    </Field>
                    <Field label="Address" error={form.errors.address}>
                        <TextArea
                            rows={3}
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                            error={!!form.errors.address}
                            placeholder="Optional"
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
                    {form.errors.is_active && (
                        <p className="text-sm text-theme-danger">{form.errors.is_active}</p>
                    )}
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create branch'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
