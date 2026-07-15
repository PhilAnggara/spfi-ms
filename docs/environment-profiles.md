# Environment Profiles — SPFI-MS

Referensi cepat untuk menyesuaikan `.env` saat pindah device atau lokasi.

> **Penting:** Jangan commit file `.env`. Copy nilai dari template di bawah ke `.env` Anda.
> Lihat juga [`.env.example`](../.env.example) untuk daftar lengkap variabel.

---

## Ringkasan 3 Skenario

| Skenario | Device | Lokasi | `DB_PROFILE` | `SEED_SOURCE` | Database dev | Snapshot |
|----------|--------|--------|--------------|---------------|--------------|----------|
| **Rumah** | Laptop | Offline / tidak ada jaringan kantor | `1` | `local` | MySQL `spfi_ms` | `storage/app/db-snapshots/*.sql` |
| **Kantor** | Laptop | Terhubung jaringan kantor | `1` | `legacy` | MySQL `spfi_ms` | `storage/app/db-snapshots/*.sql` |
| **Kantor** | PC | Terhubung jaringan kantor | `2` | `legacy` | SQL Server `spfi_ms` | `\\192.168.11.250\database\spfi_ms\*.bak` |

**Catatan snapshot:**
- Snapshot **tidak bisa ditukar** antar driver (`.sql` MySQL ≠ `.bak` SQL Server).
- Snapshot **tidak bisa ditukar** antar skenario data berbeda (CSV vs legacy) — buat snapshot baru setelah seed.
- Database **legacy kantor** (`b12d4a36`, dll.) **tidak** di-backup — hanya database dev `spfi_ms`.

---

## Skenario 1 — Laptop Rumah (MySQL + CSV)

Gunakan saat di rumah, tidak terhubung ke server legacy kantor.

```env
DB_PROFILE=1

DB_MYSQL_HOST=127.0.0.1
DB_MYSQL_PORT=3306
DB_MYSQL_DATABASE=spfi_ms
DB_MYSQL_USERNAME=root
DB_MYSQL_PASSWORD=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spfi_ms
DB_USERNAME=root
DB_PASSWORD=

SEED_SOURCE=local
SEED_SOURCE_FALLBACK_TO_LOCAL=true
```

**Snapshot — tidak perlu `SQLSERVER_SNAPSHOT_PATH`.** Opsional jika auto-detect gagal:

```env
MYSQL_BIN_PATH=C:\xampp\mysql\bin
```

**Setup pertama:**
```bash
php artisan config:clear
php artisan test --filter=DatabaseIsolationTest
php artisan migrate:fresh --seed
php artisan db:snapshot
```

---

## Skenario 2 — Laptop Kantor (MySQL + Legacy)

Gunakan saat laptop di kantor, terhubung jaringan, seed langsung dari sistem lama.

```env
DB_PROFILE=1

DB_MYSQL_HOST=127.0.0.1
DB_MYSQL_PORT=3306
DB_MYSQL_DATABASE=spfi_ms
DB_MYSQL_USERNAME=root
DB_MYSQL_PASSWORD=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=spfi_ms
DB_USERNAME=root
DB_PASSWORD=

SEED_SOURCE=legacy
SEED_SOURCE_FALLBACK_TO_LOCAL=true
LEGACY_DB_DEFAULT_CONNECTION=legacy_sqlsrv_1

LEGACY_DB1_HOST=192.168.11.250
LEGACY_DB1_PORT=1433
LEGACY_DB1_DATABASE=b12d4a36
LEGACY_DB1_USERNAME=sa
LEGACY_DB1_PASSWORD=YourPasswordHere
LEGACY_DB1_ENCRYPT=no
LEGACY_DB1_TRUST_SERVER_CERTIFICATE=true

# ... isi LEGACY_DB2, LEGACY_DB3, LEGACY_DB4 sesuai .env kantor Anda
```

**Snapshot — tidak perlu `SQLSERVER_SNAPSHOT_PATH`** (MySQL lokal di laptop).

**Setup pertama:**
```bash
php artisan config:clear
php artisan test --filter=DatabaseIsolationTest
php artisan migrate:fresh --seed
php artisan db:snapshot
```

---

## Skenario 3 — PC Kantor (SQL Server + Legacy)

Gunakan di PC kantor dengan database dev SQL Server.

