<?php

namespace App\Services\Qse;

/**
 * GROUNDING VALIDATOR (Manifest §12) — aturan paling kritis sistem.
 *
 * Kontrak output AI (Tier 2):
 *  - Output berbentuk JSON terstruktur.
 *  - SEMUA rujukan ayat berupa ID database, di field bernama `ayah_id`
 *    atau array `ayah_ids` / `cited_ayah_ids` — di level manapun.
 *  - AI TIDAK PERNAH menulis teks ayat; teks dirender sistem dari DB.
 *
 * Aturan: setiap ayah_id pada output HARUS ⊆ retrieved_ayah_ids.
 * Satu saja di luar himpunan → SELURUH output DITOLAK sebelum sampai pengguna.
 */
class GroundingValidator
{
    /**
     * @param  array  $aiOutput           Output AI (sudah di-decode dari JSON)
     * @param  array<int>  $retrievedIds  Ayat yang diberikan ke AI sebagai input
     * @return array{passed: bool, violations: array<int>, checked: int}
     */
    public function validate(array $aiOutput, array $retrievedIds): array
    {
        $allowed = array_flip(array_map('intval', $retrievedIds));
        $found   = [];
        $this->collectAyahIds($aiOutput, $found);

        $violations = [];
        foreach ($found as $id) {
            if (!isset($allowed[$id])) {
                $violations[] = $id;
            }
        }

        return [
            'passed'     => $violations === [],
            'violations' => array_values(array_unique($violations)),
            'checked'    => count($found),
        ];
    }

    /** Telusuri rekursif seluruh struktur output, kumpulkan semua rujukan ayat. */
    private function collectAyahIds(array $node, array &$found): void
    {
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                // Field array berisi ID: ayah_ids, cited_ayah_ids, dsb.
                if (is_string($key) && preg_match('/ayah_ids?$/i', $key)) {
                    foreach ($value as $v) {
                        if (is_numeric($v)) $found[] = (int) $v;
                        elseif (is_array($v)) $this->collectAyahIds($v, $found);
                    }
                } else {
                    $this->collectAyahIds($value, $found);
                }
            } elseif (is_numeric($value) && is_string($key) && preg_match('/ayah_id$/i', $key)) {
                $found[] = (int) $value;
            }
        }
    }
}
