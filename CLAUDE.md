# CLAUDE.md — Inventory Assets

File konteks proyek untuk Claude / agent AI coding. Baca seluruhnya sebelum mengerjakan task apa pun.
Status per 19 Nov 2026: Hari 1–3 selesai (skema, model, `visibleTo`, Policy, middleware, seeder demo + riwayat, CI GitHub Actions dengan PostgreSQL 17, README versi, sidebar per role + shared props `auth.user`/`can`). Task berikutnya: Hari 4. Flash pesan memakai mekanisme bawaan starter kit: `Inertia::flash('toast', ['type' => 'success'|'error', 'message' => ...])`. Akun demo: `admin@demo.test`, `staff@demo.test`, `employee@demo.test` (password `password`, lihat `DemoSeeder`).

## Dokumen acuan (sumber kebenaran)

Kalau ada konflik antara kode dan dokumen, ikuti dokumen lalu tanyakan ke Ega.

- PRD: https://claude.ai/artifact/PwXcTvDXcinW6oM7V2ddEw
- Arsitektur: https://claude.ai/artifact/Yaqa4jzx9zPm5CGd71reds
- Skema Database: https://claude.ai/artifact/FECtjwZWHCoQW6hRsknWD2
- Kontrak API: https://claude.ai/artifact/1M2gvySGDmZ3cEzdguwgvn
- Breakdown Task: https://claude.ai/artifact/3yuEQj4pMrkDjuuhH7Sg5i
- Risiko & Pertanyaan Terbuka: https://claude.ai/artifact/3zAv2bqtvrtfTyDBxyBrAL

## Ringkasan produk

- Aplikasi web untuk mencatat dan mengelola aset satu organisasi (~100 user, ~1.000 aset), dengan chatbot read-only yang menjawab sesuai hak akses penggunanya.
- Tujuan: demo/portfolio. Dikerjakan solo oleh Ega. Target MVP: 10 hari kerja + buffer (total 2 minggu).
- Demo tidak dibuka publik (ditunjukkan langsung).
- UI berbahasa English. Chatbot menjawab dalam bahasa pertanyaan (Indonesia atau English).

## Stack (final)

- Laravel 13 + starter kit React resmi (Inertia 3, React 19, TypeScript, Tailwind 4, shadcn/ui). Monolith, bukan SPA terpisah.
  - Versi terpasang (19 Nov 2026): Laravel 13.43, Inertia 3 (`inertiajs/inertia-laravel` ^3.0, `@inertiajs/react` ^3.0), React 19.2, PHP 8.4, PostgreSQL 17.10. Starter kit dari `dev-main` (rilis stabil Packagist masih Laravel 12).
  - Fitur auth starter kit: hanya login + reset password (registrasi, verifikasi email, 2FA, passkeys dibuang).
- PostgreSQL (dev: `inventory_assets`, test: `inventory_assets_test`; test tidak memakai SQLite).
- spatie/laravel-permission (role), spatie/laravel-activitylog (audit log).
- LLM: `laravel/ai` (SDK resmi) di balik interface `LlmClient` (`app/Chat/LlmClient.php`). Provider: Gemini `gemini-3.5-flash-lite` (free tier), diatur lewat `LLM_PROVIDER`/`LLM_MODEL`. Final setelah spike Hari 1 (lulus 14/15). Prism tidak dipakai.
- Hosting: 1 VPS 1–2GB region Singapura, Nginx + PHP-FPM + PostgreSQL, tanpa Docker. Budget infra Rp150rb/bulan, domain terpisah.
- **Urutan kerja: aplikasi harus berjalan normal di lokal dulu. Deploy ke VPS baru di Hari 10.**

## Role & hak akses

Satu user = satu role. Role di-seed: `admin`, `warehouse_staff`, `employee`.

| Kemampuan | Admin | Staff Gudang | Karyawan |
| --- | --- | --- | --- |
| Kelola user & role | Ya | Tidak | Tidak |
| Lihat aset | Semua | Semua | Hanya yang sedang dia pinjam |
| CRUD aset, kategori, lokasi | Ya | Ya | Tidak |
| Catat pinjam/kembali, mutasi, maintenance | Ya | Ya | Tidak |
| Riwayat pinjaman | Semua | Semua | Hanya miliknya |
| Audit log | Ya | Tidak | Tidak |
| Chatbot | Scope penuh | Scope operasional | Hanya data pinjaman sendiri |

Peminjaman dicatat langsung oleh Staff/Admin, tanpa pengajuan atau approval dari Karyawan. Tidak ada registrasi publik.

## Scope

**Wajib MVP:** auth, manajemen user (Admin), kategori, lokasi, aset (kode otomatis), peminjaman & pengembalian (+ penanda terlambat), mutasi lokasi, maintenance log, audit log, dashboard per role, chatbot read-only, seeder data demo + 3 akun demo.

**Stretch (hanya setelah semua item wajib selesai):** depresiasi garis lurus, export CSV, UI bilingual, riwayat chat tersimpan, streaming chat.

**Nanti:** chatbot yang bisa melakukan aksi, approval peminjaman, reminder maintenance, QR code, upload foto/dokumen, analitik mendalam.

**Non-goals:** multi-tenant, text-to-SQL, mobile native, integrasi ERP, role kustom dari UI.

Jangan menambah fitur di luar daftar "Wajib MVP" tanpa persetujuan Ega.

## Aturan arsitektur (WAJIB)

