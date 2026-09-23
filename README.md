# Robopath - Pelacakan Robot Otonom & Manajemen Armada 3D Real-Time

Robopath adalah sistem pelacakan robot otonom, visualisasi digital twin 3D, dan manajemen armada berbasis web yang dibangun menggunakan Laravel, Three.js, Tailwind CSS, dan HTML5 WebGL. Sistem ini menyediakan pemantauan multi-lantai secara langsung untuk unit robot pengantar otonom pada denah fasilitas, penentuan rute terpendek dinamis (algoritma A* dan Dijkstra), editor node dan denah ruangan interaktif, pengiriman tugas (*dispatching*), pelaporan insiden kendala fisik dengan bukti foto, serta sinkronisasi telemetri real-time.

Branch ini (`feature/robot-view-mode`) memperkenalkan fitur **Sistem Kamera Sudut Pandang Robot (3D Robot Follow Camera)**, yang memungkinkan operator merasakan perspektif fisik robot saat bermanuver melintasi ruangan, lorong, dan pintu fasilitas.

---

## Fitur Khusus Branch: Mode Kamera Ikuti Robot

Saat tombol **Ikuti** (Follow) aktif pada robot di Dashboard 3D, sistem pelacakan kamera menyediakan 3 mode sudut pandang dinamis:

1. **Mata Robot / POV (First-Person View - Default)**:
   - Menempatkan kamera tepat di titik mata dan sensor depan bodi robot (`eyeHeight = 0.020` hingga `0.046m` menyesuaikan skala robot).
   - Mengarahkan pandangan lurus horizontal ke depan lorong, ruangan, dan pintu masuk fasilitas (`lookAhead = 2.0m`), menciptakan pengalaman berkendara langsung dari sudut pandang onboard robot.
   - Menyembunyikan pin kartu nama dan badge status 2D pada robot yang sedang diikuti secara otomatis agar pandangan ke depan bersih tanpa halangan visual.
   - Mematikan penimpaan sudut OrbitControls selama mode FPV aktif sehingga ketinggian kamera menempel presisi di bodi robot tanpa ditarik paksa ke atas.

2. **Tampak Belakang (Third-Person Chase Cam)**:
   - Kamera sudut rendah sinematik yang mengikuti tepat di belakang punggung robot (`camDist = 0.28m` - `0.35m`, `camHeight = 0.08m` - `0.10m`).
   - Melakukan interpolasi halus (*smooth lerp*) saat robot berbelok di tikungan lorong sempit.

3. **Orbit Bebas (Isometrik Mengikuti Target)**:
   - Mengunci target fokus kamera pada robot sambil mempertahankan sudut pandang atas isometrik klasik.
   - Memungkinkan operator memutar, menggeser, dan memperbesar tampilan kamera secara manual menggunakan kursor mouse sembari robot terus bergerak.

### Kontrol Cepat & Pintasan Keyboard

- **Tombol Ganti Mode**: Terletak tepat di samping tombol **Ikuti** pada bilah bawah standar (`#btn-cycle-follow-mode`) dan navigasi atas layar penuh (`#fullview-btn-cycle-follow`). Menampilkan label mode aktif (`Mata Robot`, `Belakang`, atau `Orbit`).
- **Pintasan Keyboard (`V`)**: Tekan tombol **`V`** pada keyboard kapan saja saat mode Ikuti aktif untuk beralih mode kamera secara instan.
- **Konfigurasi Ketinggian Kamera**: Parameter kamera dapat disesuaikan langsung di file `resources/views/dashboard_3d.blade.php` pada fungsi `animate()` (variabel `eyeHeight`, `eyeForward`, `lookAhead`, `camDist`, dan `camHeight`).

---

## Fitur Utama Sistem

- **Digital Twin 3D Multi-Lantai**: Tampilan WebGL interaktif Three.js yang mendukung Lantai 1 dan Lantai 2 menggunakan model 3D GLB, bayangan terarah (*shadow maps*), kontrol pencahayaan matahari/ambient, serta skala label ruangan yang adaptif.
- **Editor Denah & Ruangan 3D Interaktif (`bot_control_3d`)**: Editor visual untuk menambahkan dan mengubah node ruangan, rute transit, titik tujuan pengantaran, dan jalur tangga antar-lantai yang tersinkronisasi langsung ke file `graph.json` lengkap dengan tombol simpan dedicated.
- **Mesin Eksekusi Misi Otonom**: Pengiriman tugas pengantaran barang ke berbagai titik lokasi (Meja Kerja, Table Office 1-4, Ruang Rapat, dan Titik Transit) dengan siklus hidup lengkap (pengambilan barang, transit perjalanan, serah terima, dan kembali ke markas).
- **Perutean Khusus Lorong Fasilitas**: Menerapkan aturan perutean lorong yang ketat melewati jalur koridor utama, mencegah robot menembus dinding ruangan secara tidak realistis.
- **Manajemen Baterai & Insiden**: Simulasi penurunan daya baterai, prosedur otomatis kembali ke markas saat baterai menipis, deteksi tabrakan/kendala, serta formulir laporan kerusakan fisik dengan unggah bukti foto (maksimal 1MB).
- **Antarmuka Responsif Modern**: Tata letak bilah alat mengambang (*floating toolbars*), panel inspektur kontekstual, dan mode layar penuh (*fullview*) yang responsif di berbagai resolusi layar tanpa risiko terpotong.
- **Standar Visual Profesional**: Menggunakan ikon resmi FontAwesome 6 dan tipografi bersih tanpa karakter emoji di seluruh antarmuka, log, dan kode program.

---

## Tumpukan Teknologi

- **Backend Framework**: Laravel 11.x (PHP 8.3+)
- **Mesin Grafis 3D**: Three.js (r128) WebGL dengan OrbitControls dan GLTFLoader
- **Frontend & Tampilan**: Blade Templates, Tailwind CSS 3.x, FontAwesome 6.x
- **Database**: MySQL / PostgreSQL / SQLite
- **Asset Bundler**: Vite

---

## Panduan Instalasi & Menjalankan Sistem

1. **Clone Repositori dan Masuk ke Branch Fitur**:
   ```bash
   git clone https://github.com/RyanHidayat058/Robopath.git
   cd Robopath
   git checkout feature/robot-view-mode
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
