<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    /**
     * Tipe promo yang didukung Promo Engine (ala minimarket Alfamart):
     * - PERCENTAGE    : Diskon persentase (%) per unit
     * - FIXED         : Potongan nominal (Rp) per unit
     * - SPECIAL_PRICE : Harga Coret / Promo JSM (harga promo tetap)
     * - BOGO          : Beli X Gratis Y (produk sama)
     * - TIERED        : Beli Banyak Lebih Hemat (harga grosir mulai qty tertentu)
     * - BUNDLE        : Paket Bundling (beli kombinasi produk = harga paket)
     * - PWP           : Tebus Murah (min. belanja Rp X -> tebus produk Y seharga Rp Z)
     */
    public const TYPES = ['PERCENTAGE', 'FIXED', 'SPECIAL_PRICE', 'BOGO', 'TIERED', 'BUNDLE', 'PWP'];

    protected $fillable = [
        'code',
        'name',
        'condition',
        'type',
        'value',
        'badge_class',
        'period_start',
        'period_end',
        'status',
        'status_color',
        'scope',
        'product_ids',
        'categories',
        'buy_qty',
        'get_qty',
        'min_qty',
        'min_purchase',
        'max_qty_per_trx',
        'reward_product_id',
        'excluded_categories',
        'days_of_week',
        'time_start',
        'time_end',
        'member_only',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'product_ids' => 'array',
        'categories' => 'array',
        'excluded_categories' => 'array',
        'days_of_week' => 'array',
        'buy_qty' => 'integer',
        'get_qty' => 'integer',
        'min_qty' => 'integer',
        'min_purchase' => 'float',
        'max_qty_per_trx' => 'integer',
        'reward_product_id' => 'integer',
        'member_only' => 'boolean',
    ];

    public static function badgeClassFor(string $type): string
    {
        return match ($type) {
            'PERCENTAGE' => 'bg-[#dbeafe] text-[#1e40af]',
            'FIXED' => 'bg-[#e0e7ff] text-[#3730a3]',
            'SPECIAL_PRICE' => 'bg-[#fee2e2] text-[#991b1b]',
            'BOGO' => 'bg-[#ffedd5] text-[#9a3412]',
            'TIERED' => 'bg-[#dcfce7] text-[#166534]',
            'BUNDLE' => 'bg-[#f3e8ff] text-[#6b21a8]',
            'PWP' => 'bg-[#fef9c3] text-[#854d0e]',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /**
     * Promo sedang berlaku saat ini (status AKTIF + periode tanggal + hari + jam).
     */
    public function isRunningAt(?Carbon $now = null): bool
    {
        $now = $now ?: now();
        if (strtoupper((string) $this->status) !== 'AKTIF') {
            return false;
        }
        $today = $now->copy()->startOfDay();
        if ($this->period_start && $today->lt($this->period_start->copy()->startOfDay())) {
            return false;
        }
        if ($this->period_end && $today->gt($this->period_end->copy()->startOfDay())) {
            return false;
        }
        $days = array_filter((array) ($this->days_of_week ?? []));
        if (!empty($days) && !in_array((int) $now->isoWeekday(), array_map('intval', $days), true)) {
            return false;
        }
        $hm = $now->format('H:i');
        if ($this->time_start && $hm < $this->time_start) {
            return false;
        }
        if ($this->time_end && $hm > $this->time_end) {
            return false;
        }
        return true;
    }
}
