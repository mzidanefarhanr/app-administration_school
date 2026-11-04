# Laporan i18n-2 — Layout & Navigasi (Worktree A)

- **Branch:** `feature/i18n`
- **Parent SHA:** `24faadb5b24f2b4d47625508aa5286938eb241e1`
- **Dokumen Acuan:** [`docs/I18N.md`](file:///D:/xampp-htdocs/school-i18n/docs/I18N.md) dan [`reports/parallel/feature-i18n/GLOSSARY.md`](file:///D:/xampp-htdocs/school-i18n/reports/parallel/feature-i18n/GLOSSARY.md).

---

## 1. Yang Dikerjakan

1. **Namespace Terjemahan Baru:**
   - `vue-app/src/i18n/locales/{id,en}/menu.json`: 10 grup menu dan 29 item rute navigasi terdaftar lengkap dalam bahasa Indonesia dan English sesuai glosarium.
   - `vue-app/src/i18n/locales/{id,en}/layout.json`: Teks antarmuka topbar (tombol), user menu popup (`AppUsers`), panel konfigurasi (`AppConfigurator`), dan footer (`AppFooter`).
   - `vue-app/src/i18n/locales/{id,en}/common.json`: Penambahan state `processing` ("Memproses Permintaan" / "Processing Request").
2. **Navigasi Menu Reaktif (`vue-app/src/layout/AppMenu.vue`):**
   - Mengubah `model` dari `ref` statis menjadi `computed` yang memanggil `t('menu.group.*')` dan `t('menu.item.*')`.
   - Hasil: Seluruh label menu navigasi utama berubah seketika (*instant reactive*) saat bahasa diganti di panel konfigurasi tanpa perlu reload halaman.
3. **Topbar Tombol (`vue-app/src/layout/AppTopbar.vue`):**
   - Mematuhi aturan ketat **Rule 4.2**: Hanya mengubah teks tombol Notification (`layout.topbar.notifications`), Messages (`layout.topbar.messages`), dan Profile (`layout.topbar.profile`).
   - Area logo default dan nama merek tidak disentuh sama sekali.
4. **Menu Pengguna & Logout (`vue-app/src/layout/AppUsers.vue`):**
   - Label peran pengguna (`admin-sistem`, `operator-sekolah`, `kepala-sekolah`, `wakil-kesiswaan`, `guru`) kini diterjemahkan dinamis via `layout.userMenu.roles.*`.
   - Teks tombol Profile, Logout, dan dialog konfirmasi/waiting terhubung ke i18n.
5. **Panel Konfigurasi (`vue-app/src/layout/AppConfigurator.vue`):**
   - Menuntaskan sisa teks yang sebelumnya berbahasa Inggris ("Primary", "Surface", "Presets", "Menu Mode", "Static", "Overlay") menjadi terjemahan reaktif.
6. **Footer (`vue-app/src/layout/AppFooter.vue`):**
   - Teks kredit dan status lisensi diterjemahkan via `layout.footer.*`.
7. **Breadcrumbs Dinamis & Router (`vue-app/src/components/AutoBreadcrumb.vue` & `vue-app/src/router/index.js`):**
   - Menambahkan `breadcrumbKey` pada seluruh 29 rute utama di `router/index.js`.
   - Mengadaptasi `AutoBreadcrumb.vue` agar membaca `breadcrumbKey` i18n secara otomatis dengan fallback aman ke string breadcrumb asli.
   - Hasil: Jejak breadcrumb halaman (misal: "Home / Academic / Class Groups") langsung berganti bahasa secara reaktif saat locale berubah.

---

## 2. Perintah dan Hasil Uji

| Pengujian / Perintah | Hasil |
| :--- | :--- |
| **Unit Test i18n (`test_i18n2.mjs`)** | **ALL PASS** (76 kunci terdaftar di ID dan EN, paritas kunci 100%, seluruh 32 rute breadcrumbKey terverifikasi). |
| **ESLint Non-Mutasi (`npx eslint ...`)** | **0 error** (hanya peringatan CRLF format Windows standar). |
| **Frontend Production Build (`npm run build`)** | **SUKSES** (`✓ built in 34.25s`, zero bundling errors). |
| **Pemeriksaan Scope Bagian 9 `PARALLEL-WORK.md`** | **0 file out-of-scope** (tidak ada file milik Agen B yang tersentuh). |

---

## 3. Checklist Pengujian Manual untuk Pemilik

1. Jalankan `npm run dev` di `vue-app` (pastikan bukan port 18028 atau 14028).
2. Periksa sidebar menu: Dalam mode bahasa Indonesia, semua menu tampil dengan label Indonesia standar.
3. Buka panel konfigurasi (ikon palet floating), ganti bahasa ke **English**:
   - Seluruh nama grup menu dan item menu di sidebar langsung berubah ke bahasa Inggris (misal: *Beranda* $\rightarrow$ *Home*, *Rombongan Belajar* $\rightarrow$ *Class Groups*).
   - Breadcrumb di bagian atas halaman langsung berganti bahasa (misal: *Dasbor* $\rightarrow$ *Dashboard*).
   - Teks pada tombol topbar (*Notifications*, *Messages*, *Profile*) langsung berganti bahasa.
   - Popup menu pengguna (*Profile*, *Logout*, role name) berganti bahasa.
4. Ganti kembali ke **Indonesia**: Semua komponen layout kembali ke bahasa Indonesia seketika tanpa reload.

---

## 4. Berkas yang Diubah / Ditambahkan

* `vue-app/src/i18n/locales/id/menu.json` (baru)
* `vue-app/src/i18n/locales/en/menu.json` (baru)
* `vue-app/src/i18n/locales/id/layout.json` (baru)
* `vue-app/src/i18n/locales/en/layout.json` (baru)
* `vue-app/src/i18n/locales/id/common.json`
* `vue-app/src/i18n/locales/en/common.json`
* `vue-app/src/layout/AppMenu.vue`
* `vue-app/src/layout/AppTopbar.vue`
* `vue-app/src/layout/AppUsers.vue`
* `vue-app/src/layout/AppFooter.vue`
* `vue-app/src/layout/AppConfigurator.vue`
* `vue-app/src/components/AutoBreadcrumb.vue`
* `vue-app/src/router/index.js`
* `reports/parallel/feature-i18n/REPORT-i18n-2.md` (laporan ini)

---

## 5. Status Permintaan & Izin

* **REQUESTS.md:** Tidak ada permintaan baru kepada Agen B / Pemilik.
* **Persetujuan Manual:** Belum ada (dicatat terpisah).
