<?php

namespace App\Services\Qse;

use App\Models\Ayah;

/**
 * TAJWEED — pemetaan anotasi per-AYAT (sumber kebenaran) ke segmen per-KATA
 * (bentuk yang dibutuhkan UI untuk merender mushaf per <span> kata).
 *
 * KENAPA TIDAK DISIMPAN PER-KATA SEJAK AWAL:
 * 16,75% anotasi (9.979 dari 59.563) MELINTASI BATAS KATA — dan itu benar
 * secara ilmu tajwid: idgham/ikhfa/iqlab justru terjadi ketika nun sukun di
 * akhir kata bertemu huruf di awal kata berikutnya. Menyimpan per-kata akan
 * memaksa membuang atau memotong sewenang-wenang aturan lintas-kata.
 *
 * Maka: sumber kebenaran tetap ayahs.text_tajweed (offset codepoint terhadap
 * text_uthmani ayat, auditable, tak kehilangan informasi). Service ini
 * MENURUNKAN bentuk per-kata, memecah anotasi lintas-kata menjadi beberapa
 * segmen yang saling tertaut lewat `group_id` + `is_start`/`is_end`, sehingga
 * UI dapat mewarnai kontinu tanpa kehilangan satu aturan pun.
 *
 * Kontrak segmen per kata (offset RELATIF terhadap word.text_uthmani):
 *   { rule, start, end, group_id, is_start, is_end, spans_words }
 *
 * Prasyarat: implode(' ', words.text_uthmani) === ayahs.text_uthmani.
 * Terpenuhi pada 6.227/6.236 ayat. NEUF ayat (2:181, 8:6, 13:37, 15:7, 27:20,
 * 36:22, 37:130, 37:164, 41:47) tokenisasinya tak selaras Tanzil↔QAC — sudah
 * ter-flag di data_flags sejak ETL. Untuk ayat itu service mengembalikan array
 * kosong + isPerWordAvailable()=false, sehingga UI merender tanpa warna per-kata
 * (jujur kosong, §3) alih-alih mewarnai huruf yang salah.
 */
class TajweedService
{
    /** Apakah anotasi per-kata dapat diturunkan dengan tepat untuk ayat ini? */
    public function isPerWordAvailable(Ayah $ayah): bool
    {
        if (empty($ayah->text_tajweed)) {
            return false;
        }
        $ayah->loadMissing('words');
        $joined = $ayah->words->pluck('text_uthmani')->implode(' ');
        return $joined === $ayah->text_uthmani;
    }

    /**
     * @return array<int, array<int, array>> word_id => daftar segmen
     */
    public function segmentsPerWord(Ayah $ayah): array
    {
        $annotations = $ayah->text_tajweed;
        if (empty($annotations)) {
            return []; // ayat tanpa tajwid → UI render tanpa warna (jujur kosong)
        }

        $ayah->loadMissing('words');

        // Guard: tanpa rekonstruksi tepat, offset per-kata tidak dapat dipercaya.
        if (!$this->isPerWordAvailable($ayah)) {
            return [];
        }

        // Bentangan codepoint tiap kata di dalam teks ayat (dipisah 1 spasi).
        $spans = [];
        $offset = 0;
        foreach ($ayah->words as $w) {
            $len = mb_strlen($w->text_uthmani);
            $spans[] = ['word_id' => $w->id, 'start' => $offset, 'end' => $offset + $len];
            $offset += $len + 1; // +1 untuk spasi pemisah
        }

        $result = [];
        foreach ($annotations as $i => $ann) {
            $groupId = $i; // identitas anotasi asal (menautkan pecahan lintas-kata)

            // Kata mana saja yang bersinggungan dengan [start, end)
            $touched = array_values(array_filter(
                $spans,
                fn ($s) => $ann['start'] < $s['end'] && $ann['end'] > $s['start']
            ));
            if (!$touched) {
                continue; // anotasi jatuh di spasi murni (tidak seharusnya terjadi)
            }

            $spansWords = count($touched) > 1;
            foreach ($touched as $k => $s) {
                // Iris anotasi ke dalam bentangan kata ini, lalu jadikan relatif.
                $segStart = max($ann['start'], $s['start']) - $s['start'];
                $segEnd   = min($ann['end'], $s['end']) - $s['start'];
                if ($segEnd <= $segStart) {
                    continue;
                }

                $result[$s['word_id']][] = [
                    'rule'        => $ann['rule'],
                    'start'       => $segStart,
                    'end'         => $segEnd,
                    'group_id'    => $groupId,
                    'is_start'    => $k === 0,
                    'is_end'      => $k === count($touched) - 1,
                    'spans_words' => $spansWords,
                ];
            }
        }

        // Urutkan tiap kata berdasarkan posisi
        foreach ($result as &$segs) {
            usort($segs, fn ($a, $b) => [$a['start'], $a['end']] <=> [$b['start'], $b['end']]);
        }

        return $result;
    }
}
