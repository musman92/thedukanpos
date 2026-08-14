import AdminHeaderActions from '@/Components/AdminHeaderActions';
import PageHeader from '@/Components/Ui/PageHeader';
import { useI18n } from '@/hooks/useI18n';
import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    ChartColumn,
    Clock3,
    ContactRound,
    CreditCard,
    LayoutDashboard,
    Package,
    PanelLeft,
    X,
    Minus,
    Plus,
    Receipt,
    Settings,
    Store,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const modules = [
    {
        id: 'dashboard',
        type: 'link',
        labelKey: 'nav.dashboard',
        icon: LayoutDashboard,
        href: 'admin.dashboard',
        match: ['admin.dashboard'],
    },
    {
        id: 'shifts',
        type: 'link',
        labelKey: 'nav.shifts',
        icon: Clock3,
        href: 'admin.shifts.index',
        match: ['admin.shifts'],
    },
    {
        id: 'catalog',
        labelKey: 'nav.catalog',
        icon: Package,
        match: ['admin.products', 'admin.brands', 'admin.categories', 'admin.units', 'admin.variations', 'admin.sections', 'admin.racks'],
        items: [
            { href: 'admin.brands.index', labelKey: 'nav.brands' },
            { href: 'admin.categories.index', labelKey: 'nav.categories' },
            { href: 'admin.units.index', labelKey: 'nav.units' },
            { href: 'admin.variations.index', labelKey: 'nav.variations' },
            { href: 'admin.sections.index', labelKey: 'nav.sections' },
            { href: 'admin.racks.index', labelKey: 'nav.racks' },
            { href: 'admin.products.index', labelKey: 'nav.products' },
        ],
    },
    {
        id: 'people',
        labelKey: 'nav.people',
        icon: ContactRound,
        match: ['admin.customers', 'admin.suppliers', 'admin.users'],
        items: [
            { href: 'admin.customers.index', labelKey: 'nav.customers' },
            { href: 'admin.suppliers.index', labelKey: 'nav.suppliers' },
            { href: 'admin.users.index', labelKey: 'nav.users' },
        ],
    },
    {
        id: 'orders',
        labelKey: 'nav.orders',
        icon: Receipt,
        match: ['admin.orders', 'admin.returns.sales', 'admin.quotations'],
        items: [
            { href: 'admin.orders.index', labelKey: 'nav.orders' },
            { href: 'admin.returns.sales.index', labelKey: 'nav.refund_orders' },
            { href: 'admin.quotations.index', labelKey: 'nav.quotations' },
        ],
    },
    {
        id: 'inventory',
        labelKey: 'nav.inventory',
        icon: Boxes,
        match: [
            'admin.inventory',
            'admin.purchases',
            'admin.returns.purchases',
            'admin.serials',
        ],
        items: [
            { href: 'admin.inventory.stock', labelKey: 'nav.stock' },
            { href: 'admin.inventory.low-stock', labelKey: 'nav.low_stock' },
            { href: 'admin.inventory.product-ledger', labelKey: 'nav.product_ledger' },
            { href: 'admin.purchases.index', labelKey: 'nav.purchases' },
            { href: 'admin.returns.purchases.index', labelKey: 'nav.purchase_returns' },
            { href: 'admin.inventory.adjustments', labelKey: 'nav.adjustments' },
            { href: 'admin.inventory.damages', labelKey: 'nav.damage' },
            { href: 'admin.inventory.transfers', labelKey: 'nav.transfers' },
            { href: 'admin.serials.index', labelKey: 'nav.serials' },
        ],
    },
    {
        id: 'financials',
        labelKey: 'nav.financials',
        icon: Wallet,
        match: ['admin.finance'],
        items: [
            { href: 'admin.finance.accounts.index', labelKey: 'nav.accounts' },
            { href: 'admin.finance.taxes.index', labelKey: 'nav.taxes' },
            { href: 'admin.finance.money-sources.index', labelKey: 'nav.money_sources' },
            { href: 'admin.finance.transactions.index', labelKey: 'nav.transactions' },
            { href: 'admin.finance.expenses.index', labelKey: 'nav.expenses' },
            { href: 'admin.finance.supplier-payments.index', labelKey: 'nav.supplier_payments' },
            { href: 'admin.finance.customer-payments.index', labelKey: 'nav.customer_payments' },
            { href: 'admin.finance.employee-payments.index', labelKey: 'nav.employee_payments' },
        ],
    },
    {
        id: 'hr',
        labelKey: 'nav.hr',
        icon: UsersRound,
        match: ['admin.hr'],
        items: [
            { href: 'admin.hr.attendance.index', labelKey: 'nav.attendance' },
            { href: 'admin.hr.leaves.index', labelKey: 'nav.leaves' },
            { href: 'admin.hr.payroll.index', labelKey: 'nav.payroll' },
            { href: 'admin.hr.adjustments.index', labelKey: 'nav.bonuses_deductions' },
        ],
    },
    {
        id: 'reports',
        type: 'link',
        labelKey: 'nav.reports',
        icon: ChartColumn,
        href: 'admin.reports.hub',
        match: ['admin.reports'],
    },
    {
        id: 'subscription',
        type: 'link',
        labelKey: 'nav.subscription',
        icon: CreditCard,
        href: 'admin.subscription.index',
        match: ['admin.subscription'],
    },
    {
        id: 'settings',
        labelKey: 'nav.settings',
        icon: Settings,
        match: ['admin.settings', 'admin.branches', 'admin.roles', 'admin.activity'],
        items: [
            { href: 'admin.settings.edit', labelKey: 'nav.company_pos_receipt' },
            { href: 'admin.branches.index', labelKey: 'nav.branches' },
            { href: 'admin.roles.index', labelKey: 'nav.roles' },
            { href: 'admin.activity.index', labelKey: 'nav.activity' },
        ],
    },
];

