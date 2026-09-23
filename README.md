# Robopath - Pelacakan Robot Otonom & Manajemen Armada 3D Real-Time

Robopath adalah sistem pelacakan robot otonom, visualisasi digital twin 3D, dan manajemen armada berbasis web yang dibangun menggunakan Laravel, Three.js, Tailwind CSS, dan HTML5 WebGL. Sistem ini menyediakan pemantauan multi-lantai secara langsung untuk unit robot pengantar otonom pada denah fasilitas, penentuan rute terpendek dinamis (algoritma A* dan Dijkstra), editor node dan denah ruangan interaktif, pengiriman tugas (*dispatching*), pelaporan insiden kendala fisik dengan bukti foto, serta sinkronisasi telemetri real-time.

Ini adalah branch utama produksi yang stabil (**`main`**).

---

## Fitur Utama Sistem

- **Digital Twin 3D Multi-Lantai**: Tampilan WebGL interaktif Three.js yang mendukung pemantauan Lantai 1 dan Lantai 2 menggunakan model 3D GLB detail, bayangan terarah (*shadow maps*), kontrol pencahayaan matahari/ambient, serta skala label ruangan yang adaptif.
- **Editor Denah & Ruangan 3D Interaktif (`bot_control_3d`)**: Editor visual komprehensif yang memungkinkan administrator menambah dan mengubah node ruangan, rute transit, titik tujuan pengantaran, dan jalur tangga antar-lantai yang tersinkronisasi langsung ke file `graph.json` lengkap dengan tombol simpan dedicated.
- **Manajemen Armada & Pelacakan Real-Time**: Pemantauan koordinat posisi dan siklus hidup misi untuk beberapa unit robot (Robot Alpha, Robot Beta, Robot Gamma) menuju berbagai titik tujuan (Meja Kerja, Table Office 1-4, Ruang Rapat, dan Titik Transit).
- **Perutean Khusus Lorong Fasilitas**: Menerapkan aturan perutean lorong yang ketat melewati jalur koridor utama, mencegah robot menembus dinding ruangan secara tidak realistis.
- **Siklus Hidup Misi Multi-Tahap**: Menangani pengambilan barang (*pickup*), perjalanan transit (*travel*), serah terima barang (*dropoff*), dan prosedur otomatis kembali ke markas (*return-to-base*) setelah misi selesai.
- **Manajemen Baterai & Insiden**: Simulasi penurunan daya baterai, prosedur otomatis kembali ke markas saat baterai menipis, deteksi tabrakan/kendala, serta formulir laporan kerusakan fisik dengan unggah bukti foto (maksimal 1MB).
- **Hak Akses Berbasis Peran**: Pengelolaan alur kerja aman dengan pemisahan wewenang antara peran Administrator (akses penuh editor denah, pencahayaan 3D, kontrol robot) dan Karyawan (pemantauan status dan pelaporan insiden).
- **Antarmuka Responsif Modern**: Tata letak bilah alat mengambang (*floating toolbars*), panel inspektur kontekstual, dan mode layar penuh (*fullview*) yang responsif di berbagai resolusi layar tanpa risiko terpotong.
- **Standar Visual Profesional**: Menggunakan ikon resmi FontAwesome 6 dan tipografi bersih tanpa karakter emoji di seluruh antarmuka, log, dan kode program.

---

## Cabang Pengembangan (Development Branches)

- **`main`**: Branch utama produksi yang stabil berisi fitur inti pelacakan 3D, editor denah, dan manajemen armada.
- **`feature/robot-view-mode`**: Branch pengembangan fitur khusus yang mengimplementasikan **Sistem Kamera Sudut Pandang Robot (3D Robot Follow Camera)** dengan mode Mata Robot (POV), Tampak Belakang (Chase Cam), dan Orbit Bebas serta pintasan keyboard tombol `V`.

---

## Tumpukan Teknologi

- **Backend Framework**: Laravel 11.x (PHP 8.3+)
- **Mesin Grafis 3D**: Three.js (r128) WebGL dengan OrbitControls dan GLTFLoader
- **Frontend & Tampilan**: Blade Templates, Tailwind CSS 3.x, FontAwesome 6.x
- **Database**: MySQL / PostgreSQL / SQLite
- **Asset Bundler**: Vite

---

## Persyaratan Sistem

- PHP >= 8.3 dengan ekstensi pendukung (OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON)
- Composer >= 2.x
- Node.js >= 18.x dan NPM

---

## Konfigurasi Lingkungan (.env.example)

File `.env` yang memuat kredensial lokal dikecualikan dari pelacakan repositori melalui `.gitignore` untuk keamanan. Saat mengkloning atau menerapkan repositori ini, pengembang perlu menyalin file `.env.example` menjadi `.env` sebelum menjalankan pembuatan kunci aplikasi dan migrasi database.

---

## Panduan Instalasi & Menjalankan Sistem

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/RyanHidayat058/Robopath.git
   cd Robopath
   ```

2. **Pasang Dependensi PHP (Composer)**:
   ```bash
   composer install
   ```

3. **Pasang Dependensi Frontend (NPM)**:
   ```bash
   npm install
   ```

4. **Konfigurasi Environment**:
   Salin file contoh konfigurasi dan buat kunci aplikasi baru:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Migrasi Database & Seeder**:
   Sesuaikan kredensial basis data di file `.env`, kemudian jalankan:
   ```bash
   php artisan migrate:fresh --seed
   ```

6. **Tautan Penyimpanan (Storage Link)**:
   Buat symbolic link untuk folder berkas gambar bukti laporan:
   ```bash
   php artisan storage:link
   ```

7. **Kompilasi Aset Frontend**:
   ```bash
   npm run build
   ```

8. **Jalankan Server Lokal**:
   ```bash
   php artisan serve
   ```
   Buka aplikasi pada peramban web di alamat `http://127.0.0.1:8000`.

---

## Endpoint API Utama

- `GET /api/telemetry` - Mengambil koordinat posisi aktif robot, status baterai, misi pengantaran, dan peringatan sistem.
- `POST /api/deliveries` - Mengirim tugas misi pengantaran baru ke unit robot yang tersedia.
- `PUT /api/deliveries/{id}/complete` - Menandai misi pengantaran aktif sebagai telah selesai.
- `POST /api/reports` - Mengirim laporan insiden kendala fisik dengan lampiran foto bukti.
- `PUT /api/reports/{id}/resolve` - Menyelesaikan dan menutup status laporan insiden.
- `POST /api/robots/{id}/telemetry` - Memperbarui data telemetri masing-masing unit robot.
- `POST /api/system/reset` - Mengatur ulang seluruh misi aktif, laporan insiden, dan mengembalikan robot ke markas.

---

## Lisensi

Proyek ini merupakan perangkat lunak sumber terbuka di bawah lisensi MIT.
