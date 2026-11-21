<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Promo Engine (server-side, otoritatif) ala minimarket Alfamart.
 *
 * Logika ini dicerminkan 1:1 oleh mesin promo JavaScript di layar POS kasir
 * (resources/views/kasir/pos.blade.php -> applyPromotions()) agar tampilan
 * keranjang selalu sama dengan hasil hitung server saat checkout.
 *
 * Aturan umum:
 * - 1 baris produk hanya mendapat 1 promo (promo dengan potongan terbesar menang).
 * - Produk clearance (near-expired) tidak digabung dengan promo lain.
 * - Urutan evaluasi: promo per-item -> Tebus Murah (PWP) -> Paket Bundling.
 */
class PromotionEngine
{
    public const ITEM_TYPES = ['PERCENTAGE', 'FIXED', 'SPECIAL_PRICE', 'BOGO', 'TIERED'];

    public static function defaultSettings(): array
    {
        return [
            'h7Percent' => 40,
            'h3Percent' => 70,
            'autoPrint' => true,
            'promoEngineEnabled' => true,
            'allowCashierToggle' => true,
        ];
    }

    public static function settings(): array
    {
        $setting = AppSetting::where('key', 'promo_settings')->first();
        $data = $setting ? (json_decode($setting->value, true) ?: []) : [];
        return array_merge(self::defaultSettings(), $data);
    }

    /**
     * Promo yang sedang berjalan saat ini.
     */
    public function runningPromotions(?Carbon $now = null): Collection
    {
        return Promotion::where('status', 'AKTIF')
            ->orderBy('id')
            ->get()
            ->filter(fn (Promotion $p) => $p->isRunningAt($now))
            ->values();
    }

