<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAdmin extends Model
{
    protected $fillable = [
        'social_account_id',
        'full_name',
        'photo',
        'alias',
        'nik',
        'no_kk',
        'status',
        'agama',
        'ttl',
        'occupation',
        'alamat',
        'phone',
        'email',
        'affiliation',
        'notes',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }
        if (preg_match('#^https?://#i', $this->photo) || str_starts_with($this->photo, '/storage/')) {
            return $this->photo;
        }

        return '/storage/' . ltrim($this->photo, '/');
    }

    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * Format ringkas data admin untuk kemudahan pelaporan WhatsApp.
     */
    public function toWhatsAppText(): string
    {
        $lines = [];
        $nama = $this->full_name . ($this->alias ? " (Alias: {$this->alias})" : '');
        $lines[] = "* Nama: " . ($nama ?: '-');
        $lines[] = "* NIK: " . ($this->nik ?: '-');
        $lines[] = "* No KK: " . ($this->no_kk ?: '-');
        $lines[] = "* Status: " . ($this->status ?: '-');
        $lines[] = "* Agama: " . ($this->agama ?: '-');
        $lines[] = "* TTL: " . ($this->ttl ?: '-');
        $lines[] = "* Pekerjaan: " . ($this->occupation ?: '-');
        $lines[] = "* Alamat: " . ($this->alamat ?: '-');
        if ($this->phone) {
            $lines[] = "* No HP/WA: " . $this->phone;
        }
        if ($this->affiliation) {
            $lines[] = "* Afiliasi: " . $this->affiliation;
        }
        $lines[] = "* Catatan: " . ($this->notes ?: '-');

        return implode("\n", $lines);
    }
}
