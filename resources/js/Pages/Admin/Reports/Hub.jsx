import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link } from '@inertiajs/react';

function camelToKebab(key) {
    return String(key)
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .replace(/_/g, '-')
        .toLowerCase();
}

export default function Hub({ reports, links, branch }) {
    const items =
        reports ||
        (links || []).map((l) => ({
            key: l.key,
            title: l.label || l.title,
            description: l.description || (l.group ? `Group: ${l.group}` : ''),
            href: l.href || `admin.reports.${camelToKebab(l.key)}`,
        }));

    return (
        <AdminLayout title={branch?.name ? `Reports · ${branch.name}` : 'Reports'}>
            <Head title="Reports" />

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                {items.map((r) => {
                    let href = '#';
                    try {
                        href = route(r.href);
                    } catch {
                        href = `#${r.key}`;
                    }
                    return (
                        <Link
                            key={r.key || r.href}
                            href={href}
                            className="rounded-xl border border-stone-200 bg-white p-5 transition hover:border-teal-300 hover:bg-teal-50/40"
                        >
                            <h2 className="font-medium text-stone-900">{r.title}</h2>
                            {r.description ? (
                                <p className="mt-1 text-sm text-stone-500">{r.description}</p>
                            ) : null}
                        </Link>
                    );
                })}
            </div>
        </AdminLayout>
    );
}