    /**
     * Payload promo aktif untuk layar kasir (termasuk SKU hasil resolve product_ids).
     */
    public function activePayloadForPos(): array
    {
        $settings = self::settings();
        $promos = $settings['promoEngineEnabled'] ? $this->runningPromotions() : collect();

        $allIds = [];
        foreach ($promos as $p) {
            $allIds = array_merge($allIds, array_map('intval', (array) ($p->product_ids ?? [])));
            if ($p->reward_product_id) {
                $allIds[] = (int) $p->reward_product_id;
            }
        }
        $productMap = Product::whereIn('id', array_unique($allIds))->get(['id', 'sku', 'name', 'price', 'member_price', 'barcode'])->keyBy('id');

        return [
            'engine_enabled' => (bool) $settings['promoEngineEnabled'],
            'allow_cashier_toggle' => (bool) $settings['allowCashierToggle'],
            'server_time' => now()->toIso8601String(),
            'promotions' => $promos->map(function (Promotion $p) use ($productMap) {
                $ids = array_map('intval', (array) ($p->product_ids ?? []));
                $reward = $p->reward_product_id ? $productMap->get($p->reward_product_id) : null;
                return [
                    'id' => $p->id,
                    'code' => $p->code,
                    'name' => $p->name,
                    'condition' => $p->condition,
                    'type' => $p->type,
                    'value' => (float) $p->value,
                    'scope' => $p->scope ?: 'ALL',
                    'product_ids' => $ids,
                    // Sejajar dengan product_ids (index sama) agar kasir bisa mencocokkan via SKU
                    'product_skus' => collect($ids)->map(fn ($id) => $productMap->get($id)?->sku)->values()->all(),
                    'categories' => array_values((array) ($p->categories ?? [])),
                    'buy_qty' => $p->buy_qty,
                    'get_qty' => $p->get_qty,
                    'min_qty' => $p->min_qty,
                    'min_purchase' => $p->min_purchase,
                    'max_qty_per_trx' => $p->max_qty_per_trx,
                    'reward_product_id' => $p->reward_product_id,
                    'reward_product' => $reward ? [
                        'id' => $reward->id,
                        'sku' => $reward->sku,
                        'barcode' => $reward->barcode,
                        'name' => $reward->name,
                        'price' => (float) $reward->price,
                    ] : null,
                    'excluded_categories' => array_values((array) ($p->excluded_categories ?? [])),
                    'days_of_week' => array_values((array) ($p->days_of_week ?? [])),
                    'time_start' => $p->time_start,
                    'time_end' => $p->time_end,
                    'member_only' => (bool) $p->member_only,
                    'period_end' => $p->period_end?->toDateString(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * Hitung promo untuk seluruh baris keranjang.
     *
     * @param array $lines Setiap baris: ['product_id','sku','category','unit_price','qty','is_clearance','promo_disabled']
     * @return array Baris yang sama + ['promotion_id','promo_name','promo_discount']
     */
    public function apply(array $lines, bool $isMember, bool $promoEnabled = true): array
    {
        foreach ($lines as $i => $line) {
            $lines[$i]['promotion_id'] = null;
            $lines[$i]['promo_name'] = null;
            $lines[$i]['promo_discount'] = 0.0;
        }

        $settings = self::settings();
        if (!$settings['promoEngineEnabled'] || !$promoEnabled || empty($lines)) {
            return $lines;
        }

        $promos = $this->runningPromotions()->filter(fn (Promotion $p) => !$p->member_only || $isMember)->values();
        if ($promos->isEmpty()) {
            return $lines;
        }

        $eligibleLine = fn (array $l) => !empty($l['product_id']) && empty($l['is_clearance']) && empty($l['promo_disabled']) && (int) $l['qty'] > 0;

        // 1. Promo per-item (pilih potongan terbesar)
        foreach ($lines as $i => $line) {
            if (!$eligibleLine($line)) {
                continue;
            }
            foreach ($promos as $promo) {
                if (!in_array($promo->type, self::ITEM_TYPES, true) || !$this->targets($promo, $line)) {
                    continue;
                }
                $disc = $this->itemDiscount($promo, (float) $line['unit_price'], (int) $line['qty']);
                if ($disc > $lines[$i]['promo_discount']) {
                    $lines[$i]['promo_discount'] = $disc;
                    $lines[$i]['promotion_id'] = $promo->id;
                    $lines[$i]['promo_name'] = $promo->name;
                }
            }
        }

        // 2. Tebus Murah (PWP)
        foreach ($promos->where('type', 'PWP') as $promo) {
            if (!$promo->reward_product_id) {
                continue;
            }
            foreach ($lines as $i => $line) {
                if (!$eligibleLine($line) || (int) $line['product_id'] !== (int) $promo->reward_product_id) {
                    continue;
                }
                $excluded = array_map('mb_strtolower', (array) ($promo->excluded_categories ?? []));
                $base = 0.0;
                foreach ($lines as $j => $other) {
                    if ($j === $i) {
                        continue;
                    }
                    if (in_array(mb_strtolower((string) ($other['category'] ?? '')), $excluded, true)) {
                        continue;
                    }
                    $base += ((float) $other['unit_price'] * (int) $other['qty']) - (float) $lines[$j]['promo_discount'];
                }
                if ($base < (float) ($promo->min_purchase ?? 0)) {
                    continue;
                }
                $units = min((int) $line['qty'], max(1, (int) ($promo->max_qty_per_trx ?: 1)));
                $disc = round(max(0, (float) $line['unit_price'] - (float) $promo->value) * $units);
                if ($disc > $lines[$i]['promo_discount']) {
                    $lines[$i]['promo_discount'] = $disc;
                    $lines[$i]['promotion_id'] = $promo->id;
                    $lines[$i]['promo_name'] = $promo->name;
                }
            }
        }

        // 3. Paket Bundling
        foreach ($promos->where('type', 'BUNDLE') as $promo) {
            $ids = array_values(array_unique(array_map('intval', (array) ($promo->product_ids ?? []))));
            if (count($ids) < 2) {
                continue;
            }
            $members = [];
            foreach ($ids as $pid) {
                $found = null;
                foreach ($lines as $i => $line) {
                    if ($eligibleLine($line) && (int) $line['product_id'] === $pid && empty($lines[$i]['promotion_id'])) {
                        $found = $i;
                        break;
                    }
                }
                if ($found === null) {
                    $members = [];
                    break;
                }
                $members[] = $found;
            }
            if (empty($members)) {
                continue;
            }
            $sets = min(array_map(fn ($i) => (int) $lines[$i]['qty'], $members));
            if ($promo->max_qty_per_trx) {
                $sets = min($sets, (int) $promo->max_qty_per_trx);
            }
            $normal = array_sum(array_map(fn ($i) => (float) $lines[$i]['unit_price'], $members));
            $perSet = max(0, $normal - (float) $promo->value);
            if ($sets <= 0 || $perSet <= 0 || $normal <= 0) {
                continue;
            }
            $total = round($perSet * $sets);
            $allocated = 0.0;
            foreach ($members as $k => $i) {
                $share = $k === count($members) - 1
                    ? $total - $allocated
                    : round($total * ((float) $lines[$i]['unit_price'] / $normal));
                $allocated += $share;
                $lines[$i]['promo_discount'] = $share;
                $lines[$i]['promotion_id'] = $promo->id;
                $lines[$i]['promo_name'] = $promo->name;
            }
        }

        return $lines;
    }

    protected function targets(Promotion $promo, array $line): bool
    {
        $scope = strtoupper((string) ($promo->scope ?: 'ALL'));
        if ($scope === 'PRODUCTS') {
            return in_array((int) $line['product_id'], array_map('intval', (array) ($promo->product_ids ?? [])), true);
        }
        if ($scope === 'CATEGORY') {
            $cats = array_map('mb_strtolower', (array) ($promo->categories ?? []));
            return in_array(mb_strtolower((string) ($line['category'] ?? '')), $cats, true);
        }
        return true;
    }

    protected function itemDiscount(Promotion $promo, float $price, int $qty): float
    {
        $value = (float) $promo->value;
        $cap = $promo->max_qty_per_trx ? (int) $promo->max_qty_per_trx : null;
        $units = $cap ? min($qty, $cap) : $qty;
        $minQty = (int) ($promo->min_qty ?? 0);

        switch ($promo->type) {
            case 'PERCENTAGE':
                if ($minQty > 0 && $qty < $minQty) return 0;
                return round($price * min(100, $value) / 100) * $units;
            case 'FIXED':
                if ($minQty > 0 && $qty < $minQty) return 0;
                return round(min($value, $price)) * $units;
            case 'SPECIAL_PRICE':
                return round(max(0, $price - $value)) * $units;
            case 'TIERED':
                if ($qty < max(2, $minQty)) return 0;
                return round(max(0, $price - $value)) * $units;
            case 'BOGO':
                $buy = max(1, (int) ($promo->buy_qty ?: 1));
                $get = max(1, (int) ($promo->get_qty ?: 1));
                $free = intdiv($qty, $buy + $get) * $get;
                if ($cap) $free = min($free, $cap);
                return round($price) * $free;
        }
        return 0;
    }
}
