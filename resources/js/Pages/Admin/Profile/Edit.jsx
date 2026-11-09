import Button from '@/Components/Ui/Button';
import Input, { Field } from '@/Components/Ui/Input';
import { useI18n } from '@/hooks/useI18n';
import AdminLayout from '@/Layouts/AdminLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Edit({ profile }) {
    const { t } = useI18n();
    const { tenant } = usePage().props;
    const login = tenant?.code ? `${profile.username}@${tenant.code}` : profile.username;

    const details = useForm({
        name: profile.name || '',
        email: profile.email || '',
        phone: profile.phone || '',
    });

    const password = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const saveDetails = (event) => {
        event.preventDefault();
        details.put(route('admin.profile.update'), { preserveScroll: true });
    };

    const savePassword = (event) => {
        event.preventDefault();
        password.put(route('admin.profile.password'), {
            preserveScroll: true,
            onSuccess: () => password.reset(),
        });
    };

    return (
        <AdminLayout title={t('profile.title')}>
            <Head title={t('profile.title')} />
            <div className="mx-auto grid max-w-3xl gap-4">
                <form className="dp-card space-y-4 p-5" onSubmit={saveDetails}>
                    <div>
                        <h2 className="text-base font-semibold text-theme-ink">{t('profile.details')}</h2>
                        {profile.role && (
                            <p className="mt-1 text-sm text-theme-ink-muted">
                                {t('profile.role')}: {profile.role}
                            </p>
                        )}
                    </div>
                    <Field label={t('profile.name')} required error={details.errors.name}>
                        <Input
                            value={details.data.name}
                            error={!!details.errors.name}
                            onChange={(event) => details.setData('name', event.target.value)}
                            autoComplete="name"
                            required
                        />
                    </Field>
                    <Field label={t('profile.username')} hint={t('profile.username_hint')}>
                        <Input value={login || ''} disabled readOnly />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('profile.email')} error={details.errors.email}>
                            <Input
                                type="email"
                                value={details.data.email}
                                error={!!details.errors.email}
                                onChange={(event) => details.setData('email', event.target.value)}
                                autoComplete="email"
                            />
                        </Field>
                        <Field label={t('profile.phone')} error={details.errors.phone}>
                            <Input
                                value={details.data.phone}
                                error={!!details.errors.phone}
                                onChange={(event) => details.setData('phone', event.target.value)}
                                autoComplete="tel"
                            />
                        </Field>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={details.processing}>
                            {details.processing ? t('common.loading') : t('common.save')}
                        </Button>
                    </div>
                </form>

                <form className="dp-card space-y-4 p-5" onSubmit={savePassword}>
                    <div>
                        <h2 className="text-base font-semibold text-theme-ink">{t('profile.password')}</h2>
                        <p className="mt-1 text-sm text-theme-ink-muted">{t('profile.password_hint')}</p>
                    </div>
                    <Field label={t('profile.current_password')} required error={password.errors.current_password}>
                        <Input
                            type="password"
                            value={password.data.current_password}
                            error={!!password.errors.current_password}
                            onChange={(event) => password.setData('current_password', event.target.value)}
                            autoComplete="current-password"
                            required
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('profile.new_password')} required error={password.errors.password}>
                            <Input
                                type="password"
                                value={password.data.password}
                                error={!!password.errors.password}
                                onChange={(event) => password.setData('password', event.target.value)}
                                autoComplete="new-password"
                                required
                            />
                        </Field>
                        <Field
                            label={t('profile.confirm_password')}
                            required
                            error={password.errors.password_confirmation}
                        >
                            <Input
                                type="password"
                                value={password.data.password_confirmation}
                                error={!!password.errors.password_confirmation}
                                onChange={(event) =>
                                    password.setData('password_confirmation', event.target.value)
                                }
                                autoComplete="new-password"
                                required
                            />
                        </Field>
                    </div>
                    <div className="flex justify-end">
                        <Button type="submit" disabled={password.processing}>
                            {password.processing ? t('common.loading') : t('common.save')}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
