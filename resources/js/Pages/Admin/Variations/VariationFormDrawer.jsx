import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect } from 'react';

const emptyOption = () => ({
    id: null,
    name: '',
    code: '',
    is_active: true,
});

const emptyData = () => ({
    name: '',
    code: '',
    is_active: true,
    options: [emptyOption()],
});

export default function VariationFormDrawer({ open, variation = null, onClose }) {
    const editing = !!variation;
    const form = useForm(emptyData());

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (variation) {
            const options =
                variation.options?.length > 0
                    ? variation.options.map((opt) => ({
                          id: opt.id,
                          name: opt.name || '',
                          code: opt.code || '',
                          is_active: opt.is_active !== false,
                      }))
                    : [emptyOption()];

            form.setData({
                name: variation.name || '',
                code: variation.code || '',
                is_active: !!variation.is_active,
                options,
            });
        } else {
            form.setData(emptyData());
        }

        return undefined;
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, variation?.id]);

    const setOption = (index, key, value) => {
        form.setData(
            'options',
            form.data.options.map((opt, i) => (i === index ? { ...opt, [key]: value } : opt)),
        );
    };

    const addOption = () => {
        form.setData('options', [...form.data.options, emptyOption()]);
    };

    const removeOption = (index) => {
        const next = form.data.options.filter((_, i) => i !== index);
        form.setData('options', next.length > 0 ? next : [emptyOption()]);
    };

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            options: (data.options || [])
                .filter((opt) => String(opt.name || '').trim() !== '')
                .map((opt, index) => ({
                    id: opt.id || null,
                    name: String(opt.name || '').trim(),
                    code: String(opt.code || '').trim(),
                    sort_order: index,
                    is_active: !!opt.is_active,
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
            form.post(route('admin.variations.update', variation.id), options);
            return;
        }

        form.post(route('admin.variations.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit variation' : 'Add variation'}
            description="Reusable types (Size, Color) and their options for product SKUs."
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
                            placeholder="e.g. Size"
                        />
                    </Field>
                    <Field
                        label="Code"
                        error={form.errors.code}
                        hint="Optional. If empty, we assign V01, V02, … automatically."
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. SIZE"
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
                            <p className="text-sm font-medium text-theme-ink">Options</p>
                            <Button type="button" variant="secondary" size="sm" onClick={addOption}>
                                <Plus className="h-3.5 w-3.5" strokeWidth={2.25} />
                                Add option
                            </Button>
                        </div>
                        {form.errors.options && (
                            <p className="text-xs text-theme-danger">{form.errors.options}</p>
                        )}
                        <div className="space-y-2">
                            {form.data.options.map((opt, index) => (
                                <div
                                    key={opt.id ?? `new-${index}`}
                                    className="flex flex-wrap items-start gap-2 rounded-lg border border-theme-border p-2"
                                >
                                    <div className="min-w-[8rem] flex-1">
                                        <Input
                                            value={opt.name}
                                            onChange={(e) => setOption(index, 'name', e.target.value)}
                                            placeholder="Option name"
                                        />
                                    </div>
                                    <div className="w-24">
                                        <Input
                                            value={opt.code}
                                            onChange={(e) => setOption(index, 'code', e.target.value)}
                                            placeholder="Code"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeOption(index)}
                                        className="mt-1 rounded-md p-2 text-theme-ink-muted hover:bg-theme-bg hover:text-theme-danger"
                                        aria-label="Remove option"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </button>
                                </div>
                            ))}
                        </div>
                        <p className="text-xs text-theme-ink-muted">
                            Leave option rows blank to skip. Empty type codes auto-assign; option codes are optional.
                        </p>
                    </div>
                </div>

                <div className="mt-auto flex justify-end gap-2 border-t border-theme-border pt-5">
                    <Button type="button" variant="secondary" onClick={onClose}>
                        Cancel
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create variation'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
