<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Ayah extends Model
{
    protected $table = 'ayahs';
    public $timestamps = false;
    protected $fillable = ['surah_id','number_in_surah','text_uthmani','text_imlaei','text_normalized','text_tajweed','data_source_id'];
    protected $casts = ['text_tajweed' => 'array'];

    public function surah(): BelongsTo { return $this->belongsTo(Surah::class); }
    public function translations(): HasMany { return $this->hasMany(Translation::class); }

    public function words(): HasMany { return $this->hasMany(Word::class)->orderBy('position_in_ayah'); }
    public function classifications(): HasMany { return $this->hasMany(AyahClassification::class); }

    /**
     * Klasifikasi muhkamat/mutasyabihat yang berlaku saat ini (§6).
     *
     * STANDAR-seeder-data-uji.md: is_test_data disaring — TAPI hanya di
     * luar environment local/testing. Maksud standar itu mencegah data
     * uji BOCOR KE PRODUKSI, bukan menyembunyikannya saat sengaja sedang
     * diuji secara lokal (itu justru seluruh tujuan seeder-nya).
     *
     * KOREKSI dari implementasi awal saya: sebelumnya filter ini SELALU
     * aktif (termasuk di lokal), sehingga data dari DevTestClassificationSeeder
     * (is_test_data=true) tidak pernah terlihat sama sekali di halaman manapun,
     * termasuk saat sengaja diuji — kontradiksi dgn tujuan seedernya sendiri.
     */
    public function currentClassification()
    {
        $query = $this->hasOne(AyahClassification::class)->where('is_current', true);

        if (!app()->environment(['local', 'testing'])) {
            $query->where('is_test_data', false);
        }

        return $query;
    }

    /** Referensi ayat standar, mis. "2:255". */
    public function getRefAttribute(): string
    {
        return $this->surah_id . ':' . $this->number_in_surah;
    }
}
