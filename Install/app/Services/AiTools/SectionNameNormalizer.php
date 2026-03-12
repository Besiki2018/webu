<?php

namespace App\Services\AiTools;

class SectionNameNormalizer
{
    public function normalize(string $name): string
    {
        $tokens = $this->tokens($name);
        if ($tokens === []) {
            return '';
        }

        $lastToken = strtolower((string) end($tokens));
        if ($lastToken === 'section') {
            array_pop($tokens);
        }

        if ($tokens === []) {
            return '';
        }

        $normalized = implode('', array_map(fn (string $token): string => $this->formatToken($token), $tokens));

        return $normalized.'Section';
    }

    public function humanize(string $name): string
    {
        $tokens = $this->tokens($name);
        if ($tokens === []) {
            return '';
        }

        $lastToken = strtolower((string) end($tokens));
        if ($lastToken === 'section') {
            array_pop($tokens);
        }

        return implode(' ', array_map(fn (string $token): string => $this->formatToken($token), $tokens));
    }

    public function normalizeKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $this->normalize($name)) ?? '');
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return [];
        }

        $spaced = preg_replace('/(?<=\p{Ll}|\d)(\p{Lu})/u', ' $1', $trimmed) ?? $trimmed;
        $parts = preg_split('/[^a-zA-Z0-9]+/', $spaced, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map(static fn (string $part): string => trim($part), $parts)));
    }

    private function formatToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        if (preg_match('/^[A-Z0-9]{2,4}$/', $token) === 1) {
            return strtoupper($token);
        }

        return ucfirst(strtolower($token));
    }
}
