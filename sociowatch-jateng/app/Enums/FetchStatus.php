<?php

namespace App\Enums;

enum FetchStatus: string
{
    case Ok = 'ok';
    case NotFound = 'not_found';
    case Unsupported = 'unsupported';
    case AuthError = 'auth_error';
    case Quota = 'quota';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Berhasil',
            self::NotFound => 'Akun tidak ditemukan',
            self::Unsupported => 'Tidak didukung — isi manual',
            self::AuthError => 'Kredensial ditolak',
            self::Quota => 'Kuota habis',
            self::Error => 'Error',
        };
    }

    public function isPlatformFatal(): bool
    {
        return in_array($this, [self::AuthError, self::Quota], true);
    }
}
