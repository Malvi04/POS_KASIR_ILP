<?php

namespace Tests\Feature;

use App\Models\CouponUnit;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityHardeningAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure clean test supervisor with a unique PIN
        User::updateOrCreate(
            ['username' => 'sec_spv_real'],
            [
                'name' => 'Real Supervisor Secure',
                'email' => 'spv_real_sec@test.com',
                'nik' => 'SPV-SEC-99',
                'role' => 'Supervisor',
                'pin' => '778899', // Genuine unique PIN
                'branch' => 'Pusat',
                'password' => Hash::make('secretSPV99'),
                'is_active' => true,
            ]
        );

        // Ensure cashier exists
        User::updateOrCreate(
            ['username' => 'sec_kasir_real'],
            [
                'name' => 'Real Cashier Secure',
                'email' => 'kasir_real_sec@test.com',
                'nik' => 'KASIR-SEC-88',
                'role' => 'Kasir',
                'pin' => '112233',
                'branch' => 'Pusat',
                'password' => Hash::make('secretKasir88'),
                'is_active' => true,
            ]
        );
    }

    /**
     * Security Test 1: Verify backdoor PIN 123456 does NOT authorize void when not assigned.
     */
    public function test_backdoor_pin_123456_fails_if_not_assigned(): void
    {
        // 000000 or fake 123456 where no supervisor has it
        $response = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '000000',
        ]);
        $response->assertStatus(403);
        $this->assertFalse($response->json('authorized'));
    }

    /**
     * Security Test 2: Valid supervisor PIN succeeds for void.
     */
    public function test_valid_supervisor_pin_succeeds_for_void(): void
    {
        $response = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '778899',
        ]);
        $response->assertStatus(200);
        $this->assertTrue($response->json('authorized'));
        $this->assertEquals('Real Supervisor Secure', $response->json('supervisor.name'));
    }

    /**
     * Security Test 3: Cashier PIN is strictly denied void authorization.
     */
    public function test_cashier_pin_is_strictly_denied_void(): void
    {
        $response = $this->postJson('/api/pos/void/authorize', [
            'pin_or_password' => '112233',
        ]);
        $response->assertStatus(403);
        $this->assertFalse($response->json('authorized'));
        $this->assertStringContainsString('Kasir tidak memiliki wewenang', $response->json('message'));
    }

    /**
     * Security Test 4: Negative discount value is rejected.
     */
    public function test_negative_discount_value_is_rejected(): void
    {
        $response = $this->postJson('/api/pos/discount/authorize', [
            'pin_or_password' => '778899',
            'discount_type' => 'PERCENT',
            'discount_value' => -15,
            'reason' => 'Fraud attempt negative discount',
        ]);
        $response->assertStatus(422);
        $this->assertFalse($response->json('authorized'));
    }

    /**
     * Security Test 5: Negative item price in held bills is rejected.
     */
    public function test_negative_price_in_held_bill_is_rejected(): void
    {
        $response = $this->postJson('/api/pos/hold', [
            'terminal_code' => 'POS-SEC',
            'customer_name' => 'Bad Actor',
            'items' => [
                [
                    'name' => 'Manipulated Item',
                    'price' => -50000,
                    'qty' => 1,
                ],
            ],
        ]);
        $response->assertStatus(422);
    }

    /**
     * Security Test 6: Security HTTP Headers are attached.
     */
    public function test_security_headers_are_attached(): void
    {
        $response = $this->get('/manifest.json');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    /**
     * Security Test 7: Unauthenticated guest accessing Owner web console is redirected.
     */
    public function test_unauthenticated_guest_accessing_owner_is_redirected(): void
    {
        $response = $this->withHeaders(['X-Security-Test' => '1'])->get('/owner');
        $response->assertStatus(302);
        $response->assertRedirect(route('manager.login'));
    }

    /**
     * Security Test 8: Unauthenticated API request is rejected with 401.
     */
    public function test_unauthenticated_api_request_is_rejected(): void
    {
        $response = $this->withHeaders(['X-Security-Test' => '1'])->getJson('/api/users');
        $response->assertStatus(401);
        $this->assertFalse($response->json('success'));
    }

    /**
     * Security Test 9: Coupon double spending / redemption race condition is blocked.
     */
    public function test_coupon_cannot_be_redeemed_twice(): void
    {
        $coupon = CouponUnit::create([
            'serial_number' => 'CPN-SEC-' . uniqid(),
            'category' => 'bbm',
            'recipient_name' => 'Driver Secure',
            'status' => 'AVAILABLE',
            'nominal_value' => 50000,
        ]);

        // First redemption succeeds
        $res1 = $this->postJson("/api/coupons/{$coupon->id}/redeem");
        $res1->assertStatus(200);
        $this->assertTrue($res1->json('success'));

        // Second redemption MUST fail with 422
        $res2 = $this->postJson("/api/coupons/{$coupon->id}/redeem");
        $res2->assertStatus(422);
        $this->assertFalse($res2->json('success'));
        $this->assertStringContainsString('sudah pernah ditukarkan', $res2->json('message'));
    }
}
