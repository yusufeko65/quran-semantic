<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SPEC-ADMIN-01 §2 — kurasi halaman Pembukaan (model "terkunci + terkurasi").
 * `ref_a`/`ref_b` disimpan sbg string ringkas ("1:6-7", "4:69") — bukan
 * ID ayat — supaya bisa dibaca manusia langsung di form kurator. Teks
 * Arab & terjemahan TIDAK disimpan di sini, selalu ditarik ulang dari
 * `ayahs`/`translations` saat render (lihat parseRef()) — satu sumber
 * kebenaran (K13), bukan disalin/bisa basi.
 */
class PembukaanExample extends Model
{
    protected $fillable = [
        'is_locked', 'ref_a', 'ref_b', 'caption_a', 'caption_b',
        'is_current', 'sort_order', 'created_by', 'promoted_by', 'promoted_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'is_current' => 'boolean',
        'promoted_at' => 'datetime',
    ];

    /**
     * Uraikan ref ringkas ("surah:nomor" atau "surah:awal-akhir") jadi
     * [surahId, array nomor ayat]. Contoh: "23:1-11" -> [23, [1..11]].
     * Melempar InvalidArgumentException kalau formatnya tidak cocok —
     * dipakai baik di form kurator (validasi) maupun render publik.
     */
    public static function parseRef(string $ref): array
    {
        if (!preg_match('/^(\d+):(\d+)(?:-(\d+))?$/', trim($ref), $m)) {
            throw new \InvalidArgumentException("Format referensi ayat tidak valid: \"{$ref}\" (contoh benar: \"1:6-7\" atau \"4:69\")");
        }

        $surahId = (int) $m[1];
        $start = (int) $m[2];
        $end = isset($m[3]) && $m[3] !== '' ? (int) $m[3] : $start;

        if ($end < $start) {
            throw new \InvalidArgumentException("Referensi ayat \"{$ref}\": akhir rentang tidak boleh sebelum awal.");
        }

        return [$surahId, range($start, $end)];
    }
}
