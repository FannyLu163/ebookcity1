# PHP EbookCity

PHP version of the Mingri EbookCity prototype.

## Run locally

```powershell
cd "C:\Users\lsf11\Documents\New project\php-ebookcity"
php -S localhost:8080 -t public
```

Open `http://localhost:8080`.

## Database

Copy `.env.example` to `.env` and update the connection values.

If the database is unavailable, the site falls back to sample data so the UI can still be previewed.

