import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center justify-center bg-[#f3f1ec] px-4">
            <div className="mb-8 text-center">
                <Link href="/" className="font-display text-3xl tracking-tight text-stone-900">
                    DukanPOS
                </Link>
                <p className="mt-1 text-sm text-stone-500">Retail point of sale</p>
            </div>

            <div className="w-full max-w-md rounded-xl border border-stone-200 bg-white px-6 py-6 shadow-sm">
                {children}
            </div>
        </div>
    );
}
