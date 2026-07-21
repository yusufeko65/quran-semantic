<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * QSE SEEDER — Import hasil ETL ke MariaDB
 * =========================================
 * Pemakaian (Windows, contoh Laragon):
 *   php artisan qse:import storage/app/qse_seed
 *   (gunakan forward slash "/" walau di Windows — PHP menerimanya)
 *
 * Prasyarat : migration sudah dijalankan (php artisan migrate).
 * Sumber CSV: dihasilkan oleh etl_qse.py (surahs, ayahs, roots, words,
 *             phonemes, alignment_flags).
 *
 * Prinsip manifest yang ditegakkan:
 *  - §13: registrasi data_sources dengan versi terkunci SEBELUM data dimuat
 *  - §13: ketidakselarasan Tanzil↔QAC dicatat sebagai data_flags, bukan diabaikan
 *  - Idempotent: aman dijalankan ulang (truncate per tabel sebelum insert ulang;
 *    TRUNCATE tidak dibungkus transaksi karena menyebabkan implicit commit di MySQL)
 */
class QseImport extends Command
{
    protected $signature = 'qse:import {path : Folder berisi CSV hasil ETL}';
    protected $description = 'Import korpus Al-Qur\'an + morfologi QAC ke database QSE';

    private const CHUNK = 1000;

