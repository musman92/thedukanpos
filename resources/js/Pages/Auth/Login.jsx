import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/hooks/useI18n';
import { Head, Link, useForm } from '@inertiajs/react';

const shopCodeKey = 'dukanpos.shop_code';

function savedShopCode() {
    if (typeof window === 'undefined') {
        return '';
    }

    return window.localStorage.getItem(shopCodeKey) || '';
}

export default function Login({ status }) {
    const { t } = useI18n();
    const rememberedShop = savedShopCode();
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        tenant_code: rememberedShop,
        username: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        const shopCode = data.tenant_code.trim().toLowerCase();
        const username = data.username.trim().toLowerCase();
        if (shopCode) {
            window.localStorage.setItem(shopCodeKey, shopCode);
        }

        transform((form) => ({
            ...form,
            tenant_code: shopCode,
            username,
        }));

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.log_in')} />

            <div className="mb-6">
                <h1 className="font-display text-xl tracking-tight text-theme-ink">
                    {t('auth.sign_in')}
                </h1>
                <p className="mt-1 text-sm text-theme-ink-soft">{t('auth.login_hint')}</p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-theme-success">
                    {status}
                </div>
            )}

            {errors.login && (
                <div className="mb-4 text-sm font-medium text-theme-danger">
                    {errors.login}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="tenant_code" value={t('auth.shop_code')} />
                    <TextInput
                        id="tenant_code"
                        type="text"
                        name="tenant_code"
                        value={data.tenant_code}
                        className="mt-1 block w-full"
                        placeholder="shop1"
                        autoComplete="organization"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        isFocused={!rememberedShop}
                        onChange={(e) => setData('tenant_code', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-theme-ink-muted">{t('auth.shop_code_hint')}</p>
                    <InputError message={errors.tenant_code} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="username" value={t('auth.username')} />
                    <TextInput
                        id="username"
                        type="text"
                        name="username"
                        value={data.username}
                        className="mt-1 block w-full"
                        placeholder="admin"
                        autoComplete="username"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        isFocused={!!rememberedShop}
                        onChange={(e) => setData('username', e.target.value)}
                    />
                    <p className="mt-1 text-xs text-theme-ink-muted">{t('auth.username_hint')}</p>
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
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-2 text-end">
                    <Link
                        href={route('password.request')}
                        className="text-sm font-medium text-theme-primary"
                    >
                        {t('auth.forgot_password')}
                    </Link>
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-theme-ink-soft">
                            {t('auth.remember')}
                        </span>
                    </label>
                </div>

                <div className="mt-6 flex items-center justify-end">
                    <PrimaryButton
                        className="w-full min-h-12 justify-center"
                        disabled={processing}
                    >
                        {t('auth.log_in')}
                    </PrimaryButton>
                </div>
            </form>

            <p className="mt-4 text-center text-sm text-theme-ink-soft">
                <Link href={route('register')} className="font-medium text-theme-primary">
                    {t('auth.create_shop')}
                </Link>
            </p>
        </GuestLayout>
    );
}
