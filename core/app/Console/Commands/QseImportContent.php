<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * QSE — Import konten pembanding: terjemahan per-ayat & gloss per-kata.
 *
 * Pemakaian:
 *   php artisan qse:import-content storage/app/qse_seed
 *
 * File yang dibaca (jika ada — yang tidak ada dilewati dengan jujur):
 *   translations.csv  kolom: surah,ayah,lang,text
 *   word_glosses.csv  kolom: surah,ayah,position,lang,gloss
 *
 * Prinsip (handoff #6 + Manifest §13): setiap sumber terdaftar di data_sources
 * dengan license terisi SEBELUM tayang. Catatan lisensi ditulis apa adanya —
 * bukan klaim hukum.
 */
class QseImportContent extends Command
{
    protected $signature = 'qse:import-content {path : Folder berisi CSV}';
    protected $description = 'Import terjemahan per-ayat dan gloss per-kata QSE';

    private const CHUNK = 1000;

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');

        // Peta (surah, ayah) -> ayah_id, dan (surah, ayah, pos) -> word_id
        $ayahMap = [];
        foreach (DB::table('ayahs')->select('id', 'surah_id', 'number_in_surah')->get() as $a) {
            $ayahMap[$a->surah_id . ':' . $a->number_in_surah] = $a->id;
        }

        // ------------------------------------------------------------
        // TERJEMAHAN PER-AYAT
        // ------------------------------------------------------------
        if (is_file("$path/translations.csv")) {
            $this->info('Import terjemahan per-ayat');

            $sourceId = $this->registerSource(
                name: 'Terjemahan Kemenag RI — Edisi Penyempurnaan 2019 (via renomureza/quran-api-id)',
                version: 'repo quran-api-id v3.0.0, src/data/quran.json',
                url: 'https://github.com/renomureza/quran-api-id',
                category: 'comparator',
                license: 'Kemenag RI — distribusi resmi gratis (lihat notes)',
                notes: 'Terjemahan resmi Kementerian Agama RI, didistribusikan gratis oleh Kemenag. '
                    . 'Repo pengumpul berlisensi MIT (berlaku untuk KODE, bukan konten terjemahan). '
                    . 'Dasar pemakaian konten: karya yang dilaksanakan oleh/atas nama pemerintah RI '
                    . '(UU Hak Cipta 28/2014) + distribusi gratis resmi Kemenag. '
                    . 'CATATAN: dicatat apa adanya, bukan nasihat hukum. Atribusi ke Kemenag RI wajib tampil.',
            );

            DB::table('translations')->where('source_id', $sourceId)->delete();

            $n = 0; $miss = 0;
            $this->loadCsv("$path/translations.csv", function ($rows) use ($ayahMap, $sourceId, &$n, &$miss) {
                $insert = [];
                foreach ($rows as $r) {
                    $ayahId = $ayahMap[$r['surah'] . ':' . $r['ayah']] ?? null;
                    if (!$ayahId) { $miss++; continue; }
                    $insert[] = [
                        'ayah_id'    => $ayahId,
                        'source_id'  => $sourceId,
                        'lang'       => $r['lang'],
                        'text'       => $r['text'],
                        'created_at' => now(),
                    ];
                }
                if ($insert) { DB::table('translations')->insert($insert); $n += count($insert); }
            });
            $this->line("  terjemahan: " . number_format($n) . ($miss ? " (LEWAT: $miss baris tak cocok ayat)" : ''));
        } else {
            $this->warn('translations.csv tidak ada — dilewati (slot tetap jujur kosong).');
        }

        // ------------------------------------------------------------
        // GLOSS PER-KATA
        // ------------------------------------------------------------
        if (is_file("$path/word_glosses.csv")) {
            $this->info('Import gloss per-kata');

            // SESUAIKAN metadata sumber gloss sebelum menjalankan —
            // license WAJIB benar (gerbang tayang, handoff #6).
            $glossSourceId = $this->registerSource(
                name: 'Word-by-word Bahasa Indonesia (Quranic Universal Library)',
                version: 'indonesian-word-by-word-translation.json (SESUAIKAN: tanggal unduh)',
                url: 'https://qul.tarteel.ai',
                category: 'comparator',
                license: 'Per halaman resource QUL — VERIFIKASI sebelum tayang publik',
                notes: 'Gloss per-kata Bahasa Indonesia dari Quranic Universal Library (Tarteel). '
                    . 'Alignment ke tokenisasi QAC divalidasi penuh (6.233 ayat peta-langsung; '
                    . '3 ayat pola pemecahan بعدما diperbaiki: 2:181, 8:6, 13:37 — kata terakhir '
                    . 'ketiganya TANPA gloss karena cacat data sumber, tercatat di data_flags).',
            );

            $wordMap = [];
            foreach (DB::table('words')->select('id', 'ayah_id', 'position_in_ayah')->get() as $w) {
                $wordMap[$w->ayah_id . ':' . $w->position_in_ayah] = $w->id;
            }

            DB::table('word_glosses')->where('source_id', $glossSourceId)->delete();

            $n = 0; $miss = 0;
            $this->loadCsv("$path/word_glosses.csv", function ($rows) use ($ayahMap, $wordMap, $glossSourceId, &$n, &$miss) {
                $insert = [];
                foreach ($rows as $r) {
                    $ayahId = $ayahMap[$r['surah'] . ':' . $r['ayah']] ?? null;
                    $wordId = $ayahId ? ($wordMap[$ayahId . ':' . $r['position']] ?? null) : null;
                    if (!$wordId) { $miss++; continue; }
                    $insert[] = [
                        'word_id'    => $wordId,
                        'source_id'  => $glossSourceId,
                        'lang'       => $r['lang'],
                        'gloss'      => mb_substr($r['gloss'], 0, 255),
                        'created_at' => now(),
                    ];
                }
                if ($insert) { DB::table('word_glosses')->insert($insert); $n += count($insert); }
            });
            $this->line("  gloss: " . number_format($n) . ($miss ? " (LEWAT: $miss)" : ''));
        } else {
            $this->warn('word_glosses.csv tidak ada — dilewati (slot tetap jujur kosong).');
        }

        // ------------------------------------------------------------
        // TERJEMAHAN INGGRIS — MUHAMMAD ASAD (via QUL)
        // ------------------------------------------------------------
        if (is_file("$path/translations_en_asad.csv")) {
            $this->info('Import terjemahan Inggris (Asad)');

            $asadId = $this->registerSource(
                name: 'The Message of the Qur\'an — Muhammad Asad (via QUL)',
                version: 'en-asad-simple.json (SESUAIKAN: tanggal unduh)',
                url: 'https://qul.tarteel.ai',
                category: 'comparator',
                license: 'Hak cipta The Book Foundation — VERIFIKASI syarat redistribusi sebelum tayang publik',
                notes: 'Terjemahan Inggris Muhammad Asad, diunduh dari Quranic Universal Library. '
                    . 'Status lisensi perlu diverifikasi di halaman resource QUL sebelum ditayangkan '
                    . 'ke publik (gerbang §6 handoff / Manifest §13).',
            );

            DB::table('translations')->where('source_id', $asadId)->delete();

            $n = 0; $miss = 0;
            $this->loadCsv("$path/translations_en_asad.csv", function ($rows) use ($ayahMap, $asadId, &$n, &$miss) {
                $insert = [];
                foreach ($rows as $r) {
                    $ayahId = $ayahMap[$r['surah'] . ':' . $r['ayah']] ?? null;
                    if (!$ayahId) { $miss++; continue; }
                    $insert[] = [
                        'ayah_id' => $ayahId, 'source_id' => $asadId,
                        'lang' => $r['lang'], 'text' => $r['text'], 'created_at' => now(),
                    ];
                }
                if ($insert) { DB::table('translations')->insert($insert); $n += count($insert); }
            });
            $this->line('  terjemahan (en): ' . number_format($n));
        }

        // ------------------------------------------------------------
        // FLAG CACAT SUMBER GLOSS -> data_flags (§13: kejujuran data dasar)
        // ------------------------------------------------------------
        if (is_file("$path/gloss_flags.csv")) {
            $this->info('Mencatat cacat sumber gloss ke data_flags');
            $this->loadCsv("$path/gloss_flags.csv", function ($rows) use ($ayahMap) {
                foreach ($rows as $r) {
                    $ayahId = $ayahMap[$r['surah'] . ':' . $r['ayah']] ?? null;
                    if (!$ayahId) continue;
                    $wordId = DB::table('words')->where('ayah_id', $ayahId)
                        ->where('position_in_ayah', $r['position'])->value('id');
                    if (!$wordId) continue;
                    $exists = DB::table('data_flags')
                        ->where('flaggable_type', 'words')->where('flaggable_id', $wordId)
                        ->where('field_name', 'gloss')->exists();
                    if ($exists) continue;
                    DB::table('data_flags')->insert([
                        'flaggable_type' => 'words', 'flaggable_id' => $wordId,
                        'field_name' => 'gloss', 'current_value' => null,
                        'proposed_value' => null, 'reason' => $r['reason'],
                        'proposed_by' => 1, 'status' => 'open', 'created_at' => now(),
                    ]);
                }
            });
            $this->line('  flag dicatat.');
        }

        // ------------------------------------------------------------
        // TAJWID — anotasi per-ayat (handoff #1) -> ayahs.text_tajweed
        // ------------------------------------------------------------
        if (is_file("$path/ayah_tajweed.json")) {
            $this->info('Import anotasi tajwid');

            $this->registerSource(
                name: 'Anotasi Tajwid Hafs — cpfair/quran-tajweed (remap terverifikasi)',
                version: 'commit master (SESUAIKAN: kunci hash) + remap 2026-07-07',
                url: 'https://github.com/cpfair/quran-tajweed',
                category: 'secondary',
                license: 'CC BY 4.0 (file data, per README repo)',
                notes: 'Offset asli terikat teks Tanzil ~2017; di-remap ke teks terpasang '
                    . 'via alignment per-ayat + verifikasi per-anotasi (6 aturan anchor '
                    . 'dicek karakter target, hasil akhir 100% lolos). 59.563/60.057 '
                    . 'anotasi (99,18%) terimpor; 494 dibuang tercatat; 134 ayat tanpa '
                    . 'tajwid (63 kosong di sumber, 71 gagal remap - mayoritas muqattaat) '
                    . 'ter-flag di data_flags. Tajwid = aids rekitasi, BUKAN makna.',
            );

            $data = json_decode(file_get_contents("$path/ayah_tajweed.json"), true);
            $n = 0;
            foreach (array_chunk(array_keys($data), 500) as $chunkIds) {
                foreach ($chunkIds as $ayahId) {
                    DB::table('ayahs')->where('id', (int) $ayahId)
                        ->update(['text_tajweed' => json_encode($data[$ayahId])]);
                    $n++;
                }
            }
            $this->line('  ayat ber-tajwid: ' . number_format($n));
        }

        if (is_file("$path/tajweed_flags.csv")) {
            $this->loadCsv("$path/tajweed_flags.csv", function ($rows) use ($ayahMap) {
                foreach ($rows as $r) {
                    $ayahId = $ayahMap[$r['surah'] . ':' . $r['ayah']] ?? null;
                    if (!$ayahId) continue;
                    $exists = DB::table('data_flags')
                        ->where('flaggable_type', 'ayahs')->where('flaggable_id', $ayahId)
                        ->where('field_name', 'text_tajweed')->exists();
                    if ($exists) continue;
                    DB::table('data_flags')->insert([
                        'flaggable_type' => 'ayahs', 'flaggable_id' => $ayahId,
                        'field_name' => 'text_tajweed', 'current_value' => null,
                        'proposed_value' => null, 'reason' => $r['reason'],
                        'proposed_by' => 1, 'status' => 'open', 'created_at' => now(),
                    ]);
                }
            });
            $this->line('  flag tajwid dicatat.');
        }

        return self::SUCCESS;
    }

    private function registerSource(string $name, string $version, string $url, string $category, string $license, string $notes = ''): int
    {
        $existing = DB::table('data_sources')->where('name', $name)->value('id');
        if ($existing) {
            return $existing;
        }
        return DB::table('data_sources')->insertGetId([
            'name' => $name, 'version' => $version, 'url' => $url,
            'license' => $license, 'category' => $category,
            'notes' => $notes,
            'locked_at' => now(),
        ]);
    }

    private function loadCsv(string $file, callable $handler): void
    {
        $h = fopen($file, 'r');
        $header = fgetcsv($h);
        $chunk = [];
        while (($row = fgetcsv($h)) !== false) {
            $chunk[] = array_combine($header, $row);
            if (count($chunk) >= self::CHUNK) { $handler($chunk); $chunk = []; }
        }
        if ($chunk) { $handler($chunk); }
        fclose($h);
    }
}
