<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Services\SettingService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'business_date' => ['required', 'date_format:Y-m-d'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_delivery' => ['sometimes', 'boolean'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'delivery_address' => ['nullable', 'string', 'max:2000'],
            'rider_id' => ['nullable', 'integer', 'exists:users,id'],
            'money_source_id' => ['nullable', 'integer', 'exists:money_sources,id'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $isDelivery = (bool) $this->boolean('is_delivery');
            $paidAmount = (float) ($this->input('paid_amount') ?? 0);
            $customerId = $this->input('customer_id') ? (int) $this->input('customer_id') : null;
            $walkIn = Customer::walkIn();
            $resolvedCustomerId = $customerId;

            if (! $resolvedCustomerId && $walkIn && ! $isDelivery) {
                $resolvedCustomerId = (int) $walkIn->id;
            }

            $isWalkIn = $walkIn && $resolvedCustomerId === (int) $walkIn->id;
            $creditCustomerId = $isWalkIn ? null : $resolvedCustomerId;

            if ($isDelivery) {
                if (! $creditCustomerId) {
                    $validator->errors()->add('customer_id', 'Delivery requires a named customer.');

                    return;
                }

                if (! app(SettingService::class)->allowPosDelivery()) {
                    $validator->errors()->add('is_delivery', 'Delivery is disabled in company settings.');
                }

                return;
            }

            if ($paidAmount <= 0 && ! $creditCustomerId) {
                $validator->errors()->add(
                    'paid_amount',
                    'Add a payment or select a customer for credit.',
                );

                return;
            }

            if ($paidAmount <= 0 && ! app(SettingService::class)->allowPosCredit()) {
                $validator->errors()->add('paid_amount', 'Credit sales are disabled in settings.');
            }

            if ($paidAmount > 0 && ! $this->input('money_source_id')) {
                $validator->errors()->add('money_source_id', 'Select a money source for the payment.');
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(int $branchId, ?int $shiftId, bool $allowCredit): array
    {
        $isDelivery = (bool) $this->boolean('is_delivery');
        $walkIn = Customer::walkIn();
        $customerId = $this->input('customer_id') ? (int) $this->input('customer_id') : null;

        if (! $customerId && $walkIn && ! $isDelivery) {
            $customerId = (int) $walkIn->id;
        }

        $paidAmount = (float) ($this->input('paid_amount') ?? 0);
        $payments = [];
        if ($paidAmount > 0 && $this->input('money_source_id')) {
            $payments[] = [
                'money_source_id' => (int) $this->input('money_source_id'),
                'amount' => $paidAmount,
            ];
        }

        return [
            'branch_id' => $branchId,
            'shift_id' => $shiftId,
            'business_date' => (string) $this->input('business_date'),
            'customer_id' => $customerId,
            'discount_total' => (float) ($this->input('discount_total') ?? 0),
            'notes' => $this->input('notes'),
            'is_delivery' => $isDelivery,
            'delivery_charge' => $isDelivery ? (float) ($this->input('delivery_charge') ?? 0) : 0,
            'delivery_address' => $isDelivery ? ($this->input('delivery_address') ?: null) : null,
            'rider_id' => $isDelivery && $this->input('rider_id') ? (int) $this->input('rider_id') : null,
            'items' => collect($this->input('items', []))->map(fn ($row) => [
                'variant_id' => (int) $row['variant_id'],
                'unit_id' => ! empty($row['unit_id']) ? (int) $row['unit_id'] : null,
                'quantity' => (float) $row['quantity'],
                'unit_price' => (float) $row['unit_price'],
                'discount' => (float) ($row['discount'] ?? 0),
            ])->all(),
            'payments' => $payments,
            'allow_credit' => $allowCredit,
        ];
    }
}
