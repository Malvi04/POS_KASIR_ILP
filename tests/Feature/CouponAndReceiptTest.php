<?php

namespace Tests\Feature;

use App\Models\CashierShift;
use App\Models\CouponUnit;
use App\Models\Member;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponAndReceiptTest extends TestCase
{
    public function test_coupon_crud_and_redemption_workflow(): void
    {
        // 1. List coupons
        $listRes = $this->getJson('/api/coupons');
        $listRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'summary' => ['total_count', 'active_count', 'redeemed_count', 'total_nominal'],
                'data',
            ]);

        // 2. Create new coupon
        $createRes = $this->postJson('/api/coupons', [
            'category' => 'bbm',
            'recipient_name' => 'Wahyudi Santoso',
            'recipient_nik' => '327501009911002',
            'recipient_dept' => 'Armada Truk Pengangkut',
            'vehicle_no' => 'B 9012 UQ',
            'volume' => '25 Liter',
            'nominal_value' => 250000,
            'notes' => 'Kupon Pertalite Armada Kebun Barat',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('success', true);

        $couponId = $createRes->json('data.id');
        $this->assertDatabaseHas('coupon_units', [
            'id' => $couponId,
            'recipient_name' => 'Wahyudi Santoso',
            'category' => 'bbm',
        ]);

        // 3. Update coupon
        $updateRes = $this->putJson("/api/coupons/{$couponId}", [
            'volume' => '30 Liter',
            'nominal_value' => 300000,
            'notes' => 'Diperbarui menjadi 30 Liter Pertalite',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('coupon_units', [
            'id' => $couponId,
            'volume' => '30 Liter',
            'nominal_value' => 300000,
        ]);

        // 4. Redeem coupon
        $user = User::first();
        $redeemRes = $this->postJson("/api/coupons/{$couponId}/redeem", [
            'user_id' => $user?->id,
        ]);

        $redeemRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('coupon_units', [
            'id' => $couponId,
            'status' => 'REDEEMED',
        ]);
        $this->assertDatabaseHas('coupon_redemptions', [
            'coupon_unit_id' => $couponId,
            'status' => 'COMPLETED',
        ]);

        // Cannot redeem twice
        $doubleRedeemRes = $this->postJson("/api/coupons/{$couponId}/redeem");
        $doubleRedeemRes->assertStatus(422);

        // 5. Delete coupon
        $deleteRes = $this->deleteJson("/api/coupons/{$couponId}");
        $deleteRes->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('coupon_units', ['id' => $couponId]);
    }

    public function test_transaction_lookup_for_thermal_receipt_printing(): void
    {
        $terminal = 'RCP-' . rand(10000, 99999);
        CashierShift::where('terminal_code', $terminal)->delete();

        $openRes = $this->postJson('/api/shift/open', [
            'terminal_code' => $terminal,
            'cashier_nik' => '202409821002',
            'cashier_name' => 'Budi Kurniawan',
            'opening_cash' => 1740000,
        ]);
        $openRes->assertStatus(201);

        $product = Product::first();
        $product->update(['stock' => max(100, (int) $product->stock)]);
        $itemPrice = (float) ($product->price > 0 ? $product->price : 10000);
        $totalAmount = $itemPrice * 2;

        $checkoutRes = $this->postJson('/api/pos/checkout', [
            'terminal_code' => $terminal,
            'cashier_nik' => '202409821002',
            'payment_method' => 'TUNAI',
            'cash_received' => $totalAmount + 50000,
            'items' => [
                [
                    'product_id' => $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'qty' => 2,
                    'unit' => 'Pcs',
                    'price' => $itemPrice,
                    'unit_price' => $itemPrice,
                ],
            ],
        ]);

        $checkoutRes->assertStatus(201);
        $invoice = $checkoutRes->json('data.invoice_number');

        // Look up by invoice number for thermal printing preview
        $receiptRes = $this->getJson("/api/pos/transactions/{$invoice}");
        $receiptRes->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.invoice_number', $invoice)
            ->assertJsonPath('data.payment_method', 'TUNAI');
    }
}
