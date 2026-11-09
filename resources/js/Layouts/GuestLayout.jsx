import { useI18n } from '@/hooks/useI18n';
import ThemeToggle from '@/Components/ThemeToggle';

const points = ['auth.brand_point_billing', 'auth.brand_point_stock', 'auth.brand_point_khata'];

export default function GuestLayout({ children }) {
    const { t } = useI18n();

    return (
        <div className="min-h-[100dvh] bg-theme-bg lg:flex">
            <aside className="relative hidden w-[60%] overflow-hidden bg-[#f6f3ee] lg:sticky lg:top-0 lg:flex lg:h-[100dvh] lg:flex-col">
                <img
                    src="/brand/auth-shop.jpg"
                    alt=""
                    className="absolute inset-0 h-full w-full object-cover opacity-40"
                />
                <div className="absolute inset-0 bg-gradient-to-r from-[#f6f3ee]/85 via-[#f6f3ee]/55 to-[#f6f3ee]/25" />

                <div className="relative z-10 flex h-full flex-col px-12 py-10 xl:px-16">
                    <img
                        src="/brand/logo_horizontal.png"
                        alt="theDukanPOS"
                        className="h-11 w-auto self-start"
                    />

                    <div className="my-auto max-w-lg py-10">
                        <h2 className="font-display text-4xl leading-tight tracking-tight text-[#1c3c62]">
                            {t('auth.brand_title')}
                        </h2>
                        <p className="mt-4 max-w-md text-base leading-relaxed text-[#3d4f66]">
                            {t('auth.brand_body')}
                        </p>
                        <ul className="mt-8 space-y-3">
                            {points.map((key) => (
                                <li key={key} className="flex items-start gap-3 text-sm text-[#24364d]">
                                    <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[#e07820]" />
                                    {t(key)}
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="border-t border-[#1c3c62]/15 pt-6">
                        <p className="text-xs font-semibold uppercase tracking-[0.14em] text-[#5e6b7a]">
                            {t('auth.brand_contact')}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-x-8 gap-y-2 text-sm font-medium text-[#1c3c62]">
                            <a
                                href="https://wa.me/923127032292"
                                target="_blank"
                                rel="noreferrer"
                                className="hover:text-[#e07820]"
                            >
                                {t('auth.brand_whatsapp')} · 0312 7032292
                            </a>
                            <a href="tel:+923127032292" className="hover:text-[#e07820]">
                                {t('auth.brand_call')} · +92 312 7032292
                            </a>
                        </div>
                    </div>
                </div>
            </aside>

            <div className="relative flex min-h-[100dvh] w-full flex-col bg-theme-surface lg:w-[40%]">
                <div className="absolute end-3 top-[max(.75rem,env(safe-area-inset-top))] z-10 sm:end-5">
                    <ThemeToggle />
                </div>

                <div className="flex flex-1 flex-col justify-center px-5 py-16 sm:px-10 lg:px-12">
                    <div className="mx-auto w-full max-w-md">
                        <div className="mb-8 flex justify-center lg:hidden">
                            <span className="rounded-xl bg-[#f4f7fb] px-4 py-3">
                                <img
                                    src="/brand/logo_horizontal.png"
                                    alt="theDukanPOS"
                                    className="h-9 w-auto"
                                />
                            </span>
                        </div>
                        {children}
                        <p className="mt-8 text-center text-xs text-theme-ink-muted lg:hidden">
                            <a
                                href="https://wa.me/923127032292"
                                className="font-medium text-theme-primary"
                            >
                                {t('auth.brand_whatsapp')} 0312 7032292
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    );
}
