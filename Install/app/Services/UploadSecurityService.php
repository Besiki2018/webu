<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class UploadSecurityService
{
    /**
     * @var array<int, string>
     */
    private const BLOCKED_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'msi', 'bin', 'cmd', 'bat', 'com',
        'sh', 'bash', 'zsh', 'csh',
        'jsp', 'jspx', 'asp', 'aspx', 'cgi',
        'pl', 'py', 'rb',
        'jar',
    ];

    /**
     * @var array<int, string>
     */
    private const BLOCKED_MIME_PREFIXES = [
        'application/x-httpd-php',
        'text/x-php',
        'application/x-php',
        'application/x-msdownload',
        'application/x-executable',
        'text/x-shellscript',
        'application/x-sh',
    ];

    /**
     * @var array<int, string>
     */
    private const EXECUTABLE_SIGNATURES = [
        '<?php',
        '<script',
        '#!/bin/',
        '#!/usr/bin/',
    ];

    /**
     * @param  array<int, string>  $allowedMimePatterns
     * @return array{mime: string, extension: string}
     */
    public function assertSafeUpload(UploadedFile $file, array $allowedMimePatterns = []): array
    {
        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        if ($extension !== '' && in_array($extension, self::BLOCKED_EXTENSIONS, true)) {
            throw new \RuntimeException("Blocked file extension [.{$extension}]");
        }

        $mime = $this->detectMimeType($file);
        foreach (self::BLOCKED_MIME_PREFIXES as $blockedPrefix) {
            if (str_starts_with($mime, $blockedPrefix)) {
                throw new \RuntimeException("Blocked file MIME type [{$mime}]");
            }
        }

        if ($allowedMimePatterns !== [] && ! $this->matchesAnyMimePattern($mime, $allowedMimePatterns)) {
            throw new \RuntimeException("File MIME type [{$mime}] is not allowed.");
        }

        if ($this->containsExecutableSignature($file)) {
            throw new \RuntimeException('Executable/script signature detected in uploaded file.');
        }

        return [
            'mime' => $mime,
            'extension' => $extension,
        ];
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

        return $mime !== '' ? $mime : 'application/octet-stream';
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function matchesAnyMimePattern(string $mime, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            $normalized = strtolower(trim($pattern));
            if ($normalized === '') {
                continue;
            }

            if ($normalized === $mime) {
                return true;
            }

            if (str_ends_with($normalized, '/*')) {
                $prefix = substr($normalized, 0, -1);
                if ($prefix !== '' && str_starts_with($mime, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function containsExecutableSignature(UploadedFile $file): bool
    {
        $realPath = $file->getRealPath();
        if (! is_string($realPath) || $realPath === '' || ! is_file($realPath)) {
            return false;
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            return false;
        }

        $sample = fread($handle, 2048);
        fclose($handle);

        if (! is_string($sample) || $sample === '') {
            return false;
        }

        $normalized = strtolower($sample);
        foreach (self::EXECUTABLE_SIGNATURES as $signature) {
            if (str_contains($normalized, strtolower($signature))) {
                return true;
            }
        }

        return false;
    }
}
