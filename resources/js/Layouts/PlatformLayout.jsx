import PageHeader from '@/Components/Ui/PageHeader';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    FileText,
    LayoutDashboard,
    LogOut,
    PanelLeft,
} from 'lucide-react';
import { useState } from 'react';

const modules = [
    {
        id: 'dashboard',
        type: 'link',
        label: 'Dashboard',
        icon: LayoutDashboard,
        href: 'platform.dashboard',
        match: ['platform.dashboard'],
    },
    {
        id: 'tenants',
        type: 'link',
        label: 'Tenants',
        icon: Building2,
        href: 'platform.tenants.index',
        match: ['platform.tenants'],
    },
    {
        id: 'invoices',
        type: 'link',
        label: 'Invoices',
        icon: FileText,
        href: 'platform.invoices.index',
        match: ['platform.invoices'],
    },
];

function moduleMatches(current, mod) {
    if (!current) return false;
    return mod.match.some(
        (prefix) => current === prefix || current.startsWith(`${prefix}.`) || current.startsWith(prefix),
    );
}

function NavLink({ mod, current }) {
    const Icon = mod.icon;
    const active = moduleMatches(current, mod);

    return (
        <Link href={route(mod.href)} className={`dp-nav-item ${active ? 'dp-nav-item-active' : ''}`}>
            <Icon className="h-4 w-4 shrink-0 opacity-80" strokeWidth={1.75} />
            <span className="min-w-0 flex-1 truncate">{mod.label}</span>
        </Link>
    );
}

function PlatformHeaderActions() {
    const { auth } = usePage().props;
    const email = auth?.user?.email || 'Operator';

    return (
        <div className="flex items-center gap-2">
            <ThemeToggle />
            <div className="hidden items-center gap-2 rounded-lg border border-theme-border bg-theme-surface px-3 py-1.5 sm:flex">
                <span className="max-w-[12rem] truncate text-sm text-theme-ink-soft">{email}</span>
            </div>
            <Link
                href={route('platform.logout')}
                method="post"
                as="button"
                className="dp-icon-btn"
                title="Log out"
            >
                <LogOut className="h-[18px] w-[18px]" strokeWidth={1.75} />
            </Link>
        </div>
    );
}

export default function PlatformLayout({ title, description = null, children, actions = null }) {
    const { flash } = usePage().props;
    const current = route().current();
    const [collapsed, setCollapsed] = useState(false);

    return (
        <div className="dp-app flex min-h-screen">
            <aside
                className={`dp-sidebar sticky top-0 flex h-screen shrink-0 flex-col transition-[width] ${
                    collapsed ? 'w-[4.25rem]' : 'w-60'
                }`}
            >
                <div className="flex h-14 items-center gap-2.5 border-b border-theme-border px-3">
                    <div
                        className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-white"
                        style={{ background: 'var(--color-brand-mark)' }}
                    >
                        D
                    </div>
                    {!collapsed && (
                        <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-theme-ink">DukanPOS</p>
                            <p className="truncate text-[11px] text-theme-ink-muted">Platform</p>
                        </div>
                    )}
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    {modules.map((mod) => (
                        <NavLink key={mod.id} mod={mod} current={current} />
                    ))}
                </nav>

                <div className="border-t border-theme-border p-2.5">
                    <div
                        className={`flex items-center gap-2 rounded-lg px-2.5 py-2 text-theme-ink-muted ${
                            collapsed ? 'justify-center' : ''
                        }`}
                    >
                        <LayoutDashboard className="h-4 w-4 shrink-0" strokeWidth={1.75} />
                        {!collapsed && (
                            <span className="truncate text-xs">Landlord control plane</span>
                        )}
                    </div>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="dp-header sticky top-0 z-20 flex h-14 items-center justify-between gap-4 px-4 sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setCollapsed((v) => !v)}
                            className="dp-icon-btn"
                            title="Toggle sidebar"
                        >
                            <PanelLeft className="h-[18px] w-[18px]" strokeWidth={1.75} />
                        </button>
                    </div>
                    <PlatformHeaderActions />
                </header>

                <main className="flex-1 px-4 py-5 sm:px-6">
                    {flash?.status && <div className="dp-flash mb-4 px-4 py-3 text-sm">{flash.status}</div>}
                    {flash?.error && (
                        <div className="mb-4 rounded-lg border border-theme-danger/30 bg-theme-danger/10 px-4 py-3 text-sm text-theme-danger">
                            {flash.error}
                        </div>
                    )}
                    <PageHeader title={title} description={description} actions={actions} />
                    {children}
                </main>
            </div>
        </div>
    );
}
