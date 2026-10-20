<?php

namespace App\Services\Anpr;

class PlprResponseNormalizer
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function normalize(array $raw): ?array
    {
        if (isset($raw['error'])) {
            return null;
        }

        if (! empty($raw['plates']) && is_array($raw['plates'])) {
            if ($raw['plates'] === []) {
                return null;
            }

            $plate = $raw['plates'][0];
            if (! is_array($plate)) {
                return null;
            }

            $text = (string) ($plate['persian_text'] ?? $plate['clean_text'] ?? $plate['label'] ?? $plate['raw_text'] ?? '');

            return [
                'plate' => $text,
                'confidence' => (float) ($plate['plate_conf'] ?? $plate['char_conf_avg'] ?? 0),
                'parts' => $this->parseParts($text),
                'plpr' => $plate,
            ];
        }

        if (! empty($raw['plate']) || ! empty($raw['parts'])) {
            return $raw;
        }

        return null;
    }

    /** @return array{two:?string,letter:?string,three:?string,province:?string} */
    private function parseParts(string $text): array
    {
        $text = preg_replace('/\s+/u', '', $text) ?? $text;
        $text = str_replace(['-', 'ـ', 'ایران'], '', $text);

        if (preg_match('/(\d{2})([^\d])(\d{3})(\d{2})?/u', $text, $m)) {
            return [
                'two' => $m[1],
                'letter' => $m[2],
                'three' => $m[3],
                'province' => $m[4] ?? null,
            ];
        }

        return ['two' => null, 'letter' => null, 'three' => null, 'province' => null];
    }
}
