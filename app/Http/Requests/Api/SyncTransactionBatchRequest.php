<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SyncTransactionBatchRequest extends FormRequest
{
    /**
     * Authorization is handled by VerifyCentralSyncToken middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the sync batch request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transactions' => ['required', 'array', 'min:1'],
            'transactions.*.transaction_uuid' => ['required', 'uuid'],
            'transactions.*.employee_uuid' => ['required', 'uuid'],
            'transactions.*.invoice_number' => ['nullable', 'string', 'max:120'],
            'transactions.*.employee_nik' => ['nullable', 'string', 'max:50'],
            'transactions.*.cashier_name' => ['nullable', 'string', 'max:150'],
            'transactions.*.shift_uuid' => ['nullable', 'uuid'],
            'transactions.*.shift_code' => ['nullable', 'string', 'max:100'],
            'transactions.*.terminal_code' => ['nullable', 'string', 'max:50'],
            'transactions.*.location_uuid' => ['nullable', 'uuid'],
            'transactions.*.branch' => ['nullable', 'string', 'max:100'],
            'transactions.*.member_uuid' => ['nullable', 'uuid'],
            'transactions.*.member_nik' => ['nullable', 'string', 'max:50'],
            'transactions.*.member_name' => ['nullable', 'string', 'max:150'],
            'transactions.*.payment_method' => ['nullable', 'string', 'max:30'],
            'transactions.*.subtotal' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.discount_total' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.promo_discount_total' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.promo_enabled' => ['nullable', 'boolean'],
            'transactions.*.tax_total' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.grand_total' => ['required', 'numeric', 'min:0'],
            'transactions.*.cash_received' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.change_returned' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.payment_reference' => ['nullable', 'string', 'max:150'],
            'transactions.*.split_cash' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.split_non_cash' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.status' => ['nullable', 'string', 'max:30'],
            'transactions.*.is_over_limit' => ['nullable', 'boolean'],
            'transactions.*.debt_status' => ['nullable', 'string', 'max:50'],
            'transactions.*.created_at' => ['nullable', 'date'],

            'transactions.*.items' => ['required', 'array', 'min:1'],
            'transactions.*.items.*.item_uuid' => ['nullable', 'uuid'],
            'transactions.*.items.*.product_uuid' => ['required', 'uuid'],
            'transactions.*.items.*.sku' => ['nullable', 'string', 'max:100'],
            'transactions.*.items.*.barcode' => ['nullable', 'string', 'max:100'],
            'transactions.*.items.*.coupon_number' => ['nullable', 'string', 'max:100'],
            'transactions.*.items.*.product_name' => ['nullable', 'string', 'max:255'],
            'transactions.*.items.*.unit' => ['nullable', 'string', 'max:50'],
            'transactions.*.items.*.qty' => ['required', 'integer', 'min:1'],
            'transactions.*.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'transactions.*.items.*.is_taxable' => ['nullable', 'boolean'],
            'transactions.*.items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.is_expired_clearance' => ['nullable', 'boolean'],
            'transactions.*.items.*.clearance_discount_percent' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'transactions.*.items.*.promo_name' => ['nullable', 'string', 'max:150'],
            'transactions.*.items.*.promo_discount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
