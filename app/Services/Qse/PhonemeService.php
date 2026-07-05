<?php

namespace App\Services\Qse;

use App\Models\Phoneme;
use Illuminate\Support\Facades\Cache;

/**
 * LAPISAN 1 — PHONEME (Manifest Bagian V)
 *
 * Mendekomposisi kata Uthmani menjadi huruf dasar → data fonetik.
 * PENTING (§5): karakter bunyi BUKAN makna kata dan BUKAN makna ayat.
 * Disclaimer itu dikembalikan bersama data, bukan hanya di UI.
 */
class PhonemeService
{
    /** Harakat & tanda baca yang dibuang untuk mendapat huruf dasar. */
    private const DIACRITICS_PATTERN =
        '/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06DC}\x{06DF}-\x{06E8}\x{06EA}-\x{06ED}\x{0640}]/u';

    /** Normalisasi varian alif ke bentuk dasar. */
    private const ALIF_MAP = [
        "\u{0671}" => "\u{0627}", // alif wasla
        "\u{0622}" => "\u{0627}", // alif madda
        "\u{0623}" => "\u{0627}", // alif hamza atas
        "\u{0625}" => "\u{0627}", // alif hamza bawah
    ];

    /** @return array{letters: array, disclaimer: string} */
    public function decompose(string $textUthmani): array
    {
        $bare = preg_replace(self::DIACRITICS_PATTERN, '', $textUthmani);
        $bare = strtr($bare, self::ALIF_MAP);

        $map = $this->phonemeMap();
        $letters = [];
        foreach (mb_str_split($bare) as $ch) {
            if (isset($map[$ch])) {
                $letters[] = $map[$ch];
            }
            // Karakter di luar 28 huruf (mis. ta marbutah, alif maqsurah) dilewati;
            // bisa ditambahkan ke master phonemes jika dibutuhkan.
        }

        return [
            'letters'    => $letters,
            'disclaimer' => 'Karakter bunyi adalah observasi fonetik. '
                . 'BUKAN makna kata dan BUKAN makna ayat (Manifest §5).',
        ];
    }

    private function phonemeMap(): array
    {
        return Cache::rememberForever('qse.phoneme_map', function () {
            return Phoneme::all()->keyBy('letter')->map(fn ($p) => [
                'letter'         => $p->letter,
                'letter_name'    => $p->letter_name,
                'ipa'            => $p->ipa,
                'makhraj'        => $p->makhraj,
                'sifat'          => $p->sifat,
                'character_desc' => $p->character_desc,
            ])->all();
        });
    }
}
