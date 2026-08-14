import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-[100dvh] flex-col items-center justify-center bg-[#f3f1ec] px-3 py-[max(1rem,env(safe-area-inset-top))]">
            <div className="mb-6 text-center sm:mb-8">
                <Link href="/" className="font-display text-3xl tracking-tight text-stone-900">
                    DukanPOS
                </Link>
                <p className="mt-1 text-sm text-stone-500">Retail point of sale</p>
            </div>

            <div className="w-full max-w-md rounded-2xl border border-stone-200 bg-white px-4 py-5 shadow-sm sm:px-6 sm:py-6">
                {children}
            </div>
        </div>
    );
}
