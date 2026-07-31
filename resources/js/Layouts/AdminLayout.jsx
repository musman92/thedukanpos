import AdminHeaderActions from '@/Components/AdminHeaderActions';
import PageHeader from '@/Components/Ui/PageHeader';
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
    Minus,
    Plus,
    Receipt,
    Settings,
    UsersRound,
    Wallet,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

const modules = [
    {
        id: 'dashboard',
        type: 'link',
        label: 'Dashboard',
        icon: LayoutDashboard,
        href: 'admin.dashboard',
        match: ['admin.dashboard'],
    },
    {
        id: 'shifts',
        type: 'link',
        label: 'Shifts',
        icon: Clock3,
        href: 'admin.shifts.index',
        match: ['admin.shifts'],
    },
    {
        id: 'catalog',
        label: 'Catalog',
        icon: Package,
        match: ['admin.products', 'admin.brands', 'admin.categories', 'admin.units', 'admin.variations', 'admin.sections', 'admin.racks'],
        items: [
            { href: 'admin.brands.index', label: 'Brands' },
            { href: 'admin.categories.index', label: 'Categories' },
            { href: 'admin.units.index', label: 'Units' },
            { href: 'admin.variations.index', label: 'Variations' },
            { href: 'admin.sections.index', label: 'Sections' },
            { href: 'admin.racks.index', label: 'Racks' },
            { href: 'admin.products.index', label: 'Products' },
        ],
    },
    {
        id: 'people',
        label: 'People & Contacts',
        icon: ContactRound,
        match: ['admin.customers', 'admin.suppliers', 'admin.users'],
        items: [
            { href: 'admin.customers.index', label: 'Customers' },
            { href: 'admin.suppliers.index', label: 'Suppliers' },
            { href: 'admin.users.index', label: 'Users' },
        ],
    },
    {
        id: 'orders',
        label: 'Orders',
        icon: Receipt,
        match: ['admin.orders', 'admin.returns.sales', 'admin.quotations'],
        items: [
            { href: 'admin.orders.index', label: 'Orders' },
            { href: 'admin.returns.sales.index', label: 'Refund Orders' },
            { href: 'admin.quotations.index', label: 'Quotations' },
        ],
    },
    {
        id: 'inventory',
        label: 'Inventory',
        icon: Boxes,
        match: [
            'admin.inventory',
            'admin.purchases',
            'admin.returns.purchases',
            'admin.serials',
        ],
        items: [
            { href: 'admin.inventory.stock', label: 'Stock' },
            { href: 'admin.inventory.low-stock', label: 'Low stock' },
            { href: 'admin.inventory.product-ledger', label: 'Product ledger' },
            { href: 'admin.purchases.index', label: 'Purchases' },
            { href: 'admin.returns.purchases.index', label: 'Purchase returns' },
            { href: 'admin.inventory.adjustments', label: 'Adjustments' },
            { href: 'admin.inventory.damages', label: 'Damage' },
            { href: 'admin.inventory.transfers', label: 'Transfers' },
            { href: 'admin.serials.index', label: 'Serials / IMEI' },
        ],
    },
    {
        id: 'financials',
        label: 'Financials',
        icon: Wallet,
        match: ['admin.finance'],
        items: [
            { href: 'admin.finance.accounts.index', label: 'Accounts' },
            { href: 'admin.finance.taxes.index', label: 'Taxes' },
            { href: 'admin.finance.money-sources.index', label: 'Money sources' },
            { href: 'admin.finance.transactions.index', label: 'Transactions' },
            { href: 'admin.finance.expenses.index', label: 'Expenses' },
            { href: 'admin.finance.supplier-payments.index', label: 'Supplier payments' },
            { href: 'admin.finance.customer-payments.index', label: 'Customer payments' },
            { href: 'admin.finance.employee-payments.index', label: 'Employee payments' },
        ],
    },
    {
        id: 'hr',
        label: 'HR',
        icon: UsersRound,
        match: ['admin.hr'],
        items: [
            { href: 'admin.hr.attendance.index', label: 'Attendance' },
            { href: 'admin.hr.leaves.index', label: 'Leaves' },
            { href: 'admin.hr.payroll.index', label: 'Payroll' },
            { href: 'admin.hr.adjustments.index', label: 'Bonuses / deductions' },
        ],
    },
    {
        id: 'reports',
        type: 'link',
        label: 'Reports',
        icon: ChartColumn,
        href: 'admin.reports.hub',
        match: ['admin.reports'],
    },
    {
        id: 'subscription',
        type: 'link',
        label: 'Subscription',
        icon: CreditCard,
        href: 'admin.subscription.index',
        match: ['admin.subscription'],
    },
    {
        id: 'settings',
        label: 'Settings',
        icon: Settings,
        match: ['admin.settings', 'admin.branches', 'admin.roles', 'admin.activity'],
        items: [
            { href: 'admin.settings.edit', label: 'Company / POS / Receipt' },
            { href: 'admin.branches.index', label: 'Branches' },
            { href: 'admin.roles.index', label: 'Roles & permissions' },
            { href: 'admin.activity.index', label: 'Activity log' },
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

function NavGroup({ mod, current, open, onToggle }) {
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
                <span className="min-w-0 flex-1 truncate text-left">{mod.label}</span>
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
                                    {item.label}
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
    const current = route().current();
    const [collapsed, setCollapsed] = useState(false);

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

    const toggle = (id) => {
        setOpenIds((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    };

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
                            <p className="truncate text-[11px] text-theme-ink-muted">Back office</p>
                        </div>
                    )}
                </div>

                <nav className="flex-1 space-y-0.5 overflow-y-auto px-2 py-3">
                    {modules.map((mod) =>
                        mod.type === 'link' ? (
                            <NavLink key={mod.id} mod={mod} current={current} />
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
                            />
                        ),
                    )}
                </nav>
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
                    <AdminHeaderActions />
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
