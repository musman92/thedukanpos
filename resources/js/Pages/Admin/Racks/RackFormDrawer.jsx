import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

const emptyData = () => ({
    section_id: '',
    name: '',
    code: '',
    is_active: true,
});

export default function RackFormDrawer({ open, rack = null, sections = [], onClose }) {
    const editing = !!rack;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (rack) {
            form.setData({
                section_id: rack.section_id ?? '',
                name: rack.name || '',
                code: rack.code || '',
                is_active: !!rack.is_active,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, rack?.id]);

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            section_id: data.section_id === '' ? null : data.section_id,
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
            form.post(route('admin.racks.update', rack.id), options);
            return;
        }

        form.post(route('admin.racks.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit rack' : 'Add rack'}
            description="Racks belong to a section and show on product locations."
            width="half"
        >
            <form onSubmit={submit} className="flex h-full flex-col">
                <div className="space-y-4">
                    <Field label="Section" required error={form.errors.section_id}>
                        <select
                            value={form.data.section_id}
                            onChange={(e) => form.setData('section_id', e.target.value)}
                            className="h-10 w-full rounded-lg border border-theme-border bg-theme-surface px-3 text-sm text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                        >
                            <option value="">Select section</option>
                            {sections.map((section) => (
                                <option key={section.id} value={section.id}>
                                    {section.name}
                                    {section.code ? ` (${section.code})` : ''}
                                </option>
                            ))}
                        </select>
                    </Field>

                    <Field label="Name" required error={form.errors.name}>
                        <Input
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            error={!!form.errors.name}
                            autoFocus
                            placeholder="e.g. Shelf 1"
                        />
                    </Field>
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional within the section. Blank assigns R01, R02…"
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. A1"
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
                        {editing ? 'Save changes' : 'Create rack'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
