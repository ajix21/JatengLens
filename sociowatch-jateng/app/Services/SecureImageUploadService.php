<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecureImageUploadService
{
    public const MAX_FILE_BYTES = 2 * 1024 * 1024; // 2 MB
    public const MIN_DIMENSION = 50;
    public const MAX_DIMENSION = 4096;
    public const MAX_PIXELS = 16_777_216; // 4096 x 4096 (anti decompression bomb)
    public const MAX_OUTPUT_DIMENSION = 1200;

    private const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    private const ALLOWED_IMAGETYPES = [
        IMAGETYPE_JPEG,
        IMAGETYPE_PNG,
        IMAGETYPE_WEBP,
    ];

    /**
     * Pola payload berbahaya yang sering disisipkan ke metadata/EXIF/trailing bytes gambar
     * (webshell PHP, XSS/HTML, PHAR polyglot, atau binary executable).
     */
    private const MALICIOUS_PATTERNS = [
        '/<\?php/i',
        '/<\?=/i',
        '/<script\b/i',
        '/<\/script>/i',
        '/<iframe\b/i',
        '/\b__HALT_COMPILER\s*\(/i',
        '/\b(eval|base64_decode|shell_exec|passthru|proc_open|popen|system|exec|assert|create_function)\s*\(/i',
        '/\x7fELF/',       // Linux ELF binary header
        '/MZ\x90\x00/',    // Windows PE/EXE header
    ];

    /**
     * Validasi ketat, scan payload berbahaya, dan re-encode gambar dari nol menggunakan GD
     * sehingga seluruh metadata EXIF/XMP/comment dan byte polyglot terbuang sepenuhnya.
     *
     * @throws ValidationException
     */
    public function storeSafeImage(UploadedFile $file, string $directory = 'admin-photos', string $field = 'photo'): string
    {
        if (!$file->isValid()) {
            $this->fail($field, 'File upload gagal atau rusak.');
        }

        $realPath = $file->getRealPath();
        if (!$realPath || !is_readable($realPath)) {
            $this->fail($field, 'File tidak dapat dibaca oleh server.');
        }

        // 1. Validasi ukuran file aktual (bukan hanya header client)
        $size = filesize($realPath);
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            $this->fail($field, 'Ukuran foto tidak boleh melebihi 2 MB.');
        }

        // 2. Validasi Magic Bytes / Real MIME menggunakan fileinfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($realPath);
        if (!$detectedMime || !in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            $this->fail($field, 'File bukan gambar valid. Hanya format JPG, PNG, atau WEBP yang diizinkan.');
        }

        // 3. Validasi struktur gambar & anti decompression bomb
        $imageInfo = @getimagesize($realPath);
        if ($imageInfo === false) {
            $this->fail($field, 'Struktur file gambar tidak valid atau rusak.');
        }

        [$width, $height, $imageType] = $imageInfo;
        if (!in_array($imageType, self::ALLOWED_IMAGETYPES, true)) {
            $this->fail($field, 'Tipe gambar tidak diizinkan. Gunakan JPG, PNG, atau WEBP.');
        }

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            $this->fail($field, 'Dimensi foto terlalu kecil (minimal 50×50 piksel).');
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > self::MAX_PIXELS) {
            $this->fail($field, 'Dimensi foto terlalu besar (maksimal 4096×4096 piksel).');
        }

        // 4. Scan payload berbahaya (webshell PHP, script, PHAR polyglot, binary executable)
        $rawBytes = file_get_contents($realPath);
        if ($rawBytes === false) {
            $this->fail($field, 'Gagal membaca konten file untuk pemeriksaan keamanan.');
        }

        foreach (self::MALICIOUS_PATTERNS as $pattern) {
            if (preg_match($pattern, $rawBytes) === 1) {
                $this->fail($field, 'File ditolak karena terdeteksi konten berbahaya / skrip tersembunyi.');
            }
        }

        // 5. Re-encode gambar dari nol dengan GD (membuang seluruh EXIF, XMP, comment, & trailing bytes)
        [$cleanBytes, $extension] = $this->reencodeCleanImage($realPath, $imageType, $width, $height, $field);

        // 6. Simpan dengan nama UUID acak + ekstensi tetap (anti path traversal & double extension)
        $filename = trim($directory, '/') . '/' . Str::uuid()->toString() . '.' . $extension;
        Storage::disk('public')->put($filename, $cleanBytes);

        return $filename;
    }

    /**
     * Decode piksel dengan GD, resize aman jika melebihi batas, lalu encode ulang ke byte bersih.
     *
     * @return array{0: string, 1: string} [binaryBytes, extension]
     * @throws ValidationException
     */
    private function reencodeCleanImage(string $realPath, int $imageType, int $width, int $height, string $field): array
    {
        if (!extension_loaded('gd')) {
            $this->fail($field, 'Ekstensi GD server tidak aktif untuk sanitasi gambar.');
        }

        $source = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($realPath),
            IMAGETYPE_PNG  => @imagecreatefrompng($realPath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($realPath) : false,
            default        => false,
        };

        if (!$source) {
            $this->fail($field, 'Gambar tidak dapat diproses ulang (kemungkinan rusak atau dimodifikasi).');
        }

        // Hitung dimensi output (maks 1200px sisi terpanjang, jaga rasio aspek)
        $targetWidth = $width;
        $targetHeight = $height;
        if ($width > self::MAX_OUTPUT_DIMENSION || $height > self::MAX_OUTPUT_DIMENSION) {
            $ratio = min(self::MAX_OUTPUT_DIMENSION / $width, self::MAX_OUTPUT_DIMENSION / $height);
            $targetWidth = max(1, (int) round($width * $ratio));
            $targetHeight = max(1, (int) round($height * $ratio));
        }

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        if (!$canvas) {
            imagedestroy($source);
            $this->fail($field, 'Gagal memproses kanvas gambar.');
        }

        $isPng = ($imageType === IMAGETYPE_PNG);
        if ($isPng) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $transparent);
        } else {
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
        }

        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        imagedestroy($source);

        ob_start();
        if ($isPng) {
            $ok = imagepng($canvas, null, 6);
            $extension = 'png';
        } else {
            $ok = imagejpeg($canvas, null, 85);
            $extension = 'jpg';
        }
        $cleanBytes = ob_get_clean();
        imagedestroy($canvas);

        if (!$ok || !is_string($cleanBytes) || $cleanBytes === '') {
            $this->fail($field, 'Gagal menghasilkan file gambar bersih.');
        }

        return [$cleanBytes, $extension];
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => [$message],
        ]);
    }
}
