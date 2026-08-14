import PageHeader from '@/Components/Ui/PageHeader';
import ResponsiveTableScope from '@/Components/Ui/ResponsiveTableScope';
import ThemeToggle from '@/Components/ThemeToggle';
import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    FileText,
    LayoutDashboard,
    LogOut,
    PanelLeft,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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
    const [mobileOpen, setMobileOpen] = useState(false);

    useEffect(() => {
        setMobileOpen(false);
    }, [current]);

    useEffect(() => {
        if (!mobileOpen) return undefined;
        document.body.style.overflow = 'hidden';
        const onKey = (event) => {
            if (event.key === 'Escape') setMobileOpen(false);
        };
        document.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = '';
            document.removeEventListener('keydown', onKey);
        };
    }, [mobileOpen]);

    return (
        <div className="dp-app flex min-h-[100dvh]">
            {mobileOpen && (
                <button
                    type="button"
                    aria-label="Close navigation"
                    className="fixed inset-0 z-40 bg-black/45 backdrop-blur-[1px] lg:hidden"
                    onClick={() => setMobileOpen(false)}
                />
            )}
            <aside
                className={`dp-sidebar fixed inset-y-0 start-0 z-50 flex h-[100dvh] w-[min(86vw,20rem)] shrink-0 flex-col shadow-2xl transition-transform duration-200 lg:sticky lg:top-0 lg:z-auto lg:h-screen lg:shadow-none lg:transition-[width] ${
                    mobileOpen ? 'translate-x-0' : '-translate-x-full rtl:translate-x-full'
                } ${
                    collapsed ? 'w-[4.25rem]' : 'w-60'
                } lg:translate-x-0 rtl:lg:translate-x-0`}
            >
                <div className="flex min-h-14 items-center gap-2.5 border-b border-theme-border px-3 pt-[env(safe-area-inset-top)]">
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
                    <button
                        type="button"
                        onClick={() => setMobileOpen(false)}
                        className="dp-icon-btn ms-auto min-h-11 min-w-11 lg:hidden"
                        aria-label="Close navigation"
                    >
                        <X className="h-5 w-5" />
                    </button>
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-2 py-3">
                    {modules.map((mod) => (
                        <NavLink key={mod.id} mod={mod} current={current} />
                    ))}
                </nav>

                <div className="border-t border-theme-border p-2.5 pb-[calc(.625rem+env(safe-area-inset-bottom))]">
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
                <header className="dp-header sticky top-0 z-30 flex min-h-14 items-center justify-between gap-2 px-3 pt-[env(safe-area-inset-top)] sm:px-6">
                    <div className="flex min-w-0 items-center gap-3">
                        <button
                            type="button"
                            onClick={() => {
                                if (window.matchMedia('(min-width: 1024px)').matches) {
                                    setCollapsed((v) => !v);
                                } else {
                                    setMobileOpen(true);
                                }
                            }}
                            className="dp-icon-btn min-h-11 min-w-11"
                            title="Toggle sidebar"
                        >
                            <PanelLeft className="h-[18px] w-[18px]" strokeWidth={1.75} />
                        </button>
                        <p className="truncate text-sm font-semibold text-theme-ink lg:hidden">
                            {title || 'Platform'}
                        </p>
                    </div>
                    <PlatformHeaderActions />
                </header>

                <main className="dp-mobile-content flex-1 overflow-x-hidden px-3 py-4 pb-[calc(5.75rem+env(safe-area-inset-bottom))] sm:px-6 sm:py-5 lg:pb-5">
                    {flash?.status && <div className="dp-flash mb-4 px-4 py-3 text-sm">{flash.status}</div>}
                    {flash?.error && (
                        <div className="mb-4 rounded-lg border border-theme-danger/30 bg-theme-danger/10 px-4 py-3 text-sm text-theme-danger">
                            {flash.error}
                        </div>
                    )}
                    <PageHeader title={title} description={description} actions={actions} />
                    <ResponsiveTableScope>{children}</ResponsiveTableScope>
                </main>
            </div>

            <nav
                className="dp-mobile-dock fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-theme-border bg-theme-surface/95 px-2 pt-1.5 backdrop-blur-xl lg:hidden"
                aria-label="Primary navigation"
            >
                {modules.map((mod) => {
                    const Icon = mod.icon;
                    return (
                        <Link
                            key={mod.id}
                            href={route(mod.href)}
                            className={moduleMatches(current, mod) ? 'active' : ''}
                        >
                            <Icon />
                            <span>{mod.label}</span>
                        </Link>
                    );
                })}
                <button type="button" onClick={() => setMobileOpen(true)}>
                    <PanelLeft />
                    <span>Menu</span>
                </button>
            </nav>
        </div>
    );
}
