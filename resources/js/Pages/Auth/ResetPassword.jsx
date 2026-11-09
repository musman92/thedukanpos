import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/hooks/useI18n';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ResetPassword({ token, shop_code, username }) {
    const { t } = useI18n();
    const { data, setData, post, processing, errors, reset } = useForm({
        token,
        shop_code: shop_code || '',
        username: username || '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('password.store'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.reset_title')} />

            <div className="mb-6">
                <h1 className="font-display text-xl tracking-tight text-theme-ink">
                    {t('auth.reset_title')}
                </h1>
                <p className="mt-1 text-sm text-theme-ink-soft">{t('auth.reset_hint')}</p>
            </div>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="shop_code" value={t('auth.shop_code')} />
                    <TextInput
                        id="shop_code"
                        type="text"
                        name="shop_code"
                        value={data.shop_code}
                        className="mt-1 block w-full"
                        autoComplete="off"
                        readOnly
                    />
                    <InputError message={errors.shop_code} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="username" value={t('auth.username')} />
                    <TextInput
                        id="username"
                        type="text"
                        name="username"
                        value={data.username}
                        className="mt-1 block w-full"
                        autoComplete="off"
                        readOnly
                    />
                    <InputError message={errors.username} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value={t('auth.password')} />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        isFocused
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password_confirmation" value={t('auth.confirm_password')} />
                    <TextInput
                        id="password_confirmation"
                        type="password"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                    <InputError message={errors.password_confirmation} className="mt-2" />
                </div>

                <div className="mt-6">
                    <PrimaryButton className="w-full min-h-12 justify-center" disabled={processing}>
                        {processing ? t('auth.reset_saving') : t('auth.reset_submit')}
                    </PrimaryButton>
                </div>
            </form>

            <p className="mt-4 text-center text-sm text-theme-ink-soft">
                <Link href={route('login')} className="font-medium text-theme-primary">
                    {t('auth.back_to_login')}
                </Link>
            </p>
        </GuestLayout>
    );
}
