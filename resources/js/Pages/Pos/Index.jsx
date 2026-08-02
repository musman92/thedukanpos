import DeliveryOrdersDrawer from '@/Components/Pos/DeliveryOrdersDrawer';
import PaymentModal from '@/Components/Pos/PaymentModal';
import ParkedBillsDrawer from '@/Components/Pos/ParkedBillsDrawer';
import QuickCustomerModal from '@/Components/Pos/QuickCustomerModal';
import TodayHistoryDrawer from '@/Components/Pos/TodayHistoryDrawer';
import SearchableSelect from '@/Components/Ui/SearchableSelect';
import { formatMoney } from '@/lib/money';
import { cartTotals, pickExactProduct } from '@/lib/posCart';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import {
    ArrowLeftRight,
    Barcode,
    Bookmark,
    Clock3,
    History,
    LayoutDashboard,
    LayoutGrid,
    Minus,
    Package,
    Plus,
    Receipt,
    Search,
    Trash2,
    Truck,
    UserPlus,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

function goAdmin(path = '/admin') {
    window.location.assign(path);
}

const PRODUCT_PLACEHOLDER = '/images/product-placeholder.svg';

function priceLabel(product, moneyCfg) {
    const min = Number(product.price_min ?? product.sale_price ?? 0);
    const max = Number(product.price_max ?? product.sale_price ?? 0);
    if (product.variant_count > 1 && max > min) {
        return `from ${formatMoney(min, moneyCfg)}`;
    }
    return formatMoney(min, moneyCfg);
}

function ProductCard({ product, index = 0, showStock, showImage, moneyCfg, onSelect }) {
    const optionCount = Number(product.variant_count || 0);

    return (
        <button
            type="button"
            onClick={() => onSelect(product)}
            style={{ animationDelay: `${Math.min(index, 12) * 20}ms` }}
            className="pos-pop group flex flex-col rounded-lg border border-theme-border bg-theme-bg p-2 text-left transition hover:-translate-y-0.5 hover:border-theme-primary/45 hover:bg-[var(--color-primary-soft)] hover:shadow-sm"
        >
            {showImage && (
                <div className="mb-1.5 aspect-square w-full overflow-hidden rounded-md bg-theme-surface ring-1 ring-theme-border">
                    <img
                        src={product.image_url || PRODUCT_PLACEHOLDER}
                        alt=""
                        loading="lazy"
                        className="h-full w-full object-cover transition group-hover:scale-[1.03]"
                        onError={(e) => {
                            if (e.currentTarget.src.endsWith(PRODUCT_PLACEHOLDER)) return;
                            e.currentTarget.src = PRODUCT_PLACEHOLDER;
                        }}
                    />
                </div>
            )}
            <p className="line-clamp-2 min-h-[2rem] text-xs font-semibold leading-snug text-theme-ink">
                {product.name}
            </p>
            {optionCount > 1 && (
                <p className="mt-0.5 text-[10px] font-medium text-theme-ink-muted">
                    {optionCount} options
                </p>
            )}
            <div className="mt-auto flex items-end justify-between gap-1 pt-1.5">
                <p className="text-xs font-bold tabular-nums text-theme-primary">
                    {priceLabel(product, moneyCfg)}
                </p>
                {showStock && (
                    <span className="rounded bg-theme-surface px-1 py-0.5 text-[10px] font-medium tabular-nums text-theme-ink-muted ring-1 ring-theme-border">
                        {product.stock}
                    </span>
                )}
            </div>
        </button>
    );
}

function VariantPicker({ product, showStock, moneyCfg, onPick, onClose }) {
    if (!product) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-3 sm:items-center">
            <button
                type="button"
                className="absolute inset-0 cursor-default"
                aria-label="Close"
                onClick={onClose}
            />
            <div className="relative z-10 w-full max-w-md overflow-hidden rounded-2xl border border-theme-border bg-theme-surface shadow-xl">
                <div className="flex items-start justify-between gap-3 border-b border-theme-border px-4 py-3">
                    <div>
                        <p className="text-sm font-semibold text-theme-ink">{product.name}</p>
                        <p className="text-xs text-theme-ink-muted">Choose a variant</p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg px-2 py-1 text-sm text-theme-ink-muted hover:bg-theme-bg hover:text-theme-ink"
                    >
                        Close
                    </button>
                </div>
                <div className="max-h-[60vh] space-y-1.5 overflow-auto p-3">
                    {(product.variants || []).map((variant) => (
                        <button
                            key={variant.variant_id}
                            type="button"
                            onClick={() => onPick(variant)}
                            className="flex w-full items-center justify-between gap-3 rounded-xl border border-theme-border bg-theme-bg px-3 py-2.5 text-left transition hover:border-theme-primary/45 hover:bg-[var(--color-primary-soft)]"
                        >
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold text-theme-ink">
                                    {variant.variant_name || variant.name}
                                </p>
                                {variant.sale_unit?.code && (
                                    <p className="text-[11px] text-theme-ink-muted">
                                        {variant.sale_unit.code}
                                    </p>
                                )}
                            </div>
                            <div className="shrink-0 text-right">
                                <p className="text-sm font-bold tabular-nums text-theme-primary">
                                    {formatMoney(variant.sale_price, moneyCfg)}
                                </p>
                                {showStock && (
                                    <p className="text-[10px] tabular-nums text-theme-ink-muted">
                                        Stock {variant.stock}
                                    </p>
                                )}
                            </div>
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );
}

function resolveDefaultCustomerId(customers = [], fallbackId = null) {
    const walkIn = customers.find((c) => c.is_walk_in);
    if (walkIn?.id != null) {
        return String(walkIn.id);
    }
    if (fallbackId != null && fallbackId !== '') {
        return String(fallbackId);
    }
    return '';
}

export default function Index({
    tenant,
    branch,
    shift,
    moneySources = [],
    customers = [],
    categories = [],
    parked_bills: initialParked = [],
    pos_settings: posSettings = {},
    default_customer_id: defaultCustomerId = null,
    riders = [],
}) {
    const allowCredit = posSettings.allow_credit !== false;
    const enableDelivery = !!posSettings.enable_delivery;
    const showStock = posSettings.show_stock !== false;
    const showProductImage = posSettings.show_product_image !== false;
    const catalogMode = posSettings.catalog_mode === 'grouped' ? 'grouped' : 'flat';
    const moneyCfg = {
        currency_symbol: posSettings.currency_symbol,
        currency_position: posSettings.currency_position,
        decimal_points: posSettings.decimal_points,
    };
    const [customerList, setCustomerList] = useState(customers);
    const walkInCustomerId = useMemo(
        () => resolveDefaultCustomerId(customerList, defaultCustomerId),
        [customerList, defaultCustomerId],
    );

    const [q, setQ] = useState('');
    const [results, setResults] = useState([]);
    const [searching, setSearching] = useState(false);
    const [categoryId, setCategoryId] = useState('all');
    const [catalog, setCatalog] = useState([]);
    const [catalogLoading, setCatalogLoading] = useState(false);
    const [variantPicker, setVariantPicker] = useState(null);
    const [cart, setCart] = useState([]);
    const [discountValue, setDiscountValue] = useState('');
    const [discountMode, setDiscountMode] = useState('fixed'); // fixed | percent
    const [customerId, setCustomerId] = useState(() =>
        resolveDefaultCustomerId(customers, defaultCustomerId),
    );
    const [isDelivery, setIsDelivery] = useState(false);
    const [deliveryCharge, setDeliveryCharge] = useState('');
    const [deliveryAddress, setDeliveryAddress] = useState('');
    const [riderId, setRiderId] = useState('');
    const [quickCustomerOpen, setQuickCustomerOpen] = useState(false);
    const [parkedSaleId, setParkedSaleId] = useState(null);
    const [parkedBills, setParkedBills] = useState(initialParked);
    const [parkedOpen, setParkedOpen] = useState(false);
    const [todayOpen, setTodayOpen] = useState(false);
    const [todaySales, setTodaySales] = useState([]);
    const [todayLoading, setTodayLoading] = useState(false);
    const [deliveryOpen, setDeliveryOpen] = useState(false);
    const [payOpen, setPayOpen] = useState(false);
    const [message, setMessage] = useState('');
    const [busy, setBusy] = useState(false);
    const [discardBusyId, setDiscardBusyId] = useState(null);
    const searchRef = useRef(null);
    const searchSeq = useRef(0);
    const catalogSeq = useRef(0);

    const isSearching = q.trim().length > 0;
    const products = isSearching ? results : catalog;
    const productsLoading = isSearching ? searching : catalogLoading;

    const deliveryChargeAmount = isDelivery ? Number(deliveryCharge || 0) : 0;
    const totals = useMemo(
        () => cartTotals(cart, discountValue, discountMode, deliveryChargeAmount),
        [cart, discountValue, discountMode, deliveryChargeAmount],
    );

    const selectedCustomer = useMemo(
        () => customerList.find((c) => String(c.id) === String(customerId)) || null,
        [customerList, customerId],
    );

    const customerOptions = useMemo(
        () =>
            customerList.map((c) => ({
                value: String(c.id),
                label: c.is_walk_in ? 'Walk-in' : c.name,
                meta: c.is_walk_in
                    ? 'Default counter customer'
                    : `${c.phone || ''} · ${formatMoney(c.balance, moneyCfg)}`,
            })),
        [customerList, moneyCfg],
    );

    const riderOptions = useMemo(
        () => [
            { value: '', label: 'No rider yet' },
            ...riders.map((r) => ({ value: String(r.id), label: r.name })),
        ],
        [riders],
    );

    useEffect(() => {
        setCustomerList(customers);
    }, [customers]);

    useEffect(() => {
        searchRef.current?.focus();
    }, []);

    const loadCatalog = useCallback(async (selected) => {
        const seq = ++catalogSeq.current;
        setCatalogLoading(true);
        try {
            const params = {};
            if (selected === 'uncategorized') {
                params.uncategorized = 1;
            } else if (selected !== 'all') {
                params.category_id = selected;
            }
            const { data } = await axios.get(route('pos.catalog'), { params });
            if (seq !== catalogSeq.current) return;
            setCatalog(data.data || []);
        } catch {
            if (seq !== catalogSeq.current) return;
            setCatalog([]);
        } finally {
            if (seq === catalogSeq.current) setCatalogLoading(false);
        }
    }, []);

    useEffect(() => {
        if (isSearching) return;
        loadCatalog(categoryId);
    }, [categoryId, isSearching, loadCatalog]);

    const runSearch = async (value) => {
        setQ(value);
        if (!value.trim()) {
            setResults([]);
            setSearching(false);
            return;
        }

        const seq = ++searchSeq.current;
        setSearching(true);
        try {
            const { data } = await axios.get(route('pos.search'), {
                params: { q: value },
            });
            if (seq !== searchSeq.current) return;
            setResults(data.data || []);
        } catch {
            if (seq !== searchSeq.current) return;
            setResults([]);
        } finally {
            if (seq === searchSeq.current) setSearching(false);
        }
    };

    const addToCart = (product) => {
        if (!product?.sale_unit?.id) return;

        setCart((prev) => {
            const existing = prev.find(
                (l) =>
                    l.variant_id === product.variant_id &&
                    l.unit_id === product.sale_unit.id,
            );
            if (existing) {
                return prev.map((l) =>
                    l === existing ? { ...l, quantity: Number(l.quantity) + 1 } : l,
                );
            }

            return [
                ...prev,
                {
                    variant_id: product.variant_id,
                    product_id: product.product_id,
                    name: product.name,
                    unit_id: product.sale_unit.id,
                    unit_code: product.sale_unit.code,
                    quantity: 1,
                    unit_price: Number(product.sale_price),
                    discount: 0,
                    tax: product.tax,
                    location: product.location,
                    stock: product.stock,
                },
            ];
        });
        setVariantPicker(null);
        setQ('');
        setResults([]);
        setMessage('');
        searchRef.current?.focus();
    };

    const selectCatalogItem = (item) => {
        // Search always returns flat variants; grouped catalog may return products.
        if (item?.kind === 'product' || (item?.variants && item.variant_count > 1)) {
            if (Number(item.variant_count) === 1 && item.variants?.[0]) {
                addToCart(item.variants[0]);
                return;
            }
            if (Number(item.variant_count) > 1) {
                setVariantPicker(item);
                return;
            }
        }
        addToCart(item);
    };

    const onSearchKeyDown = async (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();

        const value = q.trim();
        if (!value) return;

        let list = results;
        if (!list.length) {
            try {
                const { data } = await axios.get(route('pos.search'), {
                    params: { q: value },
                });
                list = data.data || [];
                setResults(list);
            } catch {
                return;
            }
        }

        const exact = pickExactProduct(list, value);
        if (exact) {
            addToCart(exact);
        }
    };

    const setQty = (idx, quantity) => {
        const qty = Number(quantity);
        if (!Number.isFinite(qty) || qty <= 0) {
            setCart((prev) => prev.filter((_, i) => i !== idx));
            return;
        }
        setCart((prev) => prev.map((l, i) => (i === idx ? { ...l, quantity: qty } : l)));
    };

    const bumpQty = (idx, delta) => {
        setCart((prev) => {
            const line = prev[idx];
            if (!line) return prev;
            const next = Number(line.quantity) + delta;
            if (next <= 0) return prev.filter((_, i) => i !== idx);
            return prev.map((l, i) => (i === idx ? { ...l, quantity: next } : l));
        });
    };

    const clearCart = () => {
        setCart([]);
        setDiscountValue('');
        setDiscountMode('fixed');
        setCustomerId(walkInCustomerId);
        setIsDelivery(false);
        setDeliveryCharge('');
        setDeliveryAddress('');
        setRiderId('');
        setParkedSaleId(null);
        setMessage('');
        searchRef.current?.focus();
    };

    const selectCustomer = (id) => {
        const nextId = id === '' || id == null ? walkInCustomerId : String(id);
        setCustomerId(nextId);
        const customer = customerList.find((c) => String(c.id) === String(nextId));
        if (isDelivery) {
            if (!customer || customer.is_walk_in) {
                setDeliveryAddress('');
            } else {
                setDeliveryAddress(customer.address || '');
            }
        }
    };

    const toggleDelivery = (on) => {
        setIsDelivery(on);
        if (!on) {
            setDeliveryCharge('');
            setDeliveryAddress('');
            setRiderId('');
            setMessage('');
            return;
        }

        const customer = customerList.find((c) => String(c.id) === String(customerId));
        // Only force quick-create when Walk-in / no customer is selected.
        if (!customer || customer.is_walk_in) {
            setMessage('Delivery needs a named customer — create one or pick from the list.');
            setQuickCustomerOpen(true);
            setDeliveryAddress('');
            return;
        }

        setDeliveryAddress(customer.address || '');
        if (!String(customer.address || '').trim()) {
            setMessage('Enter a delivery address for this customer.');
        } else {
            setMessage('');
        }
    };

    const onQuickCustomerCreated = (customer) => {
        setCustomerList((prev) => {
            const without = prev.filter((c) => String(c.id) !== String(customer.id));
            return [customer, ...without];
        });
        setCustomerId(String(customer.id));
        setIsDelivery(true);
        setDeliveryAddress(customer.address || '');
        setMessage('');
    };

    const cartPayload = () => ({
        customer_id: customerId || null,
        discount_total: Number(totals.discount || 0),
        is_delivery: isDelivery,
        delivery_charge: isDelivery ? Number(deliveryCharge || 0) : 0,
        delivery_address: isDelivery ? deliveryAddress.trim() : null,
        rider_id: isDelivery && riderId ? Number(riderId) : null,
        items: cart.map((l) => ({
            variant_id: l.variant_id,
            unit_id: l.unit_id,
            quantity: l.quantity,
            unit_price: l.unit_price,
            discount: l.discount,
        })),
    });

    const saveForLater = async () => {
        if (!shift) {
            setMessage('Open a shift before saving a bill.');
            return;
        }
        if (!cart.length) return;

        setBusy(true);
        setMessage('');
        try {
            const payload = cartPayload();
            const { data } = parkedSaleId
                ? await axios.put(route('pos.parked.update', parkedSaleId), payload)
                : await axios.post(route('pos.park'), payload);

            const saved = data.sale;
            setParkedBills((prev) => {
                const without = prev.filter((b) => b.id !== saved.id);
                return [saved, ...without];
            });
            clearCart();
            setMessage(data.message || 'Bill saved for later.');
        } catch (err) {
            setMessage(err.response?.data?.message || 'Could not save bill.');
        } finally {
            setBusy(false);
        }
    };

    const resumeParked = (bill) => {
        setCart(
            (bill.items || []).map((item) => ({
                variant_id: item.variant_id,
                product_id: item.product_id,
                name: item.name,
                unit_id: item.unit_id,
                unit_code: item.unit_code,
                quantity: Number(item.quantity),
                unit_price: Number(item.unit_price),
                discount: Number(item.discount || 0),
                tax: item.tax,
                location: item.location,
                stock: item.stock,
            })),
        );
        setDiscountValue(
            Number(bill.discount_total || 0) > 0 ? String(bill.discount_total) : '',
        );
        setDiscountMode('fixed');
        setCustomerId(
            bill.customer_id ? String(bill.customer_id) : walkInCustomerId,
        );
        setIsDelivery(!!bill.is_delivery);
        setDeliveryCharge(
            Number(bill.delivery_charge || 0) > 0 ? String(bill.delivery_charge) : '',
        );
        setDeliveryAddress(bill.delivery_address || '');
        setRiderId(bill.rider_id ? String(bill.rider_id) : '');
        setParkedSaleId(bill.id);
        setParkedOpen(false);
        setMessage(`Resumed ${bill.number}`);
        searchRef.current?.focus();
    };

    const loadTodaySales = useCallback(async () => {
        setTodayLoading(true);
        try {
            const { data } = await axios.get(route('pos.today'));
            setTodaySales(data.data || []);
        } catch {
            setTodaySales([]);
        } finally {
            setTodayLoading(false);
        }
    }, []);

    const openTodayHistory = () => {
        setTodayOpen(true);
        loadTodaySales();
    };

    const discardParked = async (bill) => {
        setDiscardBusyId(bill.id);
        try {
            await axios.delete(route('pos.parked.discard', bill.id));
            setParkedBills((prev) => prev.filter((b) => b.id !== bill.id));
            if (parkedSaleId === bill.id) {
                clearCart();
            }
        } catch (err) {
            setMessage(err.response?.data?.message || 'Could not discard bill.');
        } finally {
            setDiscardBusyId(null);
        }
    };

    const openPay = () => {
        if (!shift) {
            setMessage('Open a shift in Admin → Shifts before selling.');
            return;
        }
        if (!cart.length) return;
        if (isDelivery) {
            if (!selectedCustomer || selectedCustomer.is_walk_in) {
                setMessage('Delivery needs a named customer with an address.');
                setQuickCustomerOpen(true);
                return;
            }
            if (!deliveryAddress.trim()) {
                setMessage('Enter the delivery address.');
                return;
            }
        }
        setMessage('');
        setPayOpen(true);
    };

    const checkout = async ({ customer_id, payments, foc = false }) => {
        const resolvedCustomerId = customer_id || walkInCustomerId || null;
        const isWalkIn =
            !resolvedCustomerId ||
            String(resolvedCustomerId) === String(walkInCustomerId) ||
            !!customerList.find(
                (c) => String(c.id) === String(resolvedCustomerId) && c.is_walk_in,
            );
        const creditCustomerId = isWalkIn ? null : resolvedCustomerId;

        if (foc) {
            // FOC: wipe payable with cart-level discount; no payments required.
        } else if (
            (payments.length === 0 || !payments.some((p) => p.amount > 0)) &&
            !creditCustomerId
        ) {
            setMessage('Add a payment or select a customer for credit.');
            return;
        }

        const paidSum = foc
            ? 0
            : payments.reduce((s, p) => s + Number(p.amount || 0), 0);
        const payable = Number(totals.total || 0);

        if (!foc && paidSum + 0.01 < payable && !creditCustomerId) {
            setMessage('Select a customer for the unpaid balance.');
            return;
        }
        if (!foc && paidSum + 0.01 < payable && !allowCredit) {
            setMessage('Credit sales are disabled in settings.');
            return;
        }

        setBusy(true);
        setMessage('');
        try {
            const payload = cartPayload();
            if (foc) {
                // Discount out the full pre-discount payable (subtotal + tax). Delivery not used on FOC.
                payload.discount_total = Number(
                    (Number(totals.subtotal || 0) + Number(totals.tax || 0)).toFixed(4),
                );
                payload.is_delivery = false;
                payload.delivery_charge = 0;
                payload.delivery_address = null;
                payload.rider_id = null;
            }

            const { data } = await axios.post(route('pos.checkout'), {
                parked_sale_id: parkedSaleId || null,
                ...payload,
                customer_id: resolvedCustomerId,
                payments: foc ? [] : payments,
                foc: Boolean(foc),
            });
            const completedId = data.sale?.id;
            if (parkedSaleId) {
                setParkedBills((prev) => prev.filter((b) => b.id !== parkedSaleId));
            }
            setCart([]);
            setDiscountValue('');
            setDiscountMode('fixed');
            setCustomerId(walkInCustomerId);
            setIsDelivery(false);
            setDeliveryCharge('');
            setDeliveryAddress('');
            setRiderId('');
            setParkedSaleId(null);
            setPayOpen(false);
            router.visit(route('pos.receipt', completedId));
        } catch (err) {
            setMessage(err.response?.data?.message || 'Checkout failed');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="pos-shell flex min-h-screen flex-col text-theme-ink">
            <Head title="POS" />

            <header className="sticky top-0 z-30 border-b border-theme-border/80 bg-theme-surface/90 backdrop-blur-md">
                <div className="flex h-11 items-center justify-between gap-3 px-3 sm:px-4">
                    <div className="flex min-w-0 items-center gap-2">
                        <div
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-[11px] font-bold text-white"
                            style={{ background: 'var(--color-brand-mark)' }}
                        >
                            D
                        </div>
                        <div className="min-w-0 leading-tight">
                            <p className="truncate text-sm font-semibold text-theme-ink">
                                DukanPOS
                                <span className="ml-1.5 hidden font-medium text-theme-ink-muted sm:inline">
                                    · {tenant?.name}
                                    {branch?.name ? ` · ${branch.name}` : ''}
                                </span>
                            </p>
                        </div>
                    </div>

                    <div className="flex shrink-0 items-center gap-1.5">
                        <button
                            type="button"
                            onClick={openTodayHistory}
                            className="inline-flex h-8 items-center gap-1 rounded-md border border-theme-border bg-theme-bg px-2 text-xs font-medium text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink"
                        >
                            <History className="h-3.5 w-3.5" strokeWidth={2} />
                            <span className="hidden sm:inline">Today</span>
                        </button>
                        {enableDelivery && (
                            <button
                                type="button"
                                onClick={() => setDeliveryOpen(true)}
                                className="inline-flex h-8 items-center gap-1 rounded-md border border-theme-border bg-theme-bg px-2 text-xs font-medium text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink"
                            >
                                <Truck className="h-3.5 w-3.5" strokeWidth={2} />
                                <span className="hidden sm:inline">Delivery</span>
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={() => setParkedOpen(true)}
                            className="relative inline-flex h-8 items-center gap-1 rounded-md border border-theme-border bg-theme-bg px-2 text-xs font-medium text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink"
                        >
                            <Clock3 className="h-3.5 w-3.5" strokeWidth={2} />
                            <span className="hidden sm:inline">Saved</span>
                            {parkedBills.length > 0 && (
                                <span className="absolute -right-1.5 -top-1.5 flex h-3.5 min-w-3.5 items-center justify-center rounded-full bg-[var(--color-brand-mark)] px-0.5 text-[9px] font-bold text-white">
                                    {parkedBills.length}
                                </span>
                            )}
                        </button>
                        <button
                            type="button"
                            onClick={() => goAdmin('/admin')}
                            className="inline-flex h-8 items-center gap-1 rounded-md border border-theme-border bg-theme-bg px-2 text-xs font-medium text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink"
                            title="Back office"
                        >
                            <LayoutDashboard className="h-3.5 w-3.5" strokeWidth={2} />
                            <span className="hidden sm:inline">Admin</span>
                        </button>
                    </div>
                </div>
            </header>

            {!shift && (
                <div className="flex items-center justify-between gap-3 border-b border-amber-500/25 bg-amber-500/10 px-4 py-2.5 text-sm text-[var(--color-warning)] sm:px-6">
                    <p>
                        Open a shift before selling — cash drawer must be active.
                    </p>
                    <button
                        type="button"
                        onClick={() => goAdmin('/admin/shifts')}
                        className="inline-flex shrink-0 items-center gap-1 font-semibold underline underline-offset-2"
                    >
                        <ArrowLeftRight className="h-3.5 w-3.5" />
                        Go to Shifts
                    </button>
                </div>
            )}

            <div className="mx-auto grid w-full max-w-[1700px] flex-1 gap-3 p-3 lg:grid-cols-[200px_minmax(0,1fr)_min(100%,400px)] lg:gap-4 lg:p-4">
                <aside className="pos-rise hidden min-h-0 lg:flex lg:flex-col">
                    <div className="dp-card flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div className="border-b border-theme-border px-3 py-3">
                            <p className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                                <LayoutGrid className="h-3.5 w-3.5 text-theme-primary" />
                                Categories
                            </p>
                        </div>
                        <div className="min-h-0 flex-1 space-y-0.5 overflow-y-auto p-2">
                            {categories.map((cat) => {
                                const active = String(categoryId) === String(cat.id);
                                return (
                                    <button
                                        key={cat.id}
                                        type="button"
                                        onClick={() => {
                                            setCategoryId(cat.id);
                                            setQ('');
                                            setResults([]);
                                        }}
                                        className={`flex w-full items-center justify-between gap-2 rounded-lg px-2.5 py-2 text-left text-sm transition ${
                                            active
                                                ? 'bg-[var(--color-primary-soft)] font-semibold text-theme-primary'
                                                : 'text-theme-ink-soft hover:bg-theme-bg hover:text-theme-ink'
                                        }`}
                                    >
                                        <span className="truncate">{cat.name}</span>
                                        <span
                                            className={`shrink-0 tabular-nums text-[11px] ${
                                                active
                                                    ? 'text-theme-primary'
                                                    : 'text-theme-ink-muted'
                                            }`}
                                        >
                                            {cat.count}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                </aside>

                <section className="pos-rise flex min-h-0 flex-col">
                    <div className="dp-card flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div className="border-b border-theme-border px-4 py-3 sm:px-5">
                            <div className="mb-2 flex items-center justify-between gap-2 lg:hidden">
                                <label className="text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                                    Category
                                </label>
                                <select
                                    value={String(categoryId)}
                                    onChange={(e) => {
                                        const value = e.target.value;
                                        setCategoryId(
                                            value === 'all' || value === 'uncategorized'
                                                ? value
                                                : Number(value),
                                        );
                                        setQ('');
                                        setResults([]);
                                    }}
                                    className="dp-select-reset h-9 max-w-[14rem] rounded-lg border border-theme-border bg-theme-bg px-2 text-sm outline-none focus:border-theme-primary"
                                >
                                    {categories.map((cat) => (
                                        <option key={cat.id} value={cat.id}>
                                            {cat.name} ({cat.count})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <label className="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-theme-ink-muted">
                                <Barcode className="h-3.5 w-3.5 text-theme-primary" />
                                Scan or search
                            </label>
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-theme-ink-muted" />
                                <input
                                    ref={searchRef}
                                    autoFocus
                                    value={q}
                                    onChange={(e) => runSearch(e.target.value)}
                                    onKeyDown={onSearchKeyDown}
                                    placeholder="Barcode, short code, or product name — Enter to add"
                                    className="w-full rounded-xl border border-theme-border bg-theme-bg py-3.5 pl-12 pr-4 text-base text-theme-ink outline-none transition placeholder:text-theme-ink-muted focus:border-theme-primary focus:ring-4 focus:ring-theme-primary/15"
                                />
                            </div>
                        </div>

                        <div className="min-h-0 flex-1 overflow-auto p-3 sm:p-4">
                            {products.length > 0 && (
                                <div
                                    className={`grid gap-1.5 ${
                                        showProductImage
                                            ? 'grid-cols-3 sm:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6'
                                            : 'grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-7'
                                    }`}
                                >
                                    {products.map((p, i) => (
                                        <ProductCard
                                            key={p.id}
                                            product={p}
                                            index={i}
                                            showStock={showStock}
                                            showImage={showProductImage}
                                            moneyCfg={moneyCfg}
                                            onSelect={
                                                isSearching || catalogMode === 'flat'
                                                    ? addToCart
                                                    : selectCatalogItem
                                            }
                                        />
                                    ))}
                                </div>
                            )}

                            {products.length === 0 && (
                                <div className="flex h-full min-h-[16rem] flex-col items-center justify-center rounded-xl border border-dashed border-theme-border bg-theme-bg/70 px-6 text-center">
                                    <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--color-primary-soft)] text-theme-primary">
                                        {productsLoading ? (
                                            <Search className="h-6 w-6 animate-pulse" />
                                        ) : (
                                            <Package className="h-6 w-6" strokeWidth={1.75} />
                                        )}
                                    </div>
                                    <p className="font-display text-xl text-theme-ink">
                                        {productsLoading
                                            ? isSearching
                                                ? 'Searching…'
                                                : 'Loading…'
                                            : isSearching
                                              ? 'No matches'
                                              : 'No products'}
                                    </p>
                                    <p className="mt-2 max-w-sm text-sm leading-relaxed text-theme-ink-muted">
                                        {isSearching
                                            ? 'Try another barcode, short code, or name.'
                                            : 'Pick a category or scan a barcode to add items.'}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <aside
                    className="pos-rise flex min-h-0 flex-col lg:sticky lg:top-14 lg:max-h-[calc(100vh-4.25rem)]"
                    style={{ animationDelay: '60ms' }}
                >
                    <div className="dp-card flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div className="flex items-center gap-2 border-b border-theme-border px-3 py-2">
                            <div className="flex min-w-0 shrink-0 items-center gap-2">
                                <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--color-primary-soft)] text-theme-primary">
                                    <Receipt className="h-4 w-4" strokeWidth={2} />
                                </div>
                                <div className="min-w-0">
                                    <h2 className="text-sm font-semibold leading-tight text-theme-ink">
                                        Order
                                    </h2>
                                    <p className="text-[11px] text-theme-ink-muted">
                                        {cart.length} item{cart.length === 1 ? '' : 's'}
                                        {parkedSaleId
                                            ? ` · ${parkedBills.find((b) => b.id === parkedSaleId)?.number || 'saved'}`
                                            : ''}
                                    </p>
                                </div>
                            </div>

                            <SearchableSelect
                                options={customerOptions}
                                value={customerId === '' ? walkInCustomerId : String(customerId)}
                                onChange={selectCustomer}
                                placeholder="Walk-in"
                                size="sm"
                                className="min-w-0 flex-1"
                            />
                            <button
                                type="button"
                                onClick={() => setQuickCustomerOpen(true)}
                                className="inline-flex h-8 shrink-0 items-center justify-center rounded-lg px-1.5 text-theme-ink-muted transition hover:bg-theme-bg hover:text-theme-primary"
                                title="Quick add customer"
                            >
                                <UserPlus className="h-3.5 w-3.5" />
                            </button>

                            {cart.length > 0 && (
                                <button
                                    type="button"
                                    onClick={clearCart}
                                    className="inline-flex h-8 shrink-0 items-center justify-center rounded-lg px-1.5 text-theme-ink-muted transition hover:bg-theme-bg hover:text-theme-danger"
                                    title="Clear order"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            )}
                        </div>

                        <div className="min-h-0 flex-1 space-y-1.5 overflow-auto px-2.5 py-2.5">
                            {cart.length === 0 && (
                                <div className="flex h-full min-h-[10rem] flex-col items-center justify-center rounded-xl border border-dashed border-theme-border px-4 text-center">
                                    <Receipt className="mb-2 h-8 w-8 text-theme-ink-muted/50" />
                                    <p className="text-sm font-medium text-theme-ink-soft">
                                        No items yet
                                    </p>
                                    <p className="mt-1 text-xs text-theme-ink-muted">
                                        Tap a product or scan to add
                                    </p>
                                </div>
                            )}
                            {cart.map((line, idx) => (
                                <div
                                    key={`${line.variant_id}-${line.unit_id}-${idx}`}
                                    className="pos-pop rounded-lg border border-theme-border bg-theme-bg px-2.5 py-2"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <p className="min-w-0 truncate text-sm font-semibold leading-snug text-theme-ink">
                                            {line.name}
                                        </p>
                                        <button
                                            type="button"
                                            className="shrink-0 rounded-md p-1 text-theme-ink-muted transition hover:bg-theme-surface hover:text-theme-danger"
                                            onClick={() =>
                                                setCart((prev) =>
                                                    prev.filter((_, i) => i !== idx),
                                                )
                                            }
                                            title="Remove"
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                    <div className="mt-1.5 flex items-center gap-2">
                                        <div className="inline-flex shrink-0 items-center rounded-md border border-theme-border bg-theme-surface">
                                            <button
                                                type="button"
                                                className="px-2 py-1.5 text-theme-ink-soft transition hover:bg-theme-bg hover:text-theme-ink"
                                                onClick={() => bumpQty(idx, -1)}
                                            >
                                                <Minus className="h-3.5 w-3.5" />
                                            </button>
                                            <input
                                                type="number"
                                                min="0.01"
                                                step="1"
                                                value={line.quantity}
                                                onChange={(e) => setQty(idx, e.target.value)}
                                                className="pos-qty-input w-12 border-x border-theme-border bg-transparent py-1.5 text-center text-sm font-semibold tabular-nums text-theme-ink outline-none"
                                            />
                                            <button
                                                type="button"
                                                className="px-2 py-1.5 text-theme-ink-soft transition hover:bg-theme-bg hover:text-theme-ink"
                                                onClick={() => bumpQty(idx, 1)}
                                            >
                                                <Plus className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <span className="min-w-0 flex-1 truncate text-[11px] text-theme-ink-muted">
                                            {formatMoney(line.unit_price, moneyCfg)}
                                            {line.unit_code ? ` / ${line.unit_code}` : ''}
                                        </span>
                                        <span className="shrink-0 text-sm font-bold tabular-nums text-theme-ink">
                                            {formatMoney(
                                                Number(line.quantity) * Number(line.unit_price),
                                                moneyCfg,
                                            )}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <div className="space-y-3 border-t border-theme-border bg-theme-surface p-3">
                            <div className="space-y-1.5 text-sm">
                                <div className="flex items-center justify-between gap-3 text-theme-ink-soft">
                                    <span>Subtotal</span>
                                    <span className="tabular-nums text-theme-ink">
                                        {formatMoney(totals.subtotal, moneyCfg)}
                                    </span>
                                </div>

                                <div className="flex items-center justify-between gap-2 text-theme-ink-soft">
                                    <span className="shrink-0">Discount</span>
                                    <div className="flex min-w-0 items-center gap-1.5">
                                        <div className="inline-flex shrink-0 overflow-hidden rounded-md border border-theme-border bg-theme-bg text-[11px] font-semibold">
                                            <button
                                                type="button"
                                                onClick={() => setDiscountMode('percent')}
                                                className={`px-2 py-1 transition ${
                                                    discountMode === 'percent'
                                                        ? 'bg-theme-primary text-[var(--color-on-primary)]'
                                                        : 'text-theme-ink-muted hover:text-theme-ink'
                                                }`}
                                            >
                                                %
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => setDiscountMode('fixed')}
                                                className={`px-2 py-1 transition ${
                                                    discountMode === 'fixed'
                                                        ? 'bg-theme-primary text-[var(--color-on-primary)]'
                                                        : 'text-theme-ink-muted hover:text-theme-ink'
                                                }`}
                                            >
                                                Fixed
                                            </button>
                                        </div>
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            value={discountValue}
                                            onChange={(e) => setDiscountValue(e.target.value)}
                                            placeholder="0.00"
                                            className="pos-qty-input h-7 w-20 rounded-md border border-theme-border bg-theme-bg px-2 text-right text-xs tabular-nums text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center justify-between gap-3 text-theme-ink-soft">
                                    <span>
                                        Tax
                                        {totals.taxRateLabel != null
                                            ? ` (${totals.taxRateLabel}%)`
                                            : ''}
                                    </span>
                                    <span className="tabular-nums text-theme-ink">
                                        {formatMoney(totals.tax, moneyCfg)}
                                    </span>
                                </div>

                                {enableDelivery && (
                                    <div className="space-y-1.5 rounded-lg border border-theme-border bg-theme-bg px-2.5 py-2">
                                        <label className="flex items-center justify-between gap-2 text-theme-ink">
                                            <span className="text-sm font-medium">Delivery</span>
                                            <input
                                                type="checkbox"
                                                checked={isDelivery}
                                                onChange={(e) => toggleDelivery(e.target.checked)}
                                                className="rounded border-theme-border text-theme-primary focus:ring-theme-primary"
                                            />
                                        </label>
                                        {isDelivery && (
                                            <>
                                                <div className="flex items-center justify-between gap-2 text-theme-ink-soft">
                                                    <span className="shrink-0 text-xs">Charge</span>
                                                    <input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={deliveryCharge}
                                                        onChange={(e) =>
                                                            setDeliveryCharge(e.target.value)
                                                        }
                                                        placeholder="0.00"
                                                        className="pos-qty-input h-7 w-24 rounded-md border border-theme-border bg-theme-surface px-2 text-right text-xs tabular-nums text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                    />
                                                </div>
                                                <textarea
                                                    rows={2}
                                                    value={deliveryAddress}
                                                    onChange={(e) =>
                                                        setDeliveryAddress(e.target.value)
                                                    }
                                                    placeholder="Delivery address"
                                                    className="w-full rounded-md border border-theme-border bg-theme-surface px-2 py-1.5 text-xs text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                />
                                                <select
                                                    value={riderId}
                                                    onChange={(e) => setRiderId(e.target.value)}
                                                    className="w-full rounded-md border border-theme-border bg-theme-surface px-2 py-1.5 text-xs text-theme-ink outline-none focus:border-theme-primary focus:ring-2 focus:ring-theme-primary/20"
                                                >
                                                    {riderOptions.map((opt) => (
                                                        <option
                                                            key={opt.value || 'none'}
                                                            value={opt.value}
                                                        >
                                                            {opt.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </>
                                        )}
                                    </div>
                                )}

                                <div className="flex items-center justify-between gap-3 border-t border-theme-border pt-2">
                                    <span className="font-semibold text-theme-ink">Total</span>
                                    <span className="text-base font-bold tabular-nums text-theme-primary">
                                        {formatMoney(totals.total, moneyCfg)}
                                    </span>
                                </div>
                            </div>

                            {message && !payOpen && (
                                <p className="text-sm text-[var(--color-warning)]">{message}</p>
                            )}

                            <div className="grid grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    disabled={!cart.length || busy}
                                    onClick={saveForLater}
                                    className="inline-flex items-center justify-center gap-1.5 rounded-xl border border-theme-border bg-theme-bg py-3 text-sm font-semibold text-theme-ink-soft transition hover:border-theme-primary/40 hover:text-theme-ink disabled:cursor-not-allowed disabled:opacity-45"
                                >
                                    <Bookmark className="h-4 w-4" strokeWidth={2} />
                                    {parkedSaleId ? 'Update saved' : 'Save for later'}
                                </button>
                                <button
                                    type="button"
                                    disabled={!cart.length || busy}
                                    onClick={openPay}
                                    className="pos-pay-btn rounded-xl py-3 text-sm font-bold tracking-wide disabled:cursor-not-allowed"
                                >
                                    Pay
                                </button>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <TodayHistoryDrawer
                open={todayOpen}
                onClose={() => setTodayOpen(false)}
                sales={todaySales}
                loading={todayLoading}
                moneyCfg={moneyCfg}
                onCancelled={loadTodaySales}
                onViewReceipt={(sale) => {
                    setTodayOpen(false);
                    router.visit(route('pos.receipt', sale.id));
                }}
            />

            {enableDelivery && (
                <DeliveryOrdersDrawer
                    open={deliveryOpen}
                    onClose={() => setDeliveryOpen(false)}
                    moneyCfg={moneyCfg}
                    riders={riders}
                />
            )}

            <VariantPicker
                product={variantPicker}
                showStock={showStock}
                moneyCfg={moneyCfg}
                onPick={addToCart}
                onClose={() => setVariantPicker(null)}
            />

            <ParkedBillsDrawer
                open={parkedOpen}
                onClose={() => setParkedOpen(false)}
                bills={parkedBills}
                moneyCfg={moneyCfg}
                busyId={discardBusyId}
                onResume={resumeParked}
                onDiscard={discardParked}
            />

            <QuickCustomerModal
                open={quickCustomerOpen}
                onClose={() => setQuickCustomerOpen(false)}
                onCreated={onQuickCustomerCreated}
            />

            <PaymentModal
                open={payOpen}
                onClose={() => {
                    if (!busy) setPayOpen(false);
                }}
                onConfirm={checkout}
                busy={busy}
                totals={totals}
                moneySources={moneySources}
                customers={customerList}
                customerId={customerId}
                onCustomerChange={selectCustomer}
                allowCredit={allowCredit}
                moneyCfg={moneyCfg}
                error={message}
            />
        </div>
    );
}