```env
DB_PROFILE=2

DB_SQLSRV_HOST=192.168.11.250
DB_SQLSRV_PORT=1433
DB_SQLSRV_DATABASE=spfi_ms
DB_SQLSRV_USERNAME=sa
DB_SQLSRV_PASSWORD=YourPasswordHere
DB_SQLSRV_ENCRYPT=no
DB_SQLSRV_TRUST_SERVER_CERTIFICATE=true

DB_CONNECTION=sqlsrv
DB_HOST=192.168.11.250
DB_PORT=1433
DB_DATABASE=spfi_ms
DB_USERNAME=sa
DB_PASSWORD=YourPasswordHere

SEED_SOURCE=legacy
SEED_SOURCE_FALLBACK_TO_LOCAL=true
LEGACY_DB_DEFAULT_CONNECTION=legacy_sqlsrv_1

LEGACY_DB1_HOST=192.168.11.250
LEGACY_DB1_PORT=1433
LEGACY_DB1_DATABASE=b12d4a36
LEGACY_DB1_USERNAME=sa
LEGACY_DB1_PASSWORD=YourPasswordHere
LEGACY_DB1_ENCRYPT=no
LEGACY_DB1_TRUST_SERVER_CERTIFICATE=true

# ... isi LEGACY_DB2, LEGACY_DB3, LEGACY_DB4 sesuai kebutuhan

# Wajib untuk SQL Server remote — path di server, bukan di PC Anda
SQLSERVER_SNAPSHOT_PATH=\\192.168.11.250\database\spfi_ms
```

**Setup pertama:**
```bash
php artisan config:clear
php artisan test --filter=DatabaseIsolationTest
php artisan migrate:fresh --seed
php artisan db:snapshot
php artisan db:snapshots
```

---

## Workflow Sehari-hari (Semua Skenario)

```bash
# Setelah migrate:fresh --seed (hanya jika ada perubahan struktur tabel)
php artisan db:snapshot

# Saat database dev kosong
php artisan db:restore

# Lihat daftar snapshot
php artisan db:snapshots
```

---

## Checklist Pindah Device

### PC → Laptop (pulang ke rumah)

1. `git push` dari PC (jika ada perubahan kode)
2. Di laptop: `git pull`
3. Ubah `.env` ke **Skenario 1** (Rumah)
4. `php artisan config:clear`
5. `php artisan db:restore` (jika sudah pernah snapshot di laptop) **atau** `migrate:fresh --seed` (jika belum ada snapshot)

### Laptop → PC (masuk kantor)

1. `git push` dari laptop (jika ada perubahan kode)
2. Di PC: `git pull`
3. Ubah `.env` ke **Skenario 3** (PC Kantor)
4. `php artisan config:clear`
5. `php artisan db:restore` (jika sudah pernah snapshot di PC) **atau** `migrate:fresh --seed`

### Laptop di kantor (pakai legacy, bukan CSV)

1. Ubah `.env` ke **Skenario 2** (Laptop Kantor)
2. `php artisan config:clear`
3. `migrate:fresh --seed` → `db:snapshot`

### Refresh CSV fallback (kantor, sebelum pulang ke rumah)

Gunakan saat ingin memperbarui file CSV offline di `public/csv/` agar `SEED_SOURCE=local` di rumah memakai data terbaru.

```bash
# Pastikan legacy SQL Server reachable (Skenario 2 atau 3)
php artisan config:clear
php artisan legacy:export-csv

# Verifikasi CSV masih valid untuk seeding
php artisan migrate:fresh --seed

# Opsional: snapshot dev DB (lebih praktis untuk restore di rumah)
php artisan db:snapshot
```

Export dataset tertentu saja:

```bash
php artisan legacy:export-csv --only=supplier,po,po_detail,rr,rr_detail
php artisan legacy:export-csv --list
php artisan legacy:export-csv --dry-run
```

Menambah tabel legacy baru: tambah entry di `config/legacy_import.php` → buat seeder → `legacy:export-csv --only=nama_dataset`.

**Git:** file GL (`tbl_DocTran*.csv`) sangat besar — hindari commit ke repository.

---

## Verifikasi Keamanan

Pastikan database dev dan legacy **berbeda nama**:

```env
DB_SQLSRV_DATABASE=spfi_ms          # database dev app
LEGACY_DB1_DATABASE=b12d4a36       # sistem lama — BUKAN spfi_ms
```

Test isolasi (wajib pass setelah setup):

```bash
php artisan test --filter=DatabaseIsolationTest
```

Harus **PASS** — artinya `php artisan test` tidak akan mengosongkan database dev Anda.

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| `db:snapshot` gagal cari mysqldump | Set `MYSQL_BIN_PATH=C:\xampp\mysql\bin` |
| `SQLSERVER_SNAPSHOT_PATH` required | Wajib di PC kantor (Skenario 3) |
| `filesize(): stat failed` | Pastikan `SQLSERVER_SNAPSHOT_PATH` mengarah ke share server, bukan folder lokal PC |
| Test menghapus database dev | Jalankan `php artisan config:clear`, pastikan `DatabaseIsolationTest` pass |
| Snapshot lama gagal restore | Ada migration baru — jalankan `migrate:fresh --seed` lalu `db:snapshot` lagi |
