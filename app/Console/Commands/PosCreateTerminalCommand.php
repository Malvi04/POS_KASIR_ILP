<?php

namespace App\Console\Commands;

use App\Models\Location;
use App\Models\PosTerminal;
use App\Services\LocationStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PosCreateTerminalCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:create-terminal
                            {--branch= : Kode, UUID, atau nama cabang toko (contoh: PST, CBG1, CBG2, CAB-001)}
                            {--code= : Kode terminal kasir (contoh: KSR01, KSR02)}
                            {--name= : Nama deskriptif terminal kasir}
                            {--rotate : Regenerasi token baru jika pasangan cabang + terminal sudah terdaftar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daftarkan PC Kasir (PosTerminal) di Server Pusat dan cetak plain API token satu kali untuk .env PC kasir';

    /**
     * Execute the console command.
     */
    public function handle(LocationStockService $stockService): int
    {
        $stockService->ensureDefaultLocations();

        $branchInput = trim((string) $this->option('branch'));
        if ($branchInput === '' && $this->input->isInteractive()) {
            $available = Location::where('type', '!=', 'WAREHOUSE')
                ->orderBy('id')
                ->get()
                ->map(fn (Location $l) => "{$l->code} ({$l->name})")
                ->implode(', ');
            $branchInput = trim((string) $this->ask("Masukkan kode/nama/UUID cabang toko [{$available}]", 'PST'));
        }

        if ($branchInput === '') {
            $this->error('Parameter --branch wajib diisi (contoh: --branch=PST, --branch=CBG1, --branch=CBG2).');
            return self::FAILURE;
        }

        // Resolve Location by UUID first, then via LocationStockService alias/code/name
        $location = null;
        if (Str::isUuid($branchInput)) {
            $location = Location::where('uuid', $branchInput)->first();
        }
        if (!$location) {
            $location = $stockService->resolveLocation(null, $branchInput, null, null, null);
        }

        if (!$location) {
            $this->error("Cabang/lokasi [{$branchInput}] tidak ditemukan di tabel locations.");
            return self::FAILURE;
        }

        if (strtoupper((string) $location->type) === 'WAREHOUSE') {
            $this->error("Lokasi [{$location->code} - {$location->name}] bertipe WAREHOUSE (Gudang). Terminal kasir hanya boleh didaftarkan pada lokasi toko (STORE).");
            return self::FAILURE;
        }

        $codeInput = strtoupper(trim((string) $this->option('code')));
        if ($codeInput === '' && $this->input->isInteractive()) {
            $codeInput = strtoupper(trim((string) $this->ask('Masukkan kode terminal kasir (contoh: KSR01, KSR02)', 'KSR01')));
        }
        $codeInput = preg_replace('/[^A-Z0-9\-_]/', '', $codeInput) ?: '';

        if ($codeInput === '') {
            $this->error('Parameter --code wajib diisi (contoh: --code=KSR01).');
            return self::FAILURE;
        }

        $canonicalBranchCode = PosTerminal::canonicalBranchCode($location);
        $defaultName = "Kasir {$codeInput} - {$location->name}";
        $terminalName = trim((string) $this->option('name'));
        if ($terminalName === '') {
            $terminalName = $defaultName;
        }

        $existing = PosTerminal::where('branch_id', $location->id)
            ->where('terminal_code', $codeInput)
            ->first();

        if ($existing && !$this->option('rotate')) {
            $this->error("Terminal [{$codeInput}] pada cabang [{$location->name}] sudah terdaftar (UUID: {$existing->uuid}). Gunakan opsi --rotate jika ingin men-generate ulang token baru.");
            return self::FAILURE;
        }

        $plainToken = PosTerminal::generatePlainToken($canonicalBranchCode, $codeInput);
        $tokenHash = PosTerminal::hashToken($plainToken);

        if ($existing) {
            $existing->update([
                'name' => $terminalName,
                'api_token_hash' => $tokenHash,
                'is_active' => true,
            ]);
            $terminal = $existing->fresh();
            $actionLabel = 'DIPERBARUI (ROTATED)';
        } else {
            $terminal = PosTerminal::create([
                'branch_id' => $location->id,
                'terminal_code' => $codeInput,
                'name' => $terminalName,
                'api_token_hash' => $tokenHash,
                'is_active' => true,
            ]);
            $actionLabel = 'BERHASIL DIDAFTARKAN';
        }

        $centralUrl = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');

        $this->newLine();
        $this->info("=== TERMINAL KASIR {$actionLabel} ===");
        $this->table(
            ['Atribut', 'Nilai'],
            [
                ['Terminal UUID', $terminal->uuid],
                ['Cabang (Location)', "{$location->name} (ID: {$location->id}, Code: {$location->code})"],
                ['Branch UUID', $location->uuid],
                ['Kode Cabang Struk', $canonicalBranchCode],
                ['Kode Terminal', $terminal->terminal_code],
                ['Nama Terminal', $terminal->name],
                ['Status Aktif', $terminal->is_active ? 'AKTIF' : 'NONAKTIF'],
            ]
        );

        $this->warn('PENTING: Salin Plain Token di bawah ini sekarang! Token asli TIDAK disimpan di database dan tidak akan ditampilkan lagi.');
        $this->line("Plain API Token: {$plainToken}");
        $this->newLine();
        $this->comment('# --- Salin konfigurasi berikut ke file .env pada PC Kasir Lokal ---');
        $this->line('POS_NODE_ROLE=local');
        $this->line("POS_BRANCH_UUID={$location->uuid}");
        $this->line("POS_BRANCH_CODE={$canonicalBranchCode}");
        $this->line("POS_TERMINAL_CODE={$terminal->terminal_code}");
        $this->line("CENTRAL_API_URL={$centralUrl}");
        $this->line("CENTRAL_API_TOKEN={$plainToken}");
        $this->newLine();

        return self::SUCCESS;
    }
}
