<?php

namespace App\Services;

use App\Cms\Exceptions\CmsDomainException;
use App\Models\Site;
use App\Models\SiteCustomFont;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SiteCustomFontService
{
    /**
     * @var array<string, array{format: string, mime_types: array<int, string>}>
     */
    private const ALLOWED_EXTENSIONS = [
        'woff2' => [
            'format' => 'woff2',
            'mime_types' => [
                'font/woff2',
                'application/font-woff2',
                'application/x-font-woff2',
                'application/octet-stream',
            ],
        ],
        'woff' => [
            'format' => 'woff',
            'mime_types' => [
                'font/woff',
                'application/font-woff',
                'application/x-font-woff',
                'application/octet-stream',
            ],
        ],
        'ttf' => [
            'format' => 'truetype',
            'mime_types' => [
                'font/ttf',
                'application/font-sfnt',
                'application/x-font-ttf',
                'application/x-font-truetype',
                'application/octet-stream',
            ],
        ],
        'otf' => [
            'format' => 'opentype',
            'mime_types' => [
                'font/otf',
                'application/font-sfnt',
                'application/x-font-otf',
                'application/octet-stream',
            ],
        ],
    ];

    /**
     * @return array<int, string>
     */
    public function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED_EXTENSIONS);
    }

    public function maxUploadKilobytes(User $user): int
    {
        $plan = $user->getCurrentPlan();
        if (! $plan || ! $plan->fileStorageEnabled()) {
            throw new CmsDomainException('File storage is not enabled for your plan.', 403);
        }

        return max(1, $plan->getMaxFileSizeMb()) * 1024;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upload(Site $site, UploadedFile $file, User $user, array $payload = []): SiteCustomFont
    {
        $maxKb = $this->maxUploadKilobytes($user);
        $fileSize = (int) ($file->getSize() ?? 0);

        if ($fileSize <= 0) {
            throw new CmsDomainException('Uploaded font file is empty.', 422);
        }

        if ($fileSize > ($maxKb * 1024)) {
            throw new CmsDomainException('Uploaded file exceeds plan max file size.', 422);
        }

        $remaining = $user->getRemainingStorageBytes();
        if ($remaining !== -1 && $fileSize > $remaining) {
            throw new CmsDomainException('Storage quota exceeded. Upgrade your plan or remove files.', 422);
        }

        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        $format = $this->resolveFormatByExtension($extension);
        if ($format === null) {
            throw new CmsDomainException(
                sprintf('Unsupported font extension. Allowed: %s', implode(', ', $this->allowedExtensions())),
                422
            );
        }

        $detectedMime = $this->detectMimeType($file);
        $uploadedMime = strtolower(trim((string) ($file->getMimeType() ?: '')));
        $effectiveMime = $this->isMimeAllowedForExtension($extension, $detectedMime)
            ? $detectedMime
            : $uploadedMime;

        if (! $this->isMimeAllowedForExtension($extension, $effectiveMime)) {
            throw new CmsDomainException(
                sprintf('Detected MIME type [%s] is not allowed for .%s files.', $detectedMime, $extension),
                422
            );
        }

        $label = $this->normalizeLabel($payload['label'] ?? $file->getClientOriginalName());
        $fontFamily = $this->normalizeFamily($payload['font_family'] ?? $label);
        $baseKey = $this->normalizeFontKey($payload['font_key'] ?? $label);
        $fontKey = $this->resolveUniqueFontKey($site, $baseKey);
        $fontWeight = $this->normalizeWeight($payload['font_weight'] ?? 400);
        $fontStyle = $this->normalizeStyle($payload['font_style'] ?? 'normal');
        $fontDisplay = $this->normalizeDisplay($payload['font_display'] ?? 'swap');

        $filename = sprintf('%s-%s.%s', $fontKey, Str::uuid()->toString(), $extension);
        $path = $file->storeAs("site-fonts/{$site->id}", $filename, 'public');

        $font = SiteCustomFont::query()->create([
            'site_id' => $site->id,
            'key' => $fontKey,
            'label' => $label,
            'font_family' => $fontFamily,
            'storage_path' => $path,
            'mime' => $effectiveMime,
            'format' => $format,
            'size' => $fileSize,
            'font_weight' => $fontWeight,
            'font_style' => $fontStyle,
            'font_display' => $fontDisplay,
            'uploaded_by' => $user->id,
            'meta_json' => [
                'original_name' => $file->getClientOriginalName(),
                'extension' => $extension,
            ],
        ]);

        $site->project?->incrementStorageUsed($fileSize);

        return $font;
    }

    public function delete(Site $site, SiteCustomFont $font): void
    {
        if ((string) $font->site_id !== (string) $site->id) {
            throw (new ModelNotFoundException)->setModel(SiteCustomFont::class, [(string) $font->id]);
        }

        if (Storage::disk('public')->exists($font->storage_path)) {
            Storage::disk('public')->delete($font->storage_path);
        }

        $size = (int) $font->size;
        $font->delete();

        if ($size > 0) {
            $site->project?->decrementStorageUsed($size);
        }
    }

    public function storageUrl(SiteCustomFont $font): string
    {
        return Storage::disk('public')->url($font->storage_path);
    }

    private function resolveFormatByExtension(string $extension): ?string
    {
        return self::ALLOWED_EXTENSIONS[$extension]['format'] ?? null;
    }

    private function isMimeAllowedForExtension(string $extension, string $mime): bool
    {
        $allowed = self::ALLOWED_EXTENSIONS[$extension]['mime_types'] ?? [];

        return in_array($mime, $allowed, true);
    }

    private function detectMimeType(UploadedFile $file): string
    {
        $mime = strtolower(trim((string) ($file->getMimeType() ?: '')));
        $realPath = $file->getRealPath();

        if (is_string($realPath) && $realPath !== '' && is_file($realPath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $detected = finfo_file($finfo, $realPath);
                finfo_close($finfo);

                if (is_string($detected) && trim($detected) !== '') {
                    $mime = strtolower(trim($detected));
                }
            }
        }

        if ($mime === '') {
            return 'application/octet-stream';
        }

        return $mime;
    }

    private function normalizeLabel(mixed $value): string
    {
        $label = trim((string) $value);
        if ($label === '') {
            $label = 'Custom Font';
        }

        if (mb_strlen($label) > 120) {
            $label = mb_substr($label, 0, 120);
        }

        return $label;
    }

    private function normalizeFamily(mixed $value): string
    {
        $family = trim((string) $value);
        if ($family === '') {
            $family = 'Custom Font';
        }

        if (mb_strlen($family) > 120) {
            $family = mb_substr($family, 0, 120);
        }

        return $family;
    }

    private function normalizeFontKey(mixed $value): string
    {
        $raw = trim(strtolower((string) $value));
        $normalized = preg_replace('/[^a-z0-9_-]+/', '-', $raw) ?? '';
        $normalized = trim($normalized, '-_');

        if ($normalized === '') {
            $normalized = 'custom-font';
        }

        if (! preg_match('/^[a-z0-9]/', $normalized)) {
            $normalized = 'font-'.$normalized;
        }

        if (strlen($normalized) < 2) {
            $normalized .= '-font';
        }

        return substr($normalized, 0, 64);
    }

    private function resolveUniqueFontKey(Site $site, string $baseKey): string
    {
        if (! SiteCustomFont::query()->where('site_id', $site->id)->where('key', $baseKey)->exists()) {
            return $baseKey;
        }

        for ($suffix = 2; $suffix <= 999; $suffix++) {
            $candidate = substr(sprintf('%s-%d', $baseKey, $suffix), 0, 64);
            if (! SiteCustomFont::query()->where('site_id', $site->id)->where('key', $candidate)->exists()) {
                return $candidate;
            }
        }

        return substr(sprintf('%s-%s', $baseKey, Str::lower(Str::random(6))), 0, 64);
    }

    private function normalizeWeight(mixed $value): int
    {
        $weight = (int) $value;
        if (! in_array($weight, [100, 200, 300, 400, 500, 600, 700, 800, 900], true)) {
            return 400;
        }

        return $weight;
    }

    private function normalizeStyle(mixed $value): string
    {
        $style = strtolower(trim((string) $value));

        return in_array($style, ['normal', 'italic', 'oblique'], true) ? $style : 'normal';
    }

    private function normalizeDisplay(mixed $value): string
    {
        $display = strtolower(trim((string) $value));

        return in_array($display, ['auto', 'block', 'swap', 'fallback', 'optional'], true) ? $display : 'swap';
    }
}