    public function handle(): int
    {
        $path = rtrim($this->argument('path'), '/');
        foreach (['surahs', 'ayahs', 'roots', 'words', 'phonemes'] as $f) {
            if (!is_file("$path/$f.csv")) {
                $this->error("File tidak ditemukan: $path/$f.csv");
                return self::FAILURE;
            }
        }

        // ------------------------------------------------------------------
        // TAHAP 0 — Registrasi sumber data (§13) — versi WAJIB dikunci manual
        // ------------------------------------------------------------------
        $this->info('Tahap 0: Registrasi data_sources');
        $srcTanzil = $this->registerSource(
            'Tanzil Uthmani (via risan/quran-json)',
            'quran-json dist (SESUAIKAN: kunci commit hash)',
            'https://github.com/risan/quran-json', 'primary'
        );
        $srcQac = $this->registerSource(
            'Quranic Arabic Corpus (mirror mustafa0x/quran-morphology)',
            'QAC 0.4 (SESUAIKAN: kunci commit hash)',
            'https://github.com/mustafa0x/quran-morphology', 'primary'
        );

        // ------------------------------------------------------------------
        // TAHAP 1 — Surah & Ayat
        // ------------------------------------------------------------------
        $this->info('Tahap 1: surahs + ayahs');
        // Catatan: TRUNCATE menyebabkan implicit commit di MySQL/MariaDB,
        // sehingga TIDAK dibungkus DB::transaction() (akan error "no active transaction").
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('ayahs')->truncate();
        DB::table('surahs')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->loadCsv("$path/surahs.csv", function ($rows) {
            DB::table('surahs')->insert(array_map(fn ($r) => [
                'id'              => (int) $r['id'],
                'name_arabic'     => $r['name_arabic'],
                'transliteration' => $r['transliteration'],
                'revelation_type' => $r['revelation_type'],
                'total_ayahs'     => (int) $r['total_ayahs'],
            ], $rows));
        });

        $this->loadCsv("$path/ayahs.csv", function ($rows) use ($srcTanzil) {
            DB::table('ayahs')->insert(array_map(fn ($r) => [
                'id'              => (int) $r['id'],
                'surah_id'        => (int) $r['surah_id'],
                'number_in_surah' => (int) $r['number_in_surah'],
                'text_uthmani'    => $r['text_uthmani'],
                'text_normalized' => $r['text_normalized'],
                'data_source_id'  => $srcTanzil,
            ], $rows));
        });
        $this->line('  ayahs: ' . number_format(DB::table('ayahs')->count()));

        // ------------------------------------------------------------------
        // TAHAP 2 — Roots
        // ------------------------------------------------------------------
        $this->info('Tahap 2: roots');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('roots')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->loadCsv("$path/roots.csv", function ($rows) {
            DB::table('roots')->insert(array_map(fn ($r) => [
                'arabic'          => $r['arabic'],
                'transliteration' => $r['transliteration'],
                'letter_count'    => (int) $r['letter_count'],
            ], $rows));
        });
        $rootMap = DB::table('roots')->pluck('id', 'arabic')->all();
        $this->line('  roots: ' . number_format(count($rootMap)));

        // ------------------------------------------------------------------
        // TAHAP 3 — Words (77 ribu baris, chunked)
        // ------------------------------------------------------------------
        $this->info('Tahap 3: words');
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('words')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $total = 0;
        $this->loadCsv("$path/words.csv", function ($rows) use ($rootMap, $srcQac, &$total) {
            DB::table('words')->insert(array_map(fn ($r) => [
                'ayah_id'          => (int) $r['ayah_id'],
                'position_in_ayah' => (int) $r['position_in_ayah'],
                'text_uthmani'     => $r['text_uthmani'],
                'text_normalized'  => $r['text_normalized'],
                'root_id'          => $rootMap[$r['root_arabic']] ?? null,
                'lemma'            => $r['lemma'] ?: null,
                'pos'              => $r['pos'] ?: null,
                'morph_features'   => $r['morph_features'],
                'segments'         => $r['segments'],
                'qac_location'     => $r['qac_location'],
                'data_source_id'   => $srcQac,
            ], $rows));
            $total += count($rows);
        });
        $this->line('  words: ' . number_format($total));

        // ------------------------------------------------------------------
        // TAHAP 4 — Phonemes (master 28 huruf)
        // ------------------------------------------------------------------
        $this->info('Tahap 4: phonemes');
        DB::table('phonemes')->truncate();
        $this->loadCsv("$path/phonemes.csv", function ($rows) {
            DB::table('phonemes')->insert(array_map(fn ($r) => [
                'letter'      => $r['letter'],
                'letter_name' => $r['letter_name'],
                'ipa'         => $r['ipa'],
                'makhraj'     => $r['makhraj'],
                'sifat'       => $r['sifat'],
            ], $rows));
        });

        // ------------------------------------------------------------------
        // TAHAP 5 — Ketidakselarasan Tanzil↔QAC → data_flags (§13)
        // ------------------------------------------------------------------
        if (is_file("$path/alignment_flags.csv")) {
            $this->info('Tahap 5: alignment flags → data_flags');
            $this->loadCsv("$path/alignment_flags.csv", function ($rows) {
                foreach ($rows as $r) {
                    $ayahId = DB::table('ayahs')
                        ->where('surah_id', $r['surah'])
                        ->where('number_in_surah', $r['ayah'])
                        ->value('id');
                    DB::table('data_flags')->insert([
                        'flaggable_type' => 'ayahs',
                        'flaggable_id'   => $ayahId,
                        'field_name'     => 'word_tokenization',
                        'current_value'  => "Tanzil: {$r['tanzil_words']} kata",
                        'proposed_value' => "QAC: {$r['qac_words']} kata",
                        'reason'         => 'Tokenisasi Tanzil dan QAC tidak selaras — perlu review kurator (§13)',
                        'proposed_by'    => 1, // system user
                        'status'         => 'open',
                        'created_at'     => now(),
                    ]);
                }
            });
            $this->warn('  ' . DB::table('data_flags')->where('field_name', 'word_tokenization')->count()
                . ' ayat ditandai untuk review kurator.');
        }

        // ------------------------------------------------------------------
        // VERIFIKASI AKHIR
        // ------------------------------------------------------------------
        $this->newLine();
        $this->info('Verifikasi:');
        $checks = [
            ['surahs', 114],
            ['ayahs', 6236],
            ['words', 77429],
        ];
        $ok = true;
        foreach ($checks as [$table, $expected]) {
            $n = DB::table($table)->count();
            $status = $n === $expected ? 'OK' : "GAGAL (harap {$expected})";
            $ok = $ok && $n === $expected;
            $this->line(sprintf('  %-8s: %s — %s', $table, number_format($n), $status));
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    private function registerSource(string $name, string $version, string $url, string $category): int
    {
        $existing = DB::table('data_sources')->where('name', $name)->where('version', $version)->value('id');
        if ($existing) {
            return $existing;
        }
        return DB::table('data_sources')->insertGetId([
            'name' => $name, 'version' => $version, 'url' => $url,
            'category' => $category, 'qiraat' => 'Hafs an Asim',
            'locked_at' => now(),
        ]);
    }

    /** Baca CSV secara streaming, panggil $handler per chunk baris assoc. */
    private function loadCsv(string $file, callable $handler): void
    {
        $h = fopen($file, 'r');
        $header = fgetcsv($h);
        $chunk = [];
        while (($row = fgetcsv($h)) !== false) {
            $chunk[] = array_combine($header, $row);
            if (count($chunk) >= self::CHUNK) {
                $handler($chunk);
                $chunk = [];
            }
        }
        if ($chunk) {
            $handler($chunk);
        }
        fclose($h);
    }
}
