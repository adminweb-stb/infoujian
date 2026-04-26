# 🚀 Alur Kerja Development → Production

## Stack
- **Lokal:** Laragon (PHP + MariaDB) di `http://localhost/ujian/public/`
- **Repo:** GitHub `adminweb-stb/infoujian`
- **VPS:** Biznet `103.197.191.237:22002` — AAPanel + Docker
- **Live:** `https://ujian.satyaterrabhinneka.ac.id`

---

## 📁 File Penting

| File | Fungsi |
|---|---|
| `.env` | Konfigurasi DB lokal (jangan di-push) |
| `core/db.php` | Koneksi DB (baca dari .env) |
| `public/index.html` | Halaman mahasiswa (publik) |
| `public/assets/css/style.css` | Stylesheet utama |
| `public/panel/index.php` | Backend API + Auto-sync JSON |
| `public/panel/manage_schedules.php` | UI manajemen jadwal |
| `public/panel/sync.php` | Generator file JSON dari DB |
| `biznet_vps_helper.py` | Helper SSH/SFTP ke VPS |
| `deploy.py` | **Script deploy 1 tombol** |
| `migrate_db.py` | Migrasi DB lokal → VPS (1x pakai) |

---

## 🔄 Workflow Harian (Setelah Development)

### Langkah 1 — Test Lokal
```
http://localhost/ujian/public/          → Portal mahasiswa
http://localhost/ujian/public/panel/    → Admin panel
```

### Langkah 2 — Deploy ke GitHub + VPS
```bash
python deploy.py
```
Script ini otomatis akan:
- `git add .` + `git commit` + `git push` ke GitHub
- Upload file yang berubah ke VPS via SFTP
- Tidak menyentuh `.env` dan `public/data/*.json` di VPS

### Langkah 3 — Verifikasi Live
```
https://ujian.satyaterrabhinneka.ac.id
```

---

## 🗄️ Migrasi Database (Jika ada perubahan skema/data besar)
```bash
python migrate_db.py
```
> ⚠️ Hati-hati: ini akan **menimpa** database VPS dengan data lokal!

---

## 🔑 Akses Penting

| Layanan | URL / Info |
|---|---|
| AAPanel | `https://103.197.191.237:15805/006ad8f8` |
| phpMyAdmin VPS | `http://103.197.191.237:8080` |
| SSH VPS | `ssh -i vps-new-pdsi.pem -p 22002 pdsi-admin@103.197.191.237` |
| Docker ujian | `ujian_php` + `ujian_nginx` (port 8085) |
| DB Container | `global_mariadb` — DB: `stb_ujian_db` |

---

## 📦 File yang TIDAK boleh di-push ke GitHub
```
.env                    ← password database lokal
*.backup.css / .html    ← file cadangan desain
*.py                    ← script helper
public/data/*.json      ← data jadwal (milik VPS)
backups/                ← backup database
```

---

## 🛠️ Troubleshooting Cepat

### Jadwal baru tidak muncul di website mahasiswa?
→ Edit + Save jadwal apapun di Admin Panel (akan trigger auto-sync JSON)

### Website VPS tidak update setelah deploy?
```bash
python deploy.py
```

### Tombol ujian tidak terbuka padahal sudah waktunya?
→ Website sudah ada **Auto-Unlock Radar** setiap 30 detik. Tunggu maks 30 detik.

### SSL expired?
→ AAPanel → Website → ujian.satyaterrabhinneka.ac.id → SSL → Renew
