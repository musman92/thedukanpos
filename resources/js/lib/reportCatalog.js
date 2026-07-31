import {
    ArrowLeftRight,
    BarChart3,
    CalendarDays,
    ClipboardList,
    FileText,
    Gift,
    Percent,
    ShoppingBag,
    Trophy,
    Truck,
    UserRound,
    Wallet,
    Receipt,
    Landmark,
    Package,
    Tags,
} from 'lucide-react';

/**
 * Operational report catalog (retail-relevant FoodPOS-style set).
 * `route` is a Ziggy route name. `filters` declares which filter controls apply.
 */
export const REPORT_CATALOG = [
    {
        key: 'sales',
        label: 'Sales',
        route: 'admin.reports.sales',
        group: 'sales',
        icon: BarChart3,
        filters: ['dates'],
        exportRoute: 'admin.reports.sales.export',
    },
    {
        key: 'products',
        label: 'Sales by Item',
        route: 'admin.reports.products',
        group: 'sales',
        icon: Package,
        filters: ['dates', 'category'],
    },
    {
        key: 'top-items',
        label: 'Top Selling Items',
        route: 'admin.reports.top-items',
        group: 'sales',
        icon: Trophy,
        filters: ['dates', 'category'],
    },
    {
        key: 'daily-sales',
        label: 'Daily Sales',
        route: 'admin.reports.daily-sales',
        group: 'sales',
        icon: CalendarDays,
        filters: ['dates'],
    },
    {
        key: 'sales-by-category',
        label: 'Sales by Category',
        route: 'admin.reports.sales-by-category',
        group: 'sales',
        icon: Tags,
        filters: ['dates'],
    },
    {
        key: 'shifts-z',
        label: 'Z Report',
        route: 'admin.reports.shifts-z',
        group: 'closings',
        icon: FileText,
        filters: ['dates'],
    },
    {
        key: 'payment-methods',
        label: 'Payment Methods',
        route: 'admin.reports.payment-methods',
        group: 'sales',
        icon: Wallet,
        filters: ['dates'],
    },
    {
        key: 'money-source-txns',
        label: 'Transactions by Money Source',
        route: 'admin.reports.money-source-txns',
        group: 'sales',
        icon: ArrowLeftRight,
        filters: [],
    },
    {
        key: 'foc',
        label: 'FOC',
        route: 'admin.reports.foc',
        group: 'sales',
        icon: Gift,
        filters: ['dates'],
    },
    {
        key: 'order-history',
        label: 'Order History',
        route: 'admin.reports.order-history',
        group: 'sales',
        icon: ClipboardList,
        filters: [],
    },
    {
        key: 'discounts',
        label: 'Discounts',
        route: 'admin.reports.discounts',
        group: 'sales',
        icon: Percent,
        filters: ['dates'],
    },
    {
        key: 'tax-summary',
        label: 'Tax Summary',
        route: 'admin.reports.tax-summary',
        group: 'sales',
        icon: Receipt,
        filters: ['dates'],
    },
    {
        key: 'gross-margin',
        label: 'Gross Margin',
        route: 'admin.reports.gross-margin',
        group: 'financial',
        icon: Percent,
        filters: ['dates', 'category'],
    },
    {
        key: 'profit-loss',
        label: 'Profit & Loss',
        route: 'admin.reports.profit-loss',
        group: 'financial',
        icon: FileText,
        filters: ['dates'],
    },
    {
        key: 'expenses',
        label: 'Expenses',
        route: 'admin.reports.expenses',
        group: 'financial',
        icon: FileText,
        filters: ['dates'],
    },
    {
        key: 'account-statement',
        label: 'Account Statement',
        route: 'admin.reports.account-statement',
        group: 'financial',
        icon: Landmark,
        filters: [],
    },
    {
        key: 'weekly-closing',
        label: 'Weekly Closing',
        route: 'admin.reports.weekly-closing',
        group: 'closings',
        icon: CalendarDays,
        filters: ['dates'],
    },
    {
        key: 'monthly-closing',
        label: 'Monthly Closing',
        route: 'admin.reports.monthly-closing',
        group: 'closings',
        icon: CalendarDays,
        filters: ['dates'],
    },
    {
        key: 'receivables',
        label: 'Accounts Receivable',
        route: 'admin.reports.receivables',
        group: 'outstanding',
        icon: UserRound,
        filters: [],
    },
    {
        key: 'payables',
        label: 'Accounts Payable',
        route: 'admin.reports.payables',
        group: 'outstanding',
        icon: Truck,
        filters: [],
    },
    {
        key: 'customer-credits',
        label: 'Customer Credits',
        route: 'admin.reports.customer-credits',
        group: 'outstanding',
        icon: ShoppingBag,
        filters: ['dates'],
    },
    {
        key: 'supplier-payments',
        label: 'Supplier Payments',
        route: 'admin.reports.supplier-payments',
        group: 'outstanding',
        icon: Wallet,
        filters: ['dates'],
    },
    {
        key: 'purchases',
        label: 'Purchases',
        route: 'admin.reports.purchases',
        group: 'purchasing',
        icon: Truck,
        filters: ['dates'],
    },
];

export function findReport(keyOrRoute) {
    return (
        REPORT_CATALOG.find((r) => r.key === keyOrRoute || r.route === keyOrRoute) ||
        null
    );
}

export function activeReportKey() {
    try {
        const current = route().current();
        const match = REPORT_CATALOG.find((r) => r.route === current);
        return match?.key || null;
    } catch {
        return null;
    }
}
