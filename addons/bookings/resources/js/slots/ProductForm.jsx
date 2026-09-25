import Input, { Field } from '@/Components/Ui/Input';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import axios from 'axios';
import { useEffect } from 'react';

const PRICE_MODES = [
    { value: 'fixed', label: 'Fixed price' },
    { value: 'starting_from', label: 'Starting from' },
];

const defaults = {
    is_bookable: false,
    price_mode: 'fixed',
    requires_address: false,
    duration_minutes: '',
};

export default function ProductForm({ data, setData, errors, product }) {
    const settings = data.addons?.bookings || defaults;

    const update = (patch) => {
        setData('addons', {
            ...(data.addons || {}),
            bookings: { ...settings, ...patch },
        });
    };

    useEffect(() => {
        if (!product?.id) {
            if (!data.addons?.bookings) update(defaults);
            return;
        }

        axios
            .get(route('admin.bookings.product-settings.show', product.id))
            .then(({ data: saved }) => update({
                ...defaults,
                ...saved,
                duration_minutes: saved.duration_minutes || '',
            }));
        // Product id is the stable load boundary; form updates should not refetch.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [product?.id]);

    if (data.kind !== 'service') return null;

    return (
        <div className="space-y-4 rounded-lg border border-theme-border bg-theme-bg p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                Booking options
            </p>
            <div className="flex flex-wrap gap-5">
                <label className="flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        checked={!!settings.is_bookable}
                        onChange={(e) => update({ is_bookable: e.target.checked })}
                    />
                    Available for online booking
                </label>
                <label className="flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                    <input
                        type="checkbox"
                        className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                        checked={!!settings.requires_address}
                        onChange={(e) => update({ requires_address: e.target.checked })}
                    />
                    Customer address required
                </label>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Public price label" error={errors?.['addons.bookings.price_mode']}>
                    <SearchableSelect
                        options={PRICE_MODES}
                        value={settings.price_mode}
                        searchable={false}
                        onChange={(price_mode) => update({ price_mode })}
                    />
                </Field>
                <Field label="Duration (minutes)" hint="Blank uses the booking default.">
                    <Input
                        type="number"
                        min="5"
                        step="5"
                        value={settings.duration_minutes}
                        onChange={(e) => update({ duration_minutes: e.target.value })}
                    />
                </Field>
            </div>
        </div>
    );
}
