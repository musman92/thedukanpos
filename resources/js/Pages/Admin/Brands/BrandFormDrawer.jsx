import Button from '@/Components/Ui/Button';
import Drawer from '@/Components/Ui/Drawer';
import ImageUploadField from '@/Components/Ui/ImageUploadField';
import Input, { Field } from '@/Components/Ui/Input';
import { useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const emptyData = () => ({
    name: '',
    code: '',
    is_active: true,
    image: null,
    remove_image: false,
});

function revokePreview(url) {
    if (url?.startsWith('blob:')) {
        URL.revokeObjectURL(url);
    }
}

export default function BrandFormDrawer({ open, brand = null, onClose }) {
    const editing = !!brand;
    const [previewUrl, setPreviewUrl] = useState(null);
    const form = useForm(emptyData());
    const resetKey = `${open ? 1 : 0}-${brand?.id ?? 'new'}`;

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        form.clearErrors();

        if (brand) {
            form.setData({
                name: brand.name || '',
                code: brand.code || '',
                is_active: !!brand.is_active,
                image: null,
                remove_image: false,
            });
            setPreviewUrl((prev) => {
                revokePreview(prev);
                return brand.image_url || null;
            });
        } else {
            form.setData(emptyData());
            setPreviewUrl((prev) => {
                revokePreview(prev);
                return null;
            });
        }

        return undefined;
        // Reset only when drawer opens or the edited brand changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, brand?.id]);

    useEffect(() => {
        return () => revokePreview(previewUrl);
    }, [previewUrl]);

    const onSelectImage = (file) => {
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return URL.createObjectURL(file);
        });

        form.setData((data) => ({
            ...data,
            image: file,
            remove_image: false,
        }));
        form.clearErrors('image');
    };

    const onClearImage = () => {
        setPreviewUrl((prev) => {
            revokePreview(prev);
            return null;
        });
        form.setData((data) => ({
            ...data,
            image: null,
            remove_image: !!brand?.image_url,
        }));
        form.clearErrors('image');
    };

    const submit = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            ...(editing ? { _method: 'put' } : {}),
        }));

        const options = {
            forceFormData: true,
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
            form.post(route('admin.brands.update', brand.id), options);
            return;
        }

        form.post(route('admin.brands.store'), options);
    };

    return (
        <Drawer
            open={open}
            onClose={onClose}
            title={editing ? 'Edit brand' : 'Add brand'}
            description="Brands appear on products and reports."
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
                        hint="Optional. If empty, we assign B01, B02, … automatically."
                    >
                        <Input
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value)}
                            error={!!form.errors.code}
                            placeholder="e.g. nike"
                        />
                    </Field>

                    <ImageUploadField
                        previewUrl={previewUrl}
                        error={form.errors.image}
                        resetKey={resetKey}
                        onSelect={onSelectImage}
                        onClear={onClearImage}
                        onReject={(message) => form.setError('image', message)}
                    />

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
                        {editing ? 'Save changes' : 'Create brand'}
                    </Button>
                </div>
            </form>
        </Drawer>
    );
}
