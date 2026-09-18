# SGC SmartTrack (production)

DepEd School Governance Council Functionality Assessment for **SDO Cadiz City**.

This is the Laravel production codebase. Single SDO (not multi-tenant). Schools sign in at one domain; they do not get subdomains.

The clickable HTML prototype stays in the separate `sgc-smarttrack` repo / Vercel site.

## Stack

- Laravel 12
- Inertia.js 2
- Vue 3 (Composition API)
- TypeScript
- Tailwind CSS
- Vite

## Local setup

```bash
cd sgc-smarttrack-prod
composer install
copy .env.example .env
php artisan key:generate
npm install
```

PHP on this machine needs the **pdo_sqlite** extension for local SQLite, or point `.env` at MySQL:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sgc_smarttrack
DB_USERNAME=root
DB_PASSWORD=
```

Then:

```bash
php artisan migrate
npm run dev
php artisan serve
```

Or `composer run dev` if you want the app, queue, and Vite together.

## Roles (assigned, not chosen at login)

- `super` — system
- `division` — SDO Cadiz City / SGC Focal
- `school` — one school under Cadiz

Domain types live in `resources/js/types/sgc.ts`.
