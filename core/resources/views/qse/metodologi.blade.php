@extends('qse.layout')

@section('title', 'Panduan Metodologi — QSE')

@section('content')
    <div class="eyebrow">Panduan Metodologi · Publik · Tanpa Login</div>
    <h1 class="page-title">Bagaimana Sistem Ini Bekerja</h1>
    <p class="lead">
        Halaman ini menjelaskan <em>cara</em> Quran Semantic Explorer menyusun observasi —
        bukan tafsir, bukan fatwa. Tidak ada bagian di sini yang menggantikan penjelasan
        Al-Qur'an atas dirinya sendiri; ini hanya peta jalan alat bantunya.
    </p>

    <nav class="method-toc" aria-label="Daftar bagian">
        <a href="#hirarki">Hirarki Data</a>
        <a href="#klasifikasi">Muhkamat &amp; Mutasyabihat</a>
        <a href="#verdict">Lima Status Verdict</a>
        <a href="#sumber">Status Epistemik Sumber</a>
        <a href="#tier">Tier 0 / 1 / 2</a>
        <a href="#grounding">Kenapa AI Tidak Bisa Mengarang Ayat</a>
    </nav>

    <section id="hirarki" class="method-section">
        <h2>Hirarki Data (Tidak Boleh Dibalik)</h2>
        <p>
            Setiap analisis di sistem ini mengikuti satu arah, dari yang paling dasar
            ke yang paling luas:
        </p>
        <ol class="hier-list">
            <li>Huruf</li>
            <li>Fonem (cara ucap)</li>
            <li>Root (akar kata)</li>
            <li>Kata</li>
            <li>Morfologi (bentuk kata dalam kalimat)</li>
            <li>Ayat</li>
            <li>Seluruh Al-Qur'an</li>
            <li>Analisa</li>
        </ol>
        <p class="method-note">
            Makna ayat tidak boleh diturunkan hanya dari huruf, fonem, atau root saja —
            itu sebabnya di halaman kata, Lapisan 1 (fonem) dan Lapisan 2 (root) selalu
            disertai kalimat "bukan makna kata". Kesalahan arah ini berlaku dua arah:
            penggunaan kata di satu tempat juga tidak boleh menghapus makna yang sudah
            jelas di tempat lain.
        </p>
    </section>

    <section id="klasifikasi" class="method-section">
        <h2>Muhkamat &amp; Mutasyabihat</h2>
        <p>
            Saat dua bacaan atas data tampak bertentangan, sistem tidak menebak mana yang
            benar. Ia merujuk pembagian yang dinyatakan Al-Qur'an sendiri di
            <a href="{{ route('qse.page.ayah', [3, 7]) }}">Ali 'Imran 7</a>:
        </p>
        <div class="classif-pair">
            <div class="classif-card muhkamat">
                <span class="classification-tag muhkamat"><span class="dot"></span> MUHKAMAT</span>
                <p>Ayat yang maknanya jelas dan berdiri sendiri. Di sistem ini, ayat
                    berstatus muhkamat menjadi <strong>anchor</strong> — titik rujukan saat
                    menguji hipotesis.</p>
            </div>
            <div class="classif-card mutasyabihat">
                <span class="classification-tag mutasyabihat"><span class="dot"></span> MUTASYABIHAT</span>
                <p>Ayat yang maknanya ambigu atau membuka banyak kemungkinan bacaan.
                    Diuji terhadap ayat muhkamat, bukan sebaliknya.</p>
            </div>
        </div>
        <p class="method-note">
            Klasifikasi ini sendiri bisa diperdebatkan — bukan boolean tetap. Setiap
            klasifikasi yang tampil di halaman ayat menyertakan sumber dan catatannya,
            dan dapat direvisi jika argumen yang lebih kuat muncul.
        </p>
    </section>

    <section id="verdict" class="method-section">
        <h2>Lima Status Verdict</h2>
        <p>
            Setiap hipotesis yang diuji terhadap korpus berakhir dengan salah satu dari
            lima status berikut. Tidak ada status keenam yang berarti "benar secara
            mutlak" — bahkan SYNC berarti "konsisten dengan seluruh ayat yang diuji
            <em>sejauh ini</em>", bukan penutup pembahasan.
        </p>
        <ul class="verdict-legend">
            <li><span class="verdict-stamp sync">SYNC</span> Konsisten dengan seluruh ayat yang diuji.</li>
            <li><span class="verdict-stamp partial">PARTIAL</span> Benar sebagian — ada pengecualian yang tercatat.</li>
            <li><span class="verdict-stamp contradicted">CONTRADICTED</span> Disangkal, lengkap dengan ayat penyangkalnya. Ini tetap tercatat permanen — bukan dihapus.</li>
            <li><span class="verdict-stamp insufficient">INSUFFICIENT</span> Data belum cukup untuk diuji. Sistem menyebutkan data apa yang dibutuhkan.</li>
            <li><span class="verdict-stamp beyond_scope">BEYOND SCOPE</span> Di luar jangkauan metodologi alat ini (mis. huruf muqatta'at).</li>
        </ul>
        <p class="method-note">
            Revisi verdict adalah tanda sistem sehat, bukan kegagalan. Riwayat verdict
            sebelumnya tetap terlihat di halaman detail tiap hipotesis.
        </p>
    </section>

    <section id="sumber" class="method-section">
        <h2>Status Epistemik Setiap Sumber Data</h2>
        <p>Data sumber pun punya status epistemiknya sendiri — bukan seragam "benar":</p>
        <table class="method-table">
            <thead><tr><th>Data</th><th>Status</th></tr></thead>
            <tbody>
                <tr><td>Teks Al-Qur'an</td><td>Data primer</td></tr>
                <tr><td>Morfologi &amp; root</td><td>Anotasi manusia — dapat salah, dapat dikoreksi</td></tr>
                <tr><td>Proto-Semitik</td><td>Hipotesis linguistik akademik, makna pra-Qur'ani</td></tr>
                <tr><td>Terjemahan &amp; gloss</td><td>Referensi pembanding — bukan makna final</td></tr>
                <tr><td>Kolokasi (PMI/G²)</td><td>Pola penggunaan statistik — bukan makna</td></tr>
            </tbody>
        </table>
        <p class="method-note">
            Sistem ini menggunakan riwayat qira'at <strong>Hafs 'an 'Ashim</strong> —
            pilihan metodologis yang dinyatakan terbuka, bukan klaim bahwa qira'at
            lain tidak sah.
        </p>
    </section>

    <section id="tier" class="method-section">
        <h2>Tier 0 / 1 / 2 — Siapa Boleh Apa</h2>
        <div class="tier-grid">
            <div class="tier-card">
                <span class="tier-label">Tier 0</span>
                <p>Data deterministik: teks, morfologi, statistik kolokasi. Tanpa AI,
                    terbuka untuk siapa saja.</p>
            </div>
            <div class="tier-card">
                <span class="tier-label">Tier 1</span>
                <p>Hasil AI yang sudah pernah digenerate dan disimpan (cache). Siapa
                    saja bisa membacanya — membaca tidak memicu generate baru.</p>
            </div>
            <div class="tier-card">
                <span class="tier-label">Tier 2</span>
                <p>Proses generate AI. Hanya kurator/admin yang bisa memicunya.
                    Pengguna biasa mengajukan hipotesis ke antrian — bukan menekan
                    tombol "generate".</p>
            </div>
        </div>
    </section>

    <section id="grounding" class="method-section">
        <h2>Kenapa AI Tidak Bisa Mengarang Ayat</h2>
        <p>
            AI di sistem ini <strong>tidak pernah menulis teks ayat dari memorinya</strong>.
            Setiap klaim "ayat X mengatakan Y" adalah rujukan ke baris database — teksnya
            dirender oleh sistem dari data yang tersimpan, bukan digenerate model bahasa.
            Kalau AI menyebut nomor ayat di luar hasil pengambilan data, keluaran itu
            ditolak sebelum sampai ke pengguna.
        </p>
        <p class="method-note">
            Satu ayat yang salah kutip bisa menghancurkan kredibilitas seluruh sistem —
            ini Al-Qur'an, bukan data biasa.
        </p>
    </section>
@endsection