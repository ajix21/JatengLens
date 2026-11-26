<?php

namespace App\Console\Commands;

use App\Enums\FetchStatus;
use App\Models\FetchLog;
use App\Services\SocialFetch\SocialFetchService;
use Illuminate\Console\Command;

class SocialFetch extends Command
{
    protected $signature = 'social:fetch
        {--platform= : Batasi ke satu platform (youtube|instagram)}
        {--account= : Batasi ke satu ID akun}
        {--batch= : Nomor batch (1..N) untuk membagi kuota API}
        {--batches=1 : Total jumlah batch (mis. 2)}
        {--dry-run : Hitung saja tanpa menyimpan}';

    protected $description = 'Ambil data akun dan postingan YouTube/Instagram secara otomatis';

    public function handle(SocialFetchService $service): int
    {
        $platform = $this->option('platform') ?: null;
        $account = $this->option('account');
        $batchOpt = $this->option('batch');
        $batch = ($batchOpt !== null && $batchOpt !== '') ? max(1, (int) $batchOpt) : null;
        $batches = max(1, (int) ($this->option('batches') ?: 1));
        $dryRun = (bool) $this->option('dry-run');

        $results = $service->fetchAll(
            $platform,
            $account !== null && $account !== '' ? (int) $account : null,
            $dryRun,
            $batch,
            $batches
        );

        foreach ($service->skippedPlatforms() as $skipped) {
            $this->line("Lewati {$skipped}: kredensial belum diatur");
        }

        foreach ($results as $r) {
            $line = "[{$r->status->value}] {$r->account->platform} @{$r->account->username} — {$r->newPosts} baru, {$r->updatedPosts} diperbarui";
            if ($r->message !== null) {
                $line .= " — {$r->message}";
            }
            $this->line($line);
        }

        $ok = $results->filter(fn ($r) => $r->status === FetchStatus::Ok)->count();
        $this->info('Selesai: ' . $ok . ' berhasil, ' . ($results->count() - $ok) . ' gagal');

        if (! $dryRun) {
            FetchLog::where('fetched_at', '<', now()->subDays(90))->delete();
        }

        return self::SUCCESS;
    }
}
