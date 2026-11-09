import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { useI18n } from '@/hooks/useI18n';
import { Head, Link, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';

const shopCodeCopy = {
    short: 'auth.shop_code_short',
    invalid: 'auth.shop_code_invalid',
    taken: 'auth.shop_code_taken',
    available: 'auth.shop_code_available',
    failed: 'auth.shop_code_check_failed',
    checking: 'auth.shop_code_checking',
};

async function lookupShopCode(code) {
    const response = await fetch(
        `/api/v1/register/shop-code?shop_code=${encodeURIComponent(code)}`,
        { headers: { Accept: 'application/json' } },
    );
    if (!response.ok) {
        throw new Error('shop code check failed');
    }
    return response.json();
}

export default function Register() {
    const { t } = useI18n();
    const { data, setData, post, processing, errors, reset, transform, clearErrors } = useForm({
        name: '',
        email: '',
        shop_code: '',
        password: '',
        password_confirmation: '',
    });
    const [shopStatus, setShopStatus] = useState({ code: '', state: 'idle' });
    const requestId = useRef(0);
    const checking = shopStatus.state === 'checking';

    const checkShopCode = async () => {
        const code = data.shop_code.trim().toLowerCase();
        if (code.length < 2) {
            setShopStatus({ code, state: code === '' ? 'idle' : 'short' });
            return;
        }

        const id = ++requestId.current;
        setShopStatus({ code, state: 'checking' });
        try {
            const body = await lookupShopCode(code);
            if (id !== requestId.current) {
                return;
            }
            setShopStatus({
                code: body.shop_code || code,
                state: body.available ? 'available' : body.reason || 'invalid',
            });
        } catch {
            if (id !== requestId.current) {
                return;
            }
            setShopStatus({ code, state: 'failed' });
        }
    };

    const shopReady = shopStatus.state === 'available' && shopStatus.code === data.shop_code.trim().toLowerCase();
    const shopMessageKey = shopCodeCopy[shopStatus.state];

    const submit = (e) => {
        e.preventDefault();
        if (!shopReady) {
            return;
        }

        const shopCode = shopStatus.code;
        window.localStorage.setItem('dukanpos.shop_code', shopCode);

        transform((form) => ({
            ...form,
            name: form.name.trim(),
            email: form.email.trim(),
            shop_code: shopCode,
        }));

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title={t('auth.sign_up')} />

            <div className="mb-6">
                <h1 className="font-display text-xl tracking-tight text-theme-ink">
                    {t('auth.create_shop')}
                </h1>
                <p className="mt-1 text-sm text-theme-ink-soft">{t('auth.sign_up_hint')}</p>
            </div>

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="name" value={t('auth.name')} />
                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        autoComplete="name"
                        isFocused
                        onChange={(e) => setData('name', e.target.value)}
                        required
                    />
                    <InputError message={errors.name} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="email" value={t('auth.email')} />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="email"
                        onChange={(e) => setData('email', e.target.value)}
                        required
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="shop_code" value={t('auth.shop_code')} />
                    <div className="relative mt-1">
                        <TextInput
                            id="shop_code"
                            type="text"
                            name="shop_code"
                            value={data.shop_code}
                            className="block w-full pe-32"
                            placeholder="shop1"
                            autoComplete="off"
                            autoCapitalize="none"
                            autoCorrect="off"
                            spellCheck={false}
                            onChange={(e) => {
                                const next = e.target.value;
                                setData('shop_code', next);
                                clearErrors('shop_code');
                                const normalized = next.trim().toLowerCase();
                                if (shopStatus.code === normalized) {
                                    return;
                                }
                                requestId.current += 1;
                                setShopStatus({ code: normalized, state: 'idle' });
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    checkShopCode();
                                }
                            }}
                            required
                        />
                        <button
                            type="button"
                            onClick={checkShopCode}
                            disabled={checking || data.shop_code.trim() === ''}
                            className="absolute inset-y-1 end-1 inline-flex min-w-24 items-center justify-center gap-1.5 rounded-md bg-[var(--color-primary)] px-2.5 text-xs font-semibold text-[var(--color-on-primary)] transition hover:bg-[var(--color-primary-hover)] disabled:opacity-40"
                        >
                            {checking && (
                                <span className="h-3.5 w-3.5 animate-spin rounded-full border-2 border-current border-r-transparent" />
                            )}
                            {t('auth.check')}
                        </button>
                    </div>
                    {shopStatus.state === 'idle' ? (
                        <p className="mt-1 text-xs text-theme-ink-muted">{t('auth.sign_up_shop_hint')}</p>
                    ) : (
                        <p
                            className={`mt-1 text-xs ${
                                shopStatus.state === 'available'
                                    ? 'text-theme-success'
                                    : shopStatus.state === 'checking'
                                      ? 'text-theme-ink-muted'
                                      : 'text-theme-danger'
                            }`}
                        >
                            {shopMessageKey ? t(shopMessageKey) : t('auth.sign_up_shop_hint')}
                        </p>
                    )}
                    <InputError message={errors.shop_code} className="mt-2" />
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

                <div className="mt-6 flex items-center justify-end">
                    <PrimaryButton
                        className="w-full min-h-12 justify-center"
                        disabled={processing || !shopReady}
                    >
                        {processing ? t('auth.creating_shop') : t('auth.sign_up')}
                    </PrimaryButton>
                </div>
            </form>

            <p className="mt-4 text-center text-sm text-theme-ink-soft">
                <Link href={route('login')} className="font-medium text-theme-primary">
                    {t('auth.have_shop')}
                </Link>
            </p>
        </GuestLayout>
    );
}
