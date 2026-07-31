import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect } from 'react';

const emptyRack = () => ({
    id: null,
    name: '',
    code: '',
    is_active: true,
});

const emptyData = () => ({
    name: '',
    code: '',
    is_active: true,
    racks: [emptyRack()],
});

export default function SectionFormDrawer({ open, section = null, onClose }) {
    const editing = !!section;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (section) {
            const racks =
                section.racks?.length > 0
                    ? section.racks.map((rack) => ({
                          id: rack.id,
                          name: rack.name || '',
                          code: rack.code || '',
                          is_active: rack.is_active !== false,
                      }))
                    : [emptyRack()];

            form.setData({
                name: section.name || '',
                code: section.code || '',
                is_active: !!section.is_active,
                racks,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, section?.id]);

    const setRack = (index, key, value) => {
        form.setData(
            'racks',
            form.data.racks.map((rack, i) => (i === index ? { ...rack, [key]: value } : rack)),
        );
    };

    const addRack = () => {
        form.setData('racks', [...form.data.racks, emptyRack()]);
    };

    const removeRack = (index) => {
        const next = form.data.racks.filter((_, i) => i !== index);
        form.setData('racks', next.length > 0 ? next : [emptyRack()]);
    };

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            racks: (data.racks || [])
                .filter((rack) => String(rack.name || '').trim() !== '')
                .map((rack) => ({
                    id: rack.id || null,
                    name: String(rack.name || '').trim(),
                    code: String(rack.code || '').trim(),
                    is_active: !!rack.is_active,
                })),
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
            form.post(route('admin.sections.update', section.id), options);
            return;
        }

        form.post(route('admin.sections.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit section' : 'Add section'}
            description="Warehouse/shop sections and racks so staff can find products."
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
                            placeholder="e.g. Aisle A"
                        />
                    </Field>
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional. If empty, we assign S01, S02, … automatically."
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. A"
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

                    <div className="space-y-2">
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-medium text-theme-ink">Racks</p>
                            <Button type="button" variant="secondary" size="sm" onClick={addRack}>
                                <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
                                Add rack
                            </Button>
                        </div>
                        {form.errors.racks && (
                            <p className="text-xs text-theme-danger">{form.errors.racks}</p>
                        )}
                        <div className="space-y-2">
                            {form.data.racks.map((rack, index) => (
                                <div
                                    key={rack.id ?? `new-${index}`}
                                    className="flex flex-wrap items-start gap-2 rounded-lg border border-theme-border p-2"
                                >
                                    <div className="min-w-[8rem] flex-1">
                                        <Input
                                            value={rack.name}
                                            onChange={(e) => setRack(index, 'name', e.target.value)}
                                            placeholder="Rack name"
                                        />
                                    </div>
                                    <div className="w-24">
                                        <Input
                                            value={rack.code}
                                            onChange={(e) => setRack(index, 'code', e.target.value)}
                                            placeholder="Code"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeRack(index)}
                                        className="mt-1 rounded-md p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-danger"
                                        aria-label="Remove rack"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-theme-ink-muted">
                            Leave rack rows blank to skip. Racks in use on products cannot be removed.
                        </p>
                    </div>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create section'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
