<?php

namespace Database\Seeders;

use App\Models\Ayah;
use App\Models\AyahClassification;
use App\Models\DataSource;
use Illuminate\Database\Seeder;

/**
 * STANDAR-seeder-data-uji.md — seeder data uji utk verifikasi visual
 * .classification-tag (SPEC-UX-03 §3.2). TIDAK didaftarkan di
 * DatabaseSeeder utama — jalankan eksplisit:
 *
 *   php artisan db:seed --class=Database\\Seeders\\DevTestClassificationSeeder
 *
 * Cleanup (setelah verifikasi visual selesai, JANGAN lupa):
 *   php artisan tinker
 *   (new \Database\Seeders\DevTestClassificationSeeder())->cleanup();
 *
 * Ayat dipilih SENGAJA netral (Al-Fatihah 1:2, 1:3) — bukan Ali 'Imran 7
 * atau ayat lain yang punya muatan simbolis terkait §6 sendiri. Tujuan
 * murni verifikasi CSS (border emas vs rose), bukan klaim interpretif.
 *
 * HANYA untuk database LOKAL/staging — TIDAK PERNAH dijalankan di hosting
 * produksi (STANDAR §5).
 */
class DevTestClassificationSeeder extends Seeder
{
    private const NOTE_MARKER = 'DEVTEST-classification-tag-visual';

    public function run(): void
    {
        $sourceId = DataSource::query()->value('id');
        if (!$sourceId) {
            $this->command?->error('DevTestClassificationSeeder: tabel data_sources kosong — impor data dulu (qse:import) sebelum menjalankan seeder ini.');
            return;
        }

        $muhkamatAyahId = Ayah::query()->where('surah_id', 1)->where('number_in_surah', 2)->value('id');
        $mutasyabihatAyahId = Ayah::query()->where('surah_id', 1)->where('number_in_surah', 3)->value('id');

        if (!$muhkamatAyahId || !$mutasyabihatAyahId) {
            $this->command?->error('DevTestClassificationSeeder: ayat 1:2/1:3 tidak ditemukan — impor data dulu.');
            return;
        }

        AyahClassification::query()->updateOrCreate(
            ['ayah_id' => $muhkamatAyahId, 'notes' => self::NOTE_MARKER],
            [
                'classification' => 'muhkamat',
                'source_id'      => $sourceId,
                'is_current'     => true,
                'is_test_data'   => true,
                'created_at'     => now(),
            ]
        );

        AyahClassification::query()->updateOrCreate(
            ['ayah_id' => $mutasyabihatAyahId, 'notes' => self::NOTE_MARKER],
            [
                'classification' => 'mutasyabihat',
                'source_id'      => $sourceId,
                'is_current'     => true,
                'is_test_data'   => true,
                'created_at'     => now(),
            ]
        );

        $this->command?->info('DevTestClassificationSeeder: data uji dimasukkan (1:2=muhkamat, 1:3=mutasyabihat). '
            . 'INGAT jalankan cleanup() setelah verifikasi visual selesai.');
    }

    /** Hapus PERSIS apa yang run() masukkan — dicocokkan via is_test_data + note marker, bukan tebakan ID. */
    public function cleanup(): void
    {
        $deleted = AyahClassification::query()
            ->where('is_test_data', true)
            ->where('notes', self::NOTE_MARKER)
            ->delete();

        $this->command?->info("DevTestClassificationSeeder::cleanup() — {$deleted} baris data uji dihapus.");
    }
}
