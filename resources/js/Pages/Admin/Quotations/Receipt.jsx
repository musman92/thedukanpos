import { formatMoney } from '@/lib/money';
import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

function sectionOn(sections, key) {
    return sections?.[key] !== false;
}

export default function Receipt({
    quotation,
    tenant,
    branding,
    back_url: backUrl = null,
    auto_print: autoPrint = false,
}) {
    if (!quotation) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-white p-8 text-stone-600">
                Receipt could not be loaded.
            </div>
        );
    }

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
    const maxWidth = paperMm <= 58 ? 'max-w-[16rem]' : 'max-w-sm';

    useEffect(() => {
        if (!autoPrint) {
            return undefined;
        }

        const timer = window.setTimeout(() => window.print(), 350);

        return () => window.clearTimeout(timer);
    }, [autoPrint]);

    return (
        <div className="min-h-screen bg-white text-stone-900">
            <Head title={`Quotation ${quotation.number}`} />
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

                <p className="mt-3 text-center text-base font-bold tracking-wide">QUOTATION</p>

                <div className="mt-2 border-b border-dashed border-stone-300 pb-2 text-center text-xs text-stone-500">
                    <p>{quotation.number}</p>
                    <p>
                        {quotation.quote_date}
                        {quotation.creator?.name ? ` · By: ${quotation.creator.name}` : ''}
                    </p>
                    {quotation.valid_until && (
                        <p>Valid until: {quotation.valid_until}</p>
                    )}
                    {sectionOn(sections, 'customer_block') && quotation.customer?.name && (
                        <p>
                            Customer: {quotation.customer.name}
                            {quotation.customer.phone ? ` · ${quotation.customer.phone}` : ''}
                        </p>
                    )}
                </div>

                <table className="mt-4 w-full">
                    {sectionOn(sections, 'items_header') && (
                        <thead>
                            <tr className="border-b border-dashed border-stone-300 text-xs">
                                <th className="py-1 text-left font-semibold">Item</th>
                                <th className="w-[14%] py-1 text-center font-semibold">Qty</th>
                                <th className="w-[32%] py-1 text-right font-semibold">Amount</th>
                            </tr>
                        </thead>
                    )}
                    <tbody>
                        {(quotation.items || []).map((item) => {
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
                                                {item.tax_rate > 0 ? ` · tax ${item.tax_rate}%` : ''}
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
                    <div className="flex justify-between">
                        <span>Subtotal</span>
                        <span>{money(quotation.subtotal)}</span>
                    </div>
                    {Number(quotation.discount_total) > 0 && (
                        <div className="flex justify-between">
                            <span>Discount</span>
                            <span>-{money(quotation.discount_total)}</span>
                        </div>
                    )}
                    {Number(quotation.tax_total) > 0 && (
                        <div className="flex justify-between">
                            <span>Tax</span>
                            <span>{money(quotation.tax_total)}</span>
                        </div>
                    )}
                    <div className="flex justify-between border-y border-stone-900 py-1 font-semibold">
                        <span>Total</span>
                        <span>{money(quotation.total)}</span>
                    </div>
                </div>

                {quotation.notes && (
                    <div className="mt-4 border-t border-dashed border-stone-300 pt-3 text-xs text-stone-500">
                        <p className="font-semibold text-stone-700">Notes</p>
                        <p className="mt-1 whitespace-pre-line">{quotation.notes}</p>
                    </div>
                )}

                <p className="mt-4 text-center text-xs text-stone-500">
                    Not a tax invoice. For quotation purposes only.
                </p>

                {sectionOn(sections, 'thank_you') && branding?.receipt_footer && (
                    <p className="mt-4 text-center text-xs text-stone-500">
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
                    {backUrl ? (
                        <a
                            href={backUrl}
                            className="flex-1 rounded-md border border-stone-300 py-2 text-center"
                        >
                            Back to quotation
                        </a>
                    ) : (
                        <a
                            href={route('admin.quotations.index')}
                            className="flex-1 rounded-md border border-stone-300 py-2 text-center"
                        >
                            All quotations
                        </a>
                    )}
                </div>
            </div>
        </div>
    );
}
