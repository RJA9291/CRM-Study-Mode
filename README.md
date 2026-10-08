# CRM Study Mode

CRM untuk pelajar: dashboard Profile, Task, To-do, ID & Password Locker, prestasi task, Chat Assistant (web + Telegram) dan Knowledge berasaskan folder Google Drive yang dijawab oleh Claude. Super Admin meluluskan pendaftaran pelajar baru.

Laravel 13 · Filament 5 · PHP 8.3 · MySQL (production) / SQLite (local)

## Panel

| URL | Siapa | Isi |
|---|---|---|
| `/app` | Pelajar yang diluluskan | Dashboard prestasi, Task, To-do List, ID & Password Locker, Chat Assistant, Profil. Pendaftaran di `/app/register`. |
| `/admin` | Super Admin | Pelajar & Pengguna (lulus/tolak), Task Pelajar (beri task kepada seorang atau ramai), Knowledge (Google Drive), Log Chat. |

- Pelajar baru berdaftar sebagai **Menunggu**; mereka tidak boleh log masuk sehingga Super Admin meluluskan.
- **Locker**: password & nota disulitkan dengan `APP_KEY` (cast `encrypted`). Hanya pemilik boleh lihat — Super Admin pun tidak. Untuk lihat/salin, pelajar sahkan password akaun CRM; locker terbuka 10 minit.
- **Jangan tukar `APP_KEY` di production** selepas ada data locker — semua password tersimpan tidak akan boleh dibaca lagi.

## Chat Assistant

Soalan dikendalikan oleh `App\Services\Assistant\StudentAssistant`:

- `/today`, "task hari ni", "nak buat apa harini" → task due/lewat + to-do hari ini (dari database).
- `/tasks` → semua task belum siap.
- Soalan lain → `KnowledgeAnswerService`: cari petikan paling relevan dari folder Drive yang aktif, kemudian Claude menjawab **hanya** berdasarkan petikan itu dan menyebut sumber.

Telegram: pelajar buka **Chat Assistant → Link Telegram**, tekan pautan `t.me/<bot>?start=<kod>` (sekali guna). WhatsApp boleh ditambah kemudian dengan melaksanakan `App\Services\Messaging\MessagingChannel`.

## Setup local

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Seeder mencipta Super Admin dari `SUPER_ADMIN_*` (jika password kosong, satu dijana dan dipaparkan sekali). Dalam `APP_ENV=local` ia juga mencipta pelajar demo `pelajar@example.com` (password `password`) dan seorang pelajar menunggu kelulusan.

Ujian: `php artisan test`

## Deploy ke Laravel Forge

1. **Site** baru → repo `RJA9291/CRM-Study-Mode`, branch `main`, database MySQL.
2. **Environment** (`.env`): set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<domain>`, `DB_*`, `QUEUE_CONNECTION=database`, dan semua kunci di bahagian *CRM Study Mode* dalam `.env.example`.
3. **Deploy script** (tambah selepas `composer install`):
   ```bash
   $FORGE_PHP artisan migrate --force
   $FORGE_PHP artisan storage:link || true
   $FORGE_PHP artisan optimize
   $FORGE_PHP artisan filament:optimize
   ```
4. **Queue worker** (Forge → Queue): connection `database`, timeout `1200` — diperlukan untuk sync Drive dan balasan Telegram.
5. **Scheduler** (Forge → Scheduler): `php artisan schedule:run` setiap minit — sync semua folder knowledge setiap hari jam 3 pagi.
6. Super Admin pertama: daftar akaun biasa di `/app/register`, kemudian jalankan `php artisan crm:make-admin <email>` (Forge → Commands). Alternatif: `php artisan db:seed --force` dengan `SUPER_ADMIN_*` diisi.
7. **Google Drive**: cipta service account di Google Cloud (aktifkan Drive API), muat naik kunci JSON ke `storage/app/private/google-service-account.json` di server, dan *Share* setiap folder knowledge kepada `client_email` service account itu (Viewer).
8. **Telegram**: cipta bot dengan @BotFather, isi `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`, kemudian jalankan `php artisan telegram:set-webhook` (webhook: `POST /api/telegram/webhook`).

Jenis fail Drive yang dibaca: Google Docs/Sheets/Slides, PDF, DOCX, TXT/MD/CSV. Fail lain disenaraikan tetapi tidak diindeks.
