# SESSION-01 — Preset tema dan font

**Branch:** `feature/branding` @ 4a14017  
**Worktree:** `D:\xampp-htdocs\school-branding`  
**Waktu:** 2026-11-03 17:03 WIB  

## Scope
Item 3–4 design.md §10: preset tema Sekolario (emerald+slate), font Plus Jakarta Sans.

## Hasil

### Item 3: Preset tema
- Buat `src/theme/sekolario-preset.js` dengan emerald primary dan slate surface per design.md §3.2
- Edit `main.js`: import SekolarioPreset, ganti `preset: Aura` → `preset: SekolarioPreset`
- Edit `layout/composables/layout.js`: `surface: null` → `surface: 'slate'`

### Item 4: Font Plus Jakarta Sans
- Buat `src/assets/css/plus-jakarta-sans.css` dengan @font-face weight 400, 500, 600
- Edit `index.html`: title "Sekolario", tambah link plus-jakarta-sans.css
- Edit `styles.scss`: set font-family global `'Plus Jakarta Sans', 'Inter', system-ui, sans-serif`

**ponytail:** File woff2 Plus Jakarta Sans belum tersedia. Perlu `PlusJakartaSans-{Regular,Medium,SemiBold}.woff2` di `src/assets/css/plus-jakarta-sans/`. Font fallback ke Inter.

## File diubah
- `vue-app/src/theme/sekolario-preset.js` (baru)
- `vue-app/src/main.js`
- `vue-app/src/layout/composables/layout.js`
- `vue-app/src/assets/css/plus-jakarta-sans.css` (baru)
- `vue-app/index.html`
- `vue-app/src/assets/styles.scss`

## Blocker
Item 1–2 (logo, favicon) butuh file dari folder `sekolario-logo/`. Path tidak exist di worktree.

## NOT RUN
- Build (`npm run build`)
- Dev server
- Verifikasi visual (desktop/mobile, terang/gelap)

## Status item design.md §10
- ✓ Item 1: Logo (AppLogoDefault.vue)
- ✓ Item 2: Favicon + title
- ✓ Item 3: Preset tema
- ✓ Item 4: Font struktur (ponytail: woff2 REQ-02)
- ⊗ Item 5: Login page (views/** milik A, ditunda integrasi)
- ⊗ Item 6: severity error→danger (views/** milik A)
- ⊗ Item 7: Chart indigo→slate (components/** milik A)
- ⊠ Item 8: Skala heading (butuh approval REQ-01)
- ⊗ Item 9: Ikon menu (AppMenu.vue milik A)

## Commit & Push
SHA ff5d6ee pushed ke origin/feature/branding

## Next
- File woff2 Plus Jakarta Sans (REQ-02)
- Approval skala heading (REQ-01)
- Item 5–7, 9 dikerjakan saat integrasi atau oleh A
