# Handoff pengembangan aplikasi sekolah

Checkpoint terbaru: 2026-10-15 (WIB). Branch `main`.
HEAD aktual: `5f18c06f784542451e89426f14ea7c28071999da` ("fix(phase-01a): decouple signed-in identity from account administration"), **sudah di-push ke `origin/main`** (tersinkronisasi penuh).
Kebijakan commit/push: commit dan push per fase/koreksi ke branch kerja aktif telah **diotorisasi**; merge, force-push, rewrite history, dan deploy manual **tetap tidak diotorisasi**.

Perubahan pemilik pada `CHANGELOG.md` (entri "Pengembangan fitur iteratif via sesi Claude") dan berkas log bukti `console.txt` **sengaja tidak di-commit** (tetap di working tree lokal) agar tidak mencampur perubahan pemilik dengan catatan fase.

---

## 1. Status Fase
- **Fase 00 — Audit baseline**: PARTIAL (source & environment audit selesai; browser NOT RUN).
- **Fase 01a — Authorization backend**: **IMPLEMENTED + KOREKSI TERAKHIR (5f18c06)**.
  - Verifikasi otomatis & UAT sintetis terdahulu PASS.
  - **Penerimaan manual pemilik**: **PENDING** (belum disetujui manual; instruksi commit/push bukan persetujuan manual implementasi).
- **Fase 01b/01c dan fase selanjutnya**: NOT STARTED.

---

## 2. Riwayat Koreksi Terakhir (`5f18c06`)
Koreksi pada commit `5f18c06` memisahkan identitas pengguna login dari daftar akun administratif:
1. `authStore.js`: identitas sesi dimuat melalui `fetchIdentity()` menggunakan kontrak ringan `GET /api/check-auth` (payload minimum), menghapus pemanggilan `GET /api/users` untuk mencari profil sendiri.
2. `ListUsersOnlineWidget.vue`: diproteksi `v-if="canViewUsers"`, tidak memanggil `/api/users` bila pengguna tidak memiliki izin `users.view`.
3. `AppUsers.vue` & `AppMenu.vue`: null-safe dan beralih ke gating kemampuan bernama (`requires`), menghapus pemeriksaan numerik inline `type_user_id`.
4. Logout dilakukan berbasis sesi backend (`POST /logout`), tidak bergantung pada data daftar pengguna; state sesi dibersihkan via `authStore.$reset()`.
5. `main.js`: directive PrimeVue `StyleClass` diregistrasikan (`app.directive('styleclass', StyleClass)`).
6. `apiLogin/index.js`: menghapus fallback diam-diam ke IP lama, menambahkan validasi `VITE_API_BASE_URL` dengan error English yang jelas, membedakan network error dari 401.
7. `Login_2.vue`: membedakan network error dari invalid credentials (422).
8. `package.json`: menambahkan script `"dev:uat"`.

---

## 3. Defect Baru Ditemukan Pemilik (PENDING — Belum Diperbaiki)
Saat pengujian manual login dengan akun `uat.operator`, pemilik menemukan defect post-login identity initialization:
- **Gejala visual**: Muncul modal dialog:
  - Header: `Authentication Error`
  - Pesan: `Failed to validate user session.`
- **Log console browser**:
  - `Error: User data not found.`
  - Stack trace mengarah ke fungsi `authenticated` di `Login_2.vue` (sekitar baris 144 / 165 / 173), dipanggil dari `submitLogin` (sekitar baris 229).
- **Perilaku pasca-error**: Setelah pengguna melakukan refresh halaman, pengguna berhasil masuk ke beranda / dashboard normal.
- **Status investigasi**: **Penyebab belum terbukti.** Dugaan mengenai ketidaksesuaian kontrak respons, race condition state, atau timing initialization hanya hipotesis awal dan belum diverifikasi.
- **Status pekerjaan**: **Prompt perbaikan defect ini BELUM dijalankan.** Sesi ditutup karena batas konteks (~600k token). Perbaikan defect ini menjadi **pekerjaan pertama sesi berikutnya**.

---

## 4. Bukti & Artefak yang Tersedia
- **Berkas bukti sintetis**:
  - `reports/feature-update/evidence/uat-roles-en.png` (tangkapan layar Roles & Abilities English)
  - `reports/feature-update/evidence/uat-operator-dashboard.png` (tangkapan layar dashboard operator)
  - `reports/feature-update/evidence/uat-operator-profile-dropdown.png` (tangkapan layar dropdown profil operator)
  - `reports/feature-update/evidence/uat-students-export.csv` (unduhan CSV siswa sintetis dari endpoint terproteksi)
- **Log bukti pemilik**:
  - `console.txt` (catatan error console awal dari pengujian operator oleh pemilik)
- **Hasil verifikasi otomatis terdahulu**:
  - PHPUnit test suite: **49 passed (119 assertions)** di database terisolasi SQLite in-memory.
  - Frontend build: `npm run build` PASS.
  - Pint style check: PASS pada seluruh berkas backend yang disentuh.
  - ESLint `Roles.vue`: 0 problems; berkas sentuhan lain 0 error.
- **Batas pengujian**:
  - Seluruh verifikasi berjalan pada database/data sintetis terisolasi (`school_access_uat` dan SQLite `:memory:`).
  - Modul surat, izin, dan pembelajaran belum tersedia (hanya katalog kemampuan).
  - Tidak ada pengujian pada database operasional.

---

## 5. Keputusan & Blocker Terbuka
- **DEC-03** (peran & kemampuan fleksibel): **APPROVED** (2026-10-15).
- **DEC-14** (register publik ditutup, status_active ditolak, ekspor via students.export, legacy mapping per nama, UI English): **APPROVED** (2026-10-14 WIB).
- **BLK-03**: **OPEN** (`DatabaseSeeder` masih memuat identitas asli; dilarang dijalankan untuk pengujian).
- **Keputusan autentikasi lain**: makna `status_user_id` (jangan disamakan dengan `status_active`), reset password end-to-end, bootstrap pengguna pertama, deployment CORS/Sanctum, strategi pencabutan token vs sesi: **OPEN**.
- **UI Language**: Seluruh antarmuka dalam scope tetap **English**.

---

## 6. Server & Lingkungan Aktif
Server UAT lokal yang teridentifikasi sedang berjalan (jangan dihentikan sembarangan agar tidak mengganggu proses pemilik):
- **API UAT**: port `8001` (PID 16124, `php artisan serve --host=127.0.0.1 --port=8001 --env=uat`)
- **SPA UAT**: port `4001` (PID 21372, `vite --mode uat --host 127.0.0.1 --port 4001`)
- **Database UAT**: MySQL `school_access_uat` (disposable, terpisah dari `backdata_administrations`)
- Server operasional pada port `8000` dibiarkan utuh.

---

## 7. Pekerjaan Pertama Sesi Baru
1. Investigasi & reproduksi defect post-login pada `Login_2.vue` (modal "Authentication Error — Failed to validate user session" / "User data not found").
2. Buktikan akar masalah dengan memeriksa alur `submitLogin` dan `authenticated` di `Login_2.vue` saat berinteraksi dengan `authStore` dan endpoint `/api/check-auth` / `/api/user`.
3. Perbaiki rantai inisialisasi identitas pasca-login secara terukur tanpa memanggil `/api/users`.
4. Verifikasi login ulang dengan akun `uat.operator`, `uat.admin`, `uat.kepsek`, dan `uat.waka` di browser UAT.
5. Perbarui dokumentasi dan siapkan retest manual pemilik.
