import { Link } from '@inertiajs/react';
import { formatAmount } from '@/lib/money';
import {
    ArrowLeftRight,
    HandCoins,
    List,
    Wallet,
} from 'lucide-react';

const NAV = [
    {
        key: 'sources',
        label: 'Sources',
        href: 'admin.finance.money-sources.index',
        icon: Wallet,
    },
    {
        key: 'transfer',
        label: 'Transfer',
        href: 'admin.finance.money-sources.transfer.create',
        icon: ArrowLeftRight,
    },
    {
        key: 'owner-withdrawal',
        label: 'Owner withdrawal',
        href: 'admin.finance.money-sources.owner-withdrawal.create',
        icon: HandCoins,
    },
    {
        key: 'reports',
        label: 'Reports',
        href: 'admin.finance.money-sources.reports',
        icon: List,
    },
];

export default function MoneySourcesShell({
    activeNav = 'sources',
    title = 'Money sources',
    description = 'Manage payment sources, transfers, and owner withdrawals',
    actions = null,
    children,
}) {
    return (
        <div className="dp-card overflow-hidden">
            <div className="flex flex-col gap-3 border-b border-theme-border px-4 py-4 sm:flex-row sm:items-start sm:justify-between sm:px-5">
                <div>
                    <h2 className="text-lg font-semibold text-theme-ink">{title}</h2>
                    <p className="mt-0.5 text-sm text-theme-ink-muted">{description}</p>
                </div>
                {actions}
            </div>

            <div className="flex min-w-0 flex-col md:flex-row">
                <aside className="w-full shrink-0 border-b border-theme-border bg-theme-bg md:w-52 md:border-b-0 md:border-r lg:w-56">
                    <nav className="space-y-1 p-3" aria-label="Money sources">
                        {NAV.map((item) => {
                            const Icon = item.icon;
                            const active = activeNav === item.key;

                            return (
                                <Link
                                    key={item.key}
                                    href={route(item.href)}
                                    className={`flex items-center gap-2.5 rounded-lg px-3 py-2.5 text-sm font-medium transition ${
                                        active
                                            ? 'border-l-4 border-theme-primary bg-theme-primary/10 text-theme-primary'
                                            : 'border-l-4 border-transparent text-theme-ink-soft hover:bg-theme-surface hover:text-theme-ink'
                                    }`}
                                >
                                    <Icon
                                        className={`h-4 w-4 ${active ? 'text-theme-primary' : 'text-theme-ink-muted'}`}
                                        strokeWidth={2.25}
                                    />
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="min-w-0 flex-1 p-4 md:p-5">{children}</div>
            </div>
        </div>
    );
}

export function typeBadgeClass(type) {
    const t = String(type || '').toUpperCase();
    if (t === 'CASH') return 'bg-emerald-100 text-emerald-800';
    if (t === 'BANK') return 'bg-sky-100 text-sky-800';
    if (t === 'OWNER_DRAW') return 'bg-amber-100 text-amber-800';
    return 'bg-violet-100 text-violet-800';
}

export function typeLabel(type) {
    const t = String(type || '').toUpperCase();
    if (t === 'CASH') return 'Cash';
    if (t === 'BANK') return 'Bank';
    if (t === 'APP') return 'App';
    if (t === 'OWNER_DRAW') return 'Owner draw';
    return type;
}

export function money(value) {
    return formatAmount(value);
}
