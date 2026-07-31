import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

export default function Masters() {
    return (
        <AdminLayout title="Catalog masters">
            <Head title="Catalog" />
            <div className="rounded-xl border border-theme-border bg-theme-surface p-6 text-sm text-theme-ink-soft">
                <p>
                    Tax rates moved to{' '}
                    <Link
                        href={route('admin.finance.taxes.index')}
                        className="font-medium text-theme-primary hover:underline"
                    >
                        Financials → Taxes
                    </Link>
                    .
                </p>
            </div>
        </AdminLayout>
    );
}
