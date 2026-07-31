import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import ThemeToggle from '@/Components/ThemeToggle';
import { Head, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: 'admin@dukanpos.test',
        password: '',
        remember: false,
    });

    return (
        <div className="relative flex min-h-screen items-center justify-center bg-theme-bg px-4">
            <Head title="Platform login" />

            <div className="absolute right-4 top-4">
                <ThemeToggle />
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    post(route('platform.login.store'));
                }}
                className="dp-card w-full max-w-md overflow-visible p-6"
            >
                <div className="mb-6 flex items-center gap-3">
                    <div
                        className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
                        style={{ background: 'var(--color-brand-mark)' }}
                    >
                        D
                    </div>
                    <div>
                        <p className="text-[11px] font-semibold uppercase tracking-wide text-theme-ink-muted">
                            Platform
                        </p>
                        <h1 className="font-display text-xl font-semibold tracking-tight text-theme-ink">
                            DukanPOS operator
                        </h1>
                    </div>
                </div>
                <p className="mb-6 text-sm text-theme-ink-soft">
                    Landlord control plane — not shop login.
                </p>

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
        </div>
    );
}
