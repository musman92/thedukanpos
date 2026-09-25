import AdminLayout from '@/Layouts/AdminLayout';
import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import { Head, router, useForm } from '@inertiajs/react';

const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

const selectClass =
    'w-full rounded-lg border border-theme-border bg-theme-surface px-3.5 py-2.5 text-sm text-theme-ink outline-none transition focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20';

const checkboxClass =
    'rounded border-theme-border bg-theme-surface text-theme-primary focus:ring-theme-primary disabled:cursor-not-allowed disabled:opacity-60';

export default function Settings({ settings, public_url: publicUrl, google }) {
    const form = useForm({
        max_concurrent: settings.max_concurrent,
        default_duration_minutes: settings.default_duration_minutes,
        public_enabled: !!settings.public_enabled,
        allow_staff_overbook: !!settings.allow_staff_overbook,
        sales_ui: settings.sales_ui,
        business_hours: settings.business_hours,
        google_calendar_id: settings.google_calendar_id || 'primary',
    });

    const setHours = (day, patch) => form.setData('business_hours', {
        ...form.data.business_hours,
        [day]: { ...form.data.business_hours[day], ...patch },
    });

    return (
        <AdminLayout title="Booking settings" description="Availability, capacity, public link, sales flow, and calendar sync.">
            <Head title="Booking settings" />
            <form className="mx-auto max-w-4xl space-y-6" onSubmit={(e) => {
                e.preventDefault();
                form.put(route('admin.bookings.settings.update'));
            }}>
                <section className="dp-card space-y-4 p-5">
                    <h2 className="font-semibold">Public booking</h2>
                    <Field label="Public booking link">
                        <div className="flex gap-2"><Input readOnly value={publicUrl} />
                            <Button type="button" variant="secondary" onClick={() => navigator.clipboard.writeText(publicUrl)}>Copy</Button></div>
                    </Field>
                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input type="checkbox" className={checkboxClass} checked={form.data.public_enabled}
                            onChange={(e) => form.setData('public_enabled', e.target.checked)} />
                        Accept public bookings
                    </label>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label="Concurrent capacity" hint="0 means unlimited." error={form.errors.max_concurrent}>
                            <Input type="number" min="0" value={form.data.max_concurrent}
                                onChange={(e) => form.setData('max_concurrent', e.target.value)} />
                        </Field>
                        <Field label="Default duration (minutes)" error={form.errors.default_duration_minutes}>
                            <Input type="number" min="5" step="5" value={form.data.default_duration_minutes}
                                onChange={(e) => form.setData('default_duration_minutes', e.target.value)} />
                        </Field>
                        <Field label="Sales screen">
                            <select className={selectClass} value={form.data.sales_ui}
                                onChange={(e) => form.setData('sales_ui', e.target.value)}>
                                <option value="orders">Orders (recommended)</option>
                                <option value="pos">POS</option>
                                <option value="both">Both</option>
                            </select>
                        </Field>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-theme-ink">
                        <input type="checkbox" className={checkboxClass} checked={form.data.allow_staff_overbook}
                            onChange={(e) => form.setData('allow_staff_overbook', e.target.checked)} />
                        Allow staff to override capacity
                    </label>
                </section>

                <section className="dp-card space-y-4 p-5">
                    <h2 className="font-semibold">Business hours</h2>
                    {days.map((day) => {
                        const value = form.data.business_hours[day];
                        return <div key={day} className="grid items-center gap-3 sm:grid-cols-[8rem_1fr_1fr]">
                            <label className="flex items-center gap-2 capitalize text-sm text-theme-ink">
                                <input type="checkbox" className={checkboxClass} checked={!!value.enabled}
                                    onChange={(e) => setHours(day, { enabled: e.target.checked })} />
                                {day}
                            </label>
                            <Input type="time" value={value.open} disabled={!value.enabled}
                                onChange={(e) => setHours(day, { open: e.target.value })} />
                            <Input type="time" value={value.close} disabled={!value.enabled}
                                onChange={(e) => setHours(day, { close: e.target.value })} />
                        </div>;
                    })}
                </section>

                <section className="dp-card space-y-4 p-5">
                    <h2 className="font-semibold">Google Calendar (optional)</h2>
                    {!google.configured && <p className="text-sm text-theme-ink-muted">Set Google Calendar OAuth credentials on the server to enable connection.</p>}
                    {google.configured && !google.connected && <div className="space-y-3">
                        <Field label="Calendar ID"><Input value={form.data.google_calendar_id}
                            onChange={(e) => form.setData('google_calendar_id', e.target.value)} /></Field>
                        <Button type="button" onClick={() => { form.put(route('admin.bookings.settings.update'), {
                            onSuccess: () => window.location.assign(route('admin.bookings.google.connect')),
                        }); }}>Save and connect Google</Button>
                    </div>}
                    {google.connected && <div className="flex items-center justify-between">
                        <p className="text-sm">Connected to {settings.google_calendar_id}</p>
                        <Button type="button" variant="danger"
                            onClick={() => router.delete(route('admin.bookings.google.disconnect'))}>Disconnect</Button>
                    </div>}
                </section>

                <div className="flex justify-end"><Button type="submit" disabled={form.processing}>Save settings</Button></div>
            </form>
        </AdminLayout>
    );
}
