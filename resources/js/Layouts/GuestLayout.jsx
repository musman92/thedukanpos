import ThemeToggle from '@/Components/ThemeToggle';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-[100dvh] flex-col items-center justify-center bg-theme-bg px-3 py-[max(1rem,env(safe-area-inset-top))]">
            <div className="absolute end-3 top-[max(.75rem,env(safe-area-inset-top))] sm:end-4">
                <ThemeToggle />
            </div>

            <div className="mb-6 text-center sm:mb-8">
                <Link href="/" className="font-display text-3xl tracking-tight text-theme-ink">
                    DukanPOS
                </Link>
                <p className="mt-1 text-sm text-theme-ink-soft">Retail point of sale</p>
            </div>

            <div className="dp-card w-full max-w-md rounded-2xl px-4 py-5 sm:px-6 sm:py-6">
                {children}
            </div>
        </div>
    );
}
