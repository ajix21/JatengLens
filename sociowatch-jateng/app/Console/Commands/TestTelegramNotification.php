<?php

namespace App\Console\Commands;

use App\Services\TelegramAlertService;
use Illuminate\Console\Command;

class TestTelegramNotification extends Command
{
    protected $signature = 'telegram:test';
    protected $description = 'Kirim pesan uji coba ke Telegram untuk memastikan TELEGRAM_BOT_TOKEN & TELEGRAM_CHAT_ID aktif.';

    public function handle(TelegramAlertService $telegram): int
    {
        if (! $telegram->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN atau TELEGRAM_CHAT_ID belum diisi di file .env.');
            return self::FAILURE;
        }

        $ok = $telegram->sendMessage(
            "✅ <b>SocioWatch Jateng</b>\nKoneksi notifikasi Telegram aktif dan siap menerima peringatan postingan sensitif serta ringkasan AI Briefing harian."
        );

        if ($ok) {
            $this->info('Pesan uji coba berhasil dikirim ke Telegram!');
            return self::SUCCESS;
        }

        $this->error('Gagal mengirim pesan ke Telegram: ' . ($telegram->lastError() ?: 'Periksa kembali TELEGRAM_BOT_TOKEN dan TELEGRAM_CHAT_ID.'));
        return self::FAILURE;
    }
}
