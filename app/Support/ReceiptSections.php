<?php

namespace App\Support;

class ReceiptSections
{
    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return [
            'logo' => true,
            'branch_name' => true,
            'address' => true,
            'phone' => true,
            'tax_id' => true,
            'invoice_title' => true,
            'sale_number' => true,
            'date_cashier' => true,
            'customer_block' => true,
            'items_header' => true,
            'item_variants' => true,
            'item_unit_price' => true,
            'subtotal' => true,
            'discount' => true,
            'tax' => true,
            'payment_info' => true,
            'thank_you' => true,
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::defaults());
    }

    /**
     * @return array<string, array{label: string, description?: string, keys: list<string>}>
     */
    public static function groups(): array
    {
        return [
            'header' => [
                'label' => 'Header',
                'keys' => ['logo', 'branch_name', 'address', 'phone', 'tax_id'],
            ],
            'invoice_info' => [
                'label' => 'Invoice info',
                'keys' => ['invoice_title', 'sale_number', 'date_cashier'],
            ],
            'customer' => [
                'label' => 'Customer',
                'keys' => ['customer_block'],
            ],
            'items' => [
                'label' => 'Line items',
                'keys' => ['items_header', 'item_variants', 'item_unit_price'],
            ],
            'totals' => [
                'label' => 'Totals',
                'description' => 'Grand total is always shown. Subtotal is hidden automatically when it matches the total.',
                'keys' => ['subtotal', 'discount', 'tax'],
            ],
            'payment' => [
                'label' => 'Payment',
                'keys' => ['payment_info'],
            ],
            'footer' => [
                'label' => 'Footer',
                'keys' => ['thank_you'],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'logo' => 'Logo',
            'branch_name' => 'Branch name',
            'address' => 'Address',
            'phone' => 'Phone number',
            'tax_id' => 'Tax ID / NTN',
            'invoice_title' => '"INVOICE" title',
            'sale_number' => 'Sale number',
            'date_cashier' => 'Date & cashier (one line)',
            'customer_block' => 'Customer details',
            'items_header' => 'Column headers (Item / Qty / Price)',
            'item_variants' => 'Variant name (e.g. size)',
            'item_unit_price' => 'Unit price under item',
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'tax' => 'Tax',
            'payment_info' => 'Payment method & status',
            'thank_you' => 'Thank you / footer message',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $input
     * @return array<string, bool>
     */
    public static function normalize(?array $input): array
    {
        $defaults = self::defaults();
        $normalized = [];

        foreach ($defaults as $key => $default) {
            if ($input === null || ! array_key_exists($key, $input)) {
                $normalized[$key] = $default;

                continue;
            }

            $normalized[$key] = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN);
        }

        return $normalized;
    }

    /**
     * @param  array<string, bool>  $sections
     */
    public static function enabled(array $sections, string $key): bool
    {
        return (bool) ($sections[$key] ?? self::defaults()[$key] ?? false);
    }

    /**
     * Whether subtotal row should render (skips redundant subtotal when equal to total).
     *
     * @param  array<string, bool>  $sections
     * @param  array<string, mixed>|object  $sale
     */
    public static function shouldShowSubtotal(array $sections, array|object $sale): bool
    {
        if (! self::enabled($sections, 'subtotal')) {
            return false;
        }

        $subtotal = (float) (is_array($sale) ? ($sale['subtotal'] ?? 0) : ($sale->subtotal ?? 0));
        $total = (float) (is_array($sale) ? ($sale['total'] ?? 0) : ($sale->total ?? 0));
        $discount = (float) (is_array($sale) ? ($sale['discount_total'] ?? 0) : ($sale->discount_total ?? 0));
        $tax = (float) (is_array($sale) ? ($sale['tax_total'] ?? 0) : ($sale->tax_total ?? 0));

        if (abs($subtotal - $total) < 0.01
            && $discount <= 0.01
            && $tax <= 0.01) {
            return false;
        }

        return true;
    }

    /**
     * Sample sale payload for receipt preview in settings.
     *
     * @return array<string, mixed>
     */
    public static function sampleSale(): array
    {
        return [
            'number' => 'SL-20260731-0001',
            'created_at' => '31/07/2026 3:25 PM',
            'subtotal' => 3300,
            'discount_total' => 50,
            'tax_total' => 162.5,
            'total' => 3412.5,
            'paid_total' => 3412.5,
            'cashier' => ['name' => 'Admin'],
            'customer' => ['name' => 'Walk-in Customer', 'phone' => '0300-1234567'],
            'items' => [
                [
                    'name' => 'Premium Rice 5kg',
                    'variant' => null,
                    'qty' => 2,
                    'unit_price' => 1250,
                    'line_total' => 2500,
                ],
                [
                    'name' => 'Cooking Oil',
                    'variant' => '1 Litre',
                    'qty' => 1,
                    'unit_price' => 480,
                    'line_total' => 480,
                ],
                [
                    'name' => 'Detergent Powder',
                    'variant' => null,
                    'qty' => 1,
                    'unit_price' => 320,
                    'line_total' => 320,
                ],
            ],
            'payments' => [
                ['name' => 'Cash', 'amount' => 3412.5],
            ],
        ];
    }
}