1. **Satu sumber kebenaran untuk akses data.** Controller dan tool chatbot memanggil Service. Service memakai scope `visibleTo($actor)` untuk semua pembacaan data yang dibatasi. Dilarang query Model langsung dari controller atau tool untuk data yang dibatasi.
2. **Dua mekanisme RBAC:** Policy untuk izin aksi (create/update/delete/view per objek), query scope `visibleTo` untuk filter baris (list, search, agregasi, tools chatbot).
3. Scope Karyawan: `Loan::visibleTo` = `borrower_id = user`; `Asset::visibleTo` = aset dengan pinjaman aktif milik user.
4. Perubahan `assets.status` dan `assets.location_id` hanya lewat Service, di dalam DB transaction, bersamaan dengan pencatatan transaksinya.
5. Data di luar scope dibalas 404 (bukan 403), supaya Karyawan tidak tahu data itu ada.
6. Struktur folder: `app/Services/*`, `app/Policies/*`, `app/Chat/{ChatOrchestrator, LlmClient, Tools/*}`, `resources/js/pages/*`.

## Chatbot

- Read-only. Permintaan tambah/ubah/hapus/pinjam ditolak dengan sopan dan diarahkan ke halaman terkait.
- LLM tidak pernah menyentuh database. LLM hanya memilih tool. Tool menjalankan Service dengan `$actor` dari **session**.
- Jangan pernah menerima `user_id` atau identitas dari argumen LLM. Untuk Karyawan, filter peminjam selalu dipaksa ke dirinya sendiri.
- Daftar tool yang dikirim ke LLM disesuaikan role; scope di Service tetap berlaku sebagai lapis kedua.
- Enam tool: `search_assets`, `get_asset_detail`, `list_active_loans`, `get_loan_history`, `get_maintenance_history` (tidak untuk Karyawan), `get_inventory_summary`.
- Batasan: hasil tool maks 20 baris, maks 3 putaran tool per pertanyaan, timeout LLM ~20 detik, input maks 500 karakter, riwayat maks 6 pesan (dikirim client, tidak disimpan server), rate limit 20 pesan/jam per user.
- Output tool tidak pernah berisi: email user lain, password/token, `purchase_price` untuk Karyawan, ID internal database.
- Endpoint: `POST /chat/messages` → `{ reply, links[], meta{tool_calls, truncated} }`. `links` dibentuk server dari hasil tool, bukan ditulis LLM.
- Error JSON: `{ "error": { "code", "message" } }` — 422 `validation_failed`, 429 `rate_limited` (+ `retry_after`), 502 `llm_error`, 504 `llm_timeout`.
- Setiap request (termasuk yang gagal) dicatat di `chat_logs`.

## Database (ringkas)

Konvensi: PK bigint, `timestamptz`, status sebagai `varchar` + CHECK + PHP backed enum (bukan enum native Postgres), uang `numeric(14,2)` Rupiah, FK `ON DELETE RESTRICT`. Soft delete hanya di `assets`, `categories`, `locations`, `users`. Tabel transaksi append-only.

Tabel bisnis: `users` (+ `is_active`, `last_login_at`), `categories` (`code` prefix, `last_sequence`), `locations`, `assets`, `loans`, `asset_movements`, `maintenances`, `chat_logs`, plus `activity_log` dan tabel role dari package spatie.

- Status aset: `available`, `borrowed`, `maintenance`, `broken`, `disposed` (final). Kondisi: `good`, `fair`, `poor`.
- Kode aset: `{KODE_KATEGORI}-{4 digit}` (contoh `LAP-0001`), nomor urut per kategori, dibuat dalam transaction dengan `SELECT ... FOR UPDATE` pada baris kategori. Kode dan kategori aset tidak bisa diubah setelah dibuat.
- Partial unique index: 1 pinjaman aktif per aset (`returned_at IS NULL`), 1 maintenance `in_progress` per aset, `serial_number` unik bila diisi.
- Status "terlambat" dihitung dari `due_date`, tidak disimpan. Nilai buku (depresiasi) dihitung saat dibaca.
- Transisi status: pinjam `available→borrowed`; kembali `borrowed→available` (atau `broken`); maintenance dari `available`/`broken` → `maintenance` → `available`; dispose dari status apa pun kecuali `borrowed`; mutasi diblokir saat `borrowed`.

## Kontrak route (ringkas)

Semua di `routes/web.php`, auth session + CSRF, middleware `auth` + `active` + `role:`. Aksi tulis via Inertia (redirect + flash). Pagination 20 per halaman. 403 untuk role/Policy, 404 untuk di luar scope, 409 untuk konflik status. Daftar lengkap 32 route ada di dokumen Kontrak API.

## Aturan kerja untuk agent

- Kerjakan satu task kecil per commit/PR. Ega mereview manual sebelum lanjut, terutama Policy, scope, dan tools chatbot.
- Setiap fitur wajib punya feature test untuk 3 role, termasuk kasus ditolak.
- Setiap aksi tulis harus tercatat di audit log.
- Jangan mengubah migration yang sudah dijalankan; buat migration baru.
- Jangan menambah dependency baru tanpa alasan yang ditulis di PR.
- Kalau ragu soal requirement, tanya Ega. Jangan mengarang perilaku.
- Definition of done: jalan di lokal dengan data seed penuh, test 3 role lulus, audit log tercatat, tampilan layak di laptop dan HP.

## Hal yang masih terbuka

- Tanggal mulai Hari 1.
- Provider/paket VPS final dan nama domain (dibutuhkan Hari 10).
- Angka kuota free tier Gemini dicek langsung di Google AI Studio.
