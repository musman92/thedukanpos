import { formatMoney } from '@/lib/money';
import { Head } from '@inertiajs/react';

function sectionOn(sections, key) {
    return sections?.[key] !== false;
}

function shouldShowSubtotal(sections, sale) {
    if (!sectionOn(sections, 'subtotal')) return false;
    const subtotal = Number(sale.subtotal || 0);
    const total = Number(sale.total || 0);
    const discount = Number(sale.discount_total || 0);
    const tax = Number(sale.tax_total || 0);
    if (Math.abs(subtotal - total) < 0.01 && discount <= 0.01 && tax <= 0.01) {
        return false;
    }
    return true;
}

export default function Receipt({ sale, tenant, branding }) {
    const name = branding?.shop_name || tenant?.name;
    const sections = branding?.receipt_sections || {};
    const paperMm = Number(branding?.receipt_paper_width || 80);
    const fontSize = Number(branding?.receipt_font_size || 14);
    const moneyCfg = {
        currency_symbol: branding?.currency_symbol,
        currency: branding?.currency,
        currency_position: branding?.currency_position,
        decimal_points: branding?.decimal_points,
    };
    const money = (n) => formatMoney(n, moneyCfg);
    const due = Number(sale.total) - Number(sale.paid_total);
    const maxWidth = paperMm <= 58 ? 'max-w-[16rem]' : 'max-w-sm';
    const showSubtotal = shouldShowSubtotal(sections, sale);
    const showMeta =
        sectionOn(sections, 'sale_number') ||
        sectionOn(sections, 'date_cashier') ||
        (sectionOn(sections, 'customer_block') && sale.customer?.name);

    return (
        <div className="min-h-screen bg-white text-stone-900">
            <Head title={`Receipt ${sale.number}`} />
            <div
                className={`mx-auto ${maxWidth} px-4 py-8 print:max-w-none`}
                style={{ fontSize: `${fontSize}px` }}
            >
                <div className="text-center">
                    {sectionOn(sections, 'logo') && branding?.logo_url && (
                        <img
                            src={branding.logo_url}
                            alt=""
                            className="mx-auto mb-2 h-14 w-auto object-contain"
                        />
                    )}
                    <h1 className="text-lg font-semibold">{name}</h1>
                    {sectionOn(sections, 'branch_name') && branding?.branch_name && (
                        <p className="text-xs text-stone-500">{branding.branch_name}</p>
                    )}
                    {sectionOn(sections, 'address') && branding?.address && (
                        <p className="whitespace-pre-line text-xs text-stone-500">
                            {branding.address}
                        </p>
                    )}
                    {sectionOn(sections, 'phone') && branding?.phone && (
                        <p className="text-xs text-stone-500">{branding.phone}</p>
                    )}
                    {sectionOn(sections, 'tax_id') && branding?.tax_id && (
                        <p className="text-xs text-stone-500">NTN: {branding.tax_id}</p>
                    )}
                </div>

                {sectionOn(sections, 'invoice_title') && (
                    <p className="mt-3 text-center text-base font-bold tracking-wide">INVOICE</p>
                )}

                {showMeta && (
                    <div className="mt-2 border-b border-dashed border-stone-300 pb-2 text-center text-xs text-stone-500">
                        {sectionOn(sections, 'sale_number') && <p>{sale.number}</p>}
                        {sectionOn(sections, 'date_cashier') && (
                            <p>
                                {sale.created_at}
                                {sale.cashier?.name ? ` · Cashier: ${sale.cashier.name}` : ''}
                            </p>
                        )}
                        {sectionOn(sections, 'customer_block') && sale.customer?.name && (
                            <p>
                                Customer: {sale.customer.name}
                                {sale.customer.phone ? ` · ${sale.customer.phone}` : ''}
                            </p>
                        )}
                    </div>
                )}

                <table className="mt-4 w-full">
                    {sectionOn(sections, 'items_header') && (
                        <thead>
                            <tr className="border-b border-dashed border-stone-300 text-xs">
                                <th className="py-1 text-left font-semibold">Item</th>
                                <th className="w-[14%] py-1 text-center font-semibold">Qty</th>
                                <th className="w-[32%] py-1 text-right font-semibold">Price</th>
                            </tr>
                        </thead>
                    )}
                    <tbody>
                        {sale.items.map((item) => {
                            const productName = item.product?.name || 'Item';
                            const variantName = item.variant?.name;

                            return (
                                <tr key={item.id} className="border-t border-stone-100 align-top">
                                    <td className="py-2">
                                        <p className="font-medium">{productName}</p>
                                        {sectionOn(sections, 'item_variants') && variantName && (
                                            <p className="text-xs text-stone-500">{variantName}</p>
                                        )}
                                        {sectionOn(sections, 'item_unit_price') && (
                                            <p className="text-xs text-stone-500">
                                                {item.quantity} × {money(item.unit_price)}
                                                {item.tax_rate > 0
                                                    ? ` · tax ${item.tax_rate}%`
                                                    : ''}
                                            </p>
                                        )}
                                    </td>
                                    <td className="py-2 text-center">{item.quantity}</td>
                                    <td className="py-2 text-right">{money(item.line_total)}</td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>

                <div className="mt-4 space-y-1 border-t border-stone-200 pt-3">
                    {showSubtotal && (
                        <div className="flex justify-between">
                            <span>Subtotal</span>
                            <span>{money(sale.subtotal)}</span>
                        </div>
                    )}
                    {sectionOn(sections, 'discount') && Number(sale.discount_total) > 0 && (
                        <div className="flex justify-between">
                            <span>Discount</span>
                            <span>-{money(sale.discount_total)}</span>
                        </div>
                    )}
                    {sectionOn(sections, 'tax') && (
                        <div className="flex justify-between">
                            <span>Tax</span>
                            <span>{money(sale.tax_total)}</span>
                        </div>
                    )}
                    {sale.is_delivery && Number(sale.delivery_charge || 0) > 0 && (
                        <div className="flex justify-between">
                            <span>Delivery</span>
                            <span>{money(sale.delivery_charge)}</span>
                        </div>
                    )}
                    <div className="flex justify-between border-y border-stone-900 py-1 font-semibold">
                        <span>Total</span>
                        <span>{money(sale.total)}</span>
                    </div>
                    {sale.is_delivery && (sale.delivery_address || sale.rider?.name) && (
                        <div className="pt-2 text-xs text-stone-500">
                            {sale.delivery_address && (
                                <>
                                    <p className="font-semibold text-stone-700">Deliver to</p>
                                    <p className="mt-0.5 whitespace-pre-line">
                                        {sale.delivery_address}
                                    </p>
                                </>
                            )}
                            {sale.rider?.name && (
                                <p className="mt-1">Rider: {sale.rider.name}</p>
                            )}
                        </div>
                    )}
                    {sectionOn(sections, 'payment_info') && (
                        <>
                            <div className="flex justify-between">
                                <span>Paid</span>
                                <span>{money(sale.paid_total)}</span>
                            </div>
                            {(sale.payments || []).map((p) => (
                                <div
                                    key={p.id}
                                    className="flex justify-between text-xs text-stone-500"
                                >
                                    <span>{p.money_source?.name || 'Payment'}</span>
                                    <span>{money(p.amount)}</span>
                                </div>
                            ))}
                            {due > 0.01 && (
                                <div className="flex justify-between text-amber-700">
                                    <span>
                                        On account
                                        {sale.customer ? ` (${sale.customer.name})` : ''}
                                    </span>
                                    <span>{money(due)}</span>
                                </div>
                            )}
                        </>
                    )}
                </div>

                {sectionOn(sections, 'thank_you') && branding?.receipt_footer && (
                    <p className="mt-6 text-center text-xs text-stone-500">
                        {branding.receipt_footer}
                    </p>
                )}

                <div className="mt-8 flex gap-3 print:hidden">
                    <button
                        type="button"
                        onClick={() => window.print()}
                        className="flex-1 rounded-md bg-stone-900 py-2 text-white"
                    >
                        Print
                    </button>
                    <a
                        href="/pos"
                        className="flex-1 rounded-md border border-stone-300 py-2 text-center"
                    >
                        New sale
                    </a>
                </div>
            </div>
        </div>
    );
}
