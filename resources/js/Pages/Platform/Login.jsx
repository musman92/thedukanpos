import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: 'admin@dukanpos.test',
        password: '',
        remember: false,
    });

    return (
        <GuestLayout>
            <Head title="Platform login" />

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('platform.login.store'));
                }}
            >
                <div className="mb-6">
                    <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                        Platform
                    </p>
                    <h1 className="font-display text-xl font-semibold tracking-tight text-theme-ink">
                        DukanPOS operator
                    </h1>
                    <p className="mt-1 text-sm text-theme-ink-soft">
                        Landlord control plane — not shop login.
                    </p>
                </div>

                <div className="space-y-4">
                    <Field label="Email" error={errors.email}>
                        <Input
                            type="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            error={!!errors.email}
                            autoComplete="username"
                        />
                    </Field>
                    <Field label="Password" error={errors.password}>
                        <Input
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            error={!!errors.password}
                            autoComplete="current-password"
                        />
                    </Field>
                    <label className="inline-flex cursor-pointer items-center gap-2 text-sm text-theme-ink">
                        <input
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-theme-border text-theme-primary focus:ring-theme-primary/20"
                        />
                        Remember me
                    </label>
                    <Button type="submit" disabled={processing} className="w-full">
                        Sign in
                    </Button>
                </div>
            </form>
        </GuestLayout>
    );
}
