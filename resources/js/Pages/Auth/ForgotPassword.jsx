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

export default function ForgotPassword({ status }) {
    const { t } = useI18n();
    const rememberedShop = savedShopCode();
    const { data, setData, post, processing, errors, transform } = useForm({
        shop_code: rememberedShop,
        username: '',
    });

    const submit = (e) => {
        e.preventDefault();

        const shopCode = data.shop_code.trim().toLowerCase();
        const username = data.username.trim().toLowerCase();
        if (shopCode) {
            window.localStorage.setItem(shopCodeKey, shopCode);
        }

        transform((form) => ({
            ...form,
            shop_code: shopCode,
            username,
        }));

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title={t('auth.forgot_title')} />

            <div className="mb-6">
                <h1 className="font-display text-xl tracking-tight text-theme-ink">
                    {t('auth.forgot_title')}
                </h1>
                <p className="mt-1 text-sm text-theme-ink-soft">{t('auth.forgot_hint')}</p>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-theme-success">{status}</div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="shop_code" value={t('auth.shop_code')} />
                    <TextInput
                        id="shop_code"
                        type="text"
                        name="shop_code"
                        value={data.shop_code}
                        className="mt-1 block w-full"
                        placeholder="shop1"
                        autoComplete="organization"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        isFocused={!rememberedShop}
                        onChange={(e) => setData('shop_code', e.target.value)}
                        required
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
                        placeholder="admin"
                        autoComplete="username"
                        autoCapitalize="none"
                        autoCorrect="off"
                        spellCheck={false}
                        isFocused={!!rememberedShop}
                        onChange={(e) => setData('username', e.target.value)}
                        required
                    />
                    <InputError message={errors.username} className="mt-2" />
                </div>

                <div className="mt-6">
                    <PrimaryButton className="w-full min-h-12 justify-center" disabled={processing}>
                        {processing ? t('auth.forgot_sending') : t('auth.forgot_submit')}
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