function isItemActive(current, href) {
    if (!current) return false;
    if (current === href) return true;
    const base = href.replace(/\.index$/, '').replace(/\.edit$/, '');
    return current === base || current.startsWith(`${base}.`);
}

function moduleMatches(current, mod) {
    if (!current) return false;
    return mod.match.some(
        (prefix) => current === prefix || current.startsWith(`${prefix}.`) || current.startsWith(prefix),
    );
}

function NavLink({ mod, current, t }) {
    const Icon = mod.icon;
    const active = moduleMatches(current, mod);

    return (
        <Link href={route(mod.href)} className={`dp-nav-item ${active ? 'dp-nav-item-active' : ''}`}>
            <Icon className="h-4 w-4 shrink-0 opacity-80" strokeWidth={1.75} />
            <span className="min-w-0 flex-1 truncate">{t(mod.labelKey)}</span>
        </Link>
    );
}

function NavGroup({ mod, current, open, onToggle, t }) {
    const Icon = mod.icon;
    const groupActive = moduleMatches(current, mod);

    return (
        <div>
            <button
                type="button"
                onClick={onToggle}
                className={`dp-nav-item ${groupActive ? 'dp-nav-item-active' : ''}`}
            >
                <Icon className="h-4 w-4 shrink-0 opacity-80" strokeWidth={1.75} />
                <span className="min-w-0 flex-1 truncate text-start">{t(mod.labelKey)}</span>
                {open ? (
                    <Minus className="h-3.5 w-3.5 shrink-0 opacity-70" strokeWidth={2} />
                ) : (
                    <Plus className="h-3.5 w-3.5 shrink-0 opacity-70" strokeWidth={2} />
                )}
            </button>

            {open && (
                <ul className="mt-0.5 space-y-0.5 pb-1">
                    {mod.items.map((item) => {
                        const active = isItemActive(current, item.href);
                        return (
                            <li key={item.href}>
                                <Link
                                    href={route(item.href)}
                                    className={`dp-nav-sub ${active ? 'dp-nav-sub-active' : ''}`}
                                >
                                    {t(item.labelKey)}
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            )}
        </div>
    );
}

export default function AdminLayout({ title, description = null, children, actions = null }) {
    const { flash } = usePage().props;
    const { t } = useI18n();
    const current = route().current();
    const [collapsed, setCollapsed] = useState(false);
    const [mobileOpen, setMobileOpen] = useState(false);

    const activeId = useMemo(() => {
        const found = modules.find((mod) => mod.type !== 'link' && moduleMatches(current, mod));
        return found?.id || null;
    }, [current]);

    const [openIds, setOpenIds] = useState(() => new Set(activeId ? [activeId] : []));

    useEffect(() => {
        if (!activeId) return;
        setOpenIds((prev) => {
            if (prev.has(activeId)) return prev;
            const next = new Set(prev);
            next.add(activeId);
            return next;
        });
    }, [activeId]);

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

    const toggle = (id) => {
        setOpenIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

    return (
        <div className="dp-app flex min-h-[100dvh]">
            {mobileOpen && (
                <button
                    type="button"
                    aria-label={t('header.toggle_sidebar')}
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
                            <p className="truncate text-[11px] text-theme-ink-muted">
                                {t('nav.back_office')}
                            </p>
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

                <nav className="flex-1 space-y-0.5 overflow-y-auto overscroll-contain px-2 py-3 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                    {modules.map((mod) =>
                        mod.type === 'link' ? (
                            <NavLink key={mod.id} mod={mod} current={current} t={t} />
                        ) : (
                            <NavGroup
                                key={mod.id}
                                mod={mod}
                                current={current}
                                open={!collapsed && openIds.has(mod.id)}
                                onToggle={() => {
                                    if (collapsed) setCollapsed(false);
                                    toggle(mod.id);
                                }}
                                t={t}
                            />
                        ),
                    )}
                </nav>
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
                            title={t('header.toggle_sidebar')}
                        >
                            <PanelLeft className="h-[18px] w-[18px]" strokeWidth={1.75} />
                        </button>
                        <p className="truncate text-sm font-semibold text-theme-ink lg:hidden">
                            {title || 'DukanPOS'}
                        </p>
                    </div>
                    <AdminHeaderActions />
                </header>

                <main className="dp-mobile-content flex-1 overflow-x-hidden px-3 py-4 pb-[calc(5.75rem+env(safe-area-inset-bottom))] sm:px-6 sm:py-5 lg:pb-5">
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

            <nav
                className="dp-mobile-dock fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-theme-border bg-theme-surface/95 px-2 pt-1.5 backdrop-blur-xl lg:hidden"
                aria-label="Primary navigation"
            >
                <Link
                    href={route('admin.dashboard')}
                    className={isItemActive(current, 'admin.dashboard') ? 'active' : ''}
                >
                    <LayoutDashboard />
                    <span>{t('nav.dashboard')}</span>
                </Link>
                <a href="/pos">
                    <Store />
                    <span>POS</span>
                </a>
                <Link
                    href={route('admin.orders.index')}
                    className={moduleMatches(current, modules.find((mod) => mod.id === 'orders')) ? 'active' : ''}
                >
                    <Receipt />
                    <span>{t('nav.orders')}</span>
                </Link>
                <button type="button" onClick={() => setMobileOpen(true)}>
                    <PanelLeft />
                    <span>Menu</span>
                </button>
            </nav>
        </div>
    );
}
