# Environment Profiles — SPFI-MS

Referensi cepat untuk menyesuaikan `.env` saat pindah device atau lokasi.

> **Penting:** Jangan commit file `.env`. Copy nilai dari template di bawah ke `.env` Anda.
> Lihat juga [`.env.example`](../.env.example) untuk daftar lengkap variabel.

---

## Ringkasan 3 Skenario

| Skenario | Device | Lokasi | `DB_PROFILE` | Database dev | Sumber data |
|----------|--------|--------|--------------|--------------|-------------|
| **Rumah** | Laptop | Offline / tidak ada jaringan kantor | `1` | MySQL `spfi_ms` | Snapshot / data yang di-pull di kantor |
| **Kantor** | Laptop | Terhubung jaringan kantor | `1` | MySQL `spfi_ms` | `db:pull-production` dari SQL Server `spfi_ms` |
| **Kantor** | PC | Terhubung jaringan kantor | `2` | SQL Server `spfi_ms_dev` | `db:pull-production` dari SQL Server `spfi_ms` |

**Catatan snapshot:**
- Snapshot **tidak bisa ditukar** antar driver (`.sql` MySQL ≠ `.bak` SQL Server).
- Database **production** (`spfi_ms` di SQL Server) **tidak** di-backup oleh `db:snapshot` — hanya database development.
- Database **legacy kantor** (`b12d4a36`, dll.) **tidak** di-backup.

---

## Pull production → development

Jalankan **hanya di kantor** (production SQL Server harus reachable). Schema target harus sudah up-to-date (`php artisan migrate`) sebelum pull — command ini mengganti **data**, bukan membuat ulang schema.

```bash
php artisan migrate
php artisan db:pull-production
```

- `DB_PROFILE=2` (PC kantor): production `spfi_ms` → SQL Server `spfi_ms_dev`
- `DB_PROFILE=1` (laptop di kantor): production `spfi_ms` → MySQL lokal `spfi_ms`, lalu dibawa pulang

Opsi:

```bash
php artisan db:pull-production --dry-run
php artisan db:pull-production --force
php artisan db:pull-production --chunk=500
```

`--force` melewati konfirmasi. `--dry-run` hanya menampilkan daftar tabel dan jumlah baris.

Setelah pull, opsional: `php artisan db:snapshot` agar development bisa di-restore tanpa pull ulang.

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

## Skenario 2 — Laptop Kantor (MySQL + Production)

Gunakan saat laptop di kantor, terhubung jaringan, untuk menyalin data production ke MySQL lokal sebelum pulang.

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

PROD_DB_HOST=192.168.11.250
PROD_DB_PORT=1433
PROD_DB_DATABASE=spfi_ms
PROD_DB_USERNAME=sa
PROD_DB_PASSWORD=YourPasswordHere
PROD_DB_ENCRYPT=no
PROD_DB_TRUST_SERVER_CERTIFICATE=true
```

**Snapshot — tidak perlu `SQLSERVER_SNAPSHOT_PATH`** (MySQL lokal di laptop).

**Setup pertama:**
```bash
php artisan config:clear
php artisan test --filter=DatabaseIsolationTest
php artisan migrate
php artisan db:pull-production
php artisan db:snapshot
```

---

## Skenario 3 — PC Kantor (SQL Server + Production)

Gunakan di PC kantor dengan database development SQL Server `spfi_ms_dev` (bukan production `spfi_ms`).

```env
DB_PROFILE=2

DB_SQLSRV_HOST=192.168.11.250
DB_SQLSRV_PORT=1433
DB_SQLSRV_DATABASE=spfi_ms_dev
DB_SQLSRV_USERNAME=sa
DB_SQLSRV_PASSWORD=YourPasswordHere
DB_SQLSRV_ENCRYPT=no
DB_SQLSRV_TRUST_SERVER_CERTIFICATE=true

DB_CONNECTION=sqlsrv
DB_HOST=192.168.11.250
DB_PORT=1433
DB_DATABASE=spfi_ms_dev
DB_USERNAME=sa
DB_PASSWORD=YourPasswordHere

PROD_DB_HOST=192.168.11.250
PROD_DB_PORT=1433
PROD_DB_DATABASE=spfi_ms
PROD_DB_USERNAME=sa
PROD_DB_PASSWORD=YourPasswordHere
PROD_DB_ENCRYPT=no
PROD_DB_TRUST_SERVER_CERTIFICATE=true

# Wajib untuk SQL Server remote — path di server, bukan di PC Anda
SQLSERVER_SNAPSHOT_PATH=\\192.168.11.250\database\spfi_ms_dev
```

**Setup pertama:**
```bash
php artisan config:clear
php artisan test --filter=DatabaseIsolationTest
php artisan migrate
php artisan db:pull-production
php artisan db:snapshot
php artisan db:snapshots
```

---

## Workflow Sehari-hari (Semua Skenario)

```bash
# Di kantor: samakan data development dengan production
php artisan migrate
php artisan db:pull-production

# Opsional: simpan salinan development
php artisan db:snapshot

# Saat database dev kosong (rumah / PC)
php artisan db:restore

# Lihat daftar snapshot
php artisan db:snapshots
```

---

## Checklist Pindah Device

### PC → Laptop (pulang ke rumah)

1. Di laptop **sebelum pulang** (masih di jaringan kantor): `.env` Skenario 2, lalu `php artisan migrate` → `php artisan db:pull-production` → `php artisan db:snapshot`
2. `git push` dari PC (jika ada perubahan kode)
3. Di rumah: `git pull`, `.env` Skenario 1, `php artisan config:clear`
4. `php artisan db:restore` jika snapshot laptop sudah ada

### Laptop → PC (masuk kantor)

1. `git push` dari laptop (jika ada perubahan kode)
2. Di PC: `git pull`
3. Ubah `.env` ke **Skenario 3** (PC Kantor)
4. `php artisan config:clear`
5. `php artisan migrate` → `php artisan db:pull-production`

### Laptop di kantor (refresh MySQL dari production)

1. Ubah `.env` ke **Skenario 2** (Laptop Kantor)
2. `php artisan config:clear`
3. `php artisan migrate` → `php artisan db:pull-production` → `php artisan db:snapshot`

---

## Verifikasi Keamanan

Production dan development SQL Server **harus berbeda nama**:

```env
PROD_DB_DATABASE=spfi_ms           # production — sumber pull, jangan dijadikan target
DB_SQLSRV_DATABASE=spfi_ms_dev     # database development di kantor
```

`db:pull-production` menolak menulis ke production, ke koneksi `legacy_*`, dan ke `APP_ENV=production`.

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
| Snapshot lama gagal restore | Ada migration baru — jalankan `migrate` lalu `db:pull-production` lalu `db:snapshot` lagi |
| `db:pull-production` menolak target | Pastikan `DB_SQLSRV_DATABASE` bukan `spfi_ms` (harus `spfi_ms_dev`) dan `PROD_DB_*` terisi |
| Schema mismatch saat pull | Jalankan `php artisan migrate` di target sebelum `db:pull-production` |

