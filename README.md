<div align="center">

# سعرها — Saerha

### منصة الخدمات الطبية في المملكة العربية السعودية

**Saudi Arabia's medical services marketplace** — search, compare, and book the best clinics and medical services across the Kingdom.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Vite](https://img.shields.io/badge/Vite-7-646CFF?logo=vite&logoColor=white)](https://vite.dev)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

---

## 📖 Table of Contents

- [Overview](#-overview)
- [Dashboards & URLs](#-dashboards--urls)
- [Test Credentials](#-test-credentials)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Architecture](#-architecture)
- [Quick Start](#-quick-start)
- [API Documentation](#-api-documentation)
- [Project Structure](#-project-structure)
- [Bilingual Support](#-bilingual-support)
- [Routes Map](#-routes-map)
- [Roadmap](#-roadmap)
- [License](#-license)

---

## 🎯 Overview

**Saerha (سعرها)** is a three-sided medical services marketplace built for the Saudi market:

1. **Patients** — search clinics, compare prices, read articles, view clinics on an interactive map, and book appointments via OTP authentication.
2. **Clinics** — manage their listing, services, prices, articles, working hours, Google reviews, and incoming bookings through a dedicated **React** dashboard.
3. **Platform admins** — oversee clinics, subscriptions, sales pipeline, analytics, and audit trail from a super-admin **React** panel.

> **Migration note:** the admin & clinic panels were fully migrated from **Filament v4** to a **React 19 + TypeScript SPA**. Filament has been removed; React is now the sole panel UI. Laravel remains the source of truth — every screen calls versioned REST endpoints that reuse the same Services, Policies, Observers, and Notifications.

The platform ships fully bilingual (Arabic / English) with automatic RTL ↔ LTR switching, dedicated typography per language, and locale-aware database fields.

---

## 🔗 Dashboards & URLs

**Production:** [`https://sarha.intshar.sa`](https://sarha.intshar.sa) &nbsp;·&nbsp; **Local (Laragon):** `http://sarha.test` (or `http://localhost:8000` via `php artisan serve`).

| Surface | Production URL | Guard |
| --- | --- | --- |
| 🌐 Public site (customers) | [`https://sarha.intshar.sa/`](https://sarha.intshar.sa/) | web |
| 🔑 Panel login (admin + clinic) | [`https://sarha.intshar.sa/app/login`](https://sarha.intshar.sa/app/login) | — |
| 🛡️ **Admin dashboard** (React) | [`https://sarha.intshar.sa/app/admin/dashboard`](https://sarha.intshar.sa/app/admin/dashboard) | admin |
| 🏥 **Clinic dashboard** (React) | [`https://sarha.intshar.sa/app/clinic/dashboard`](https://sarha.intshar.sa/app/clinic/dashboard) | clinic |
| 📈 Analytics | [`https://sarha.intshar.sa/app/admin/analytics`](https://sarha.intshar.sa/app/admin/analytics) | admin |
| 📚 API docs (Scribe) | [`https://sarha.intshar.sa/docs`](https://sarha.intshar.sa/docs) | — |
| 🧾 OpenAPI spec | [`https://sarha.intshar.sa/docs.openapi`](https://sarha.intshar.sa/docs.openapi) | — |
| 📮 Postman collection | [`https://sarha.intshar.sa/docs.postman`](https://sarha.intshar.sa/docs.postman) | — |

> For local development swap the host to `http://sarha.test` — every path above is identical on the dev box.
>
> Anything under `/app/*` loads the React SPA shell; React Router handles internal routing. The login page auto-routes to the correct dashboard based on the guard you authenticate against.

---

## 🔐 Test Credentials

> Seeded by `php artisan db:seed`. **All passwords are `password`** — change before production!

### 🛡️ Admin — log in at `/app/login` (choose **Admin**, sign in by email)

| Email | Password | Role |
|-------|----------|------|
| `admin@saerha.sa` | `password` | super_admin |

### 🏥 Clinic — log in at `/app/login` (choose **Clinic**, sign in by **phone**)

All clinic accounts use password **`password`**. Login is by **phone number**, not email:

| # | Clinic | Phone | Status | Plan |
|---|--------|-------|--------|------|
| 1 | مركز الرياض للأسنان | `0564334488` | ✅ active | ⭐ premium |
| 2 | مجمع الجمال والجلدية | `0555574270` | ✅ active | basic |
| 3 | مركز البصر للعيون | `0531531003` | ✅ active | ⭐ premium |
| 4 | مجمع أطفال المستقبل | `0527641533` | ✅ active | basic |
| 5 | مركز العظام والمفاصل | `0520053416` | ✅ active | ⭐ premium |
| 6 | مجمع القلب التخصصي | `0534186626` | ⏳ pending | basic |

> Clinic #6 is **pending** — it can't sign in until an admin approves it from `/app/admin/clinics`.
> Phone numbers are randomized on each fresh seed; run `php artisan tinker --execute="App\Models\Clinic::pluck('phone','name')"` to read the current ones.

### 👤 Customer (public site) — `/login`

| Method | Detail |
|--------|--------|
| Phone-only OTP | enter any Saudi phone (`05XXXXXXXX`) |
| Dev shortcut | OTP code is flashed on screen when `APP_ENV=local` |
| New users | auto-registered on first successful verification |

---

## ✨ Features

### Public Site
- 🔍 **Smart search** — filter by city, specialty, free-text; sort by featured / top-rated / cheapest / most-booked / **nearest (geolocation)**
- 🗺️ **Interactive maps** — clinics on the homepage & search results (Leaflet + OpenStreetMap), "search this area" on pan/zoom
- 🏥 **Clinic profiles** — services & prices, articles, working hours, Google reviews, breadcrumbs, booking & price-quote forms
- ⭐ **Featured clinics** — premium-tier listings get prominent placement
- 📱 **OTP authentication** — phone-only signup/login (Unifonic-ready)
- 🤖 **AI assistant** — floating chat that matches specialties/cities to clinics; rejects medical-advice questions
- 🔔 **Toasts** — success & error flash messages, auto-dismiss
- 🌍 **Bilingual** — Arabic (RTL) / English (LTR), Cairo ↔ Inter fonts, custom RTL pagination

### Clinic Dashboard (React)
- 📊 Live stats: new bookings, monthly volume, active services, subscription status
- ✏️ CRUD for services (with sub-clinic + sort order, grouped-by-category view), prices & offers
- 📰 Rich-text article publishing (Tiptap) with cover images + AI excerpt/body generation, monthly publish limit on Basic
- 📅 Booking management with quick status tabs + counters, appointment scheduling, internal notes
- 💬 Price-quote replies with tap-to-call customer context
- 🕐 Working-hours editor (7 days, open/close toggles)
- 📍 Google Maps coordinate extraction + Google reviews fetch
- 💳 Subscription page (current plan, days remaining, plan comparison)
- 🔔 Notification bell + smart nav badges (new quotes, subscription/offer expiring)
- ⬆️ CSV / Google-Sheets service import with AI column mapping

### Super Admin Panel (React)
- 🏥 **15 resources**: Clinics, Bookings, Complaints, Price Quotes, Sales Leads, Subscriptions, Users, Services, Articles, Cities, Categories, Admins, System Settings, Audit Log, Mass Notify
- 📈 **Dashboard**: KPI cards + 30-day bookings trend + "expiring soon" / "top clinics" / "clinics by city" panels
- 📊 **Analytics page**: total views, contact requests, revenue, 6-month comparison, specialty performance
- 🔬 **Per-clinic stats**: page views / bookings / quotes trend (7/14/30d) + KPI vs platform average
- 💼 **Sales pipeline**: leads with follow-up badges (overdue/today/upcoming) + one-click convert to Basic/Premium
- 🏥 **Clinic lifecycle**: approve / reject (with reason) / activate / suspend / extend (+30/+90) / impersonate
- 💳 **Subscriptions**: "expiring soon" filter + colour-coded rows; subscription CRUD
- 🧯 **Complaints**: status tabs + transitions (in-review / resolve / reject / notify clinic)
- 📨 **Mass notify**: target all / premium / basic / expiring clinics — in-app, email, or both
- 🔐 Soft deletes + restore + bulk actions, audit log with explicit action names, role-based admins
- 🌐 Grouped sidebar (matches Filament navigation groups), per-admin language switcher

### Cross-cutting
- 🔁 **Service-extraction pattern** — every action runs through the same Service/Policy/Observer the panels share
- 🛎️ **Unified notifications** — single `PlatformNotification` stream, polled by the React bell every 30s
- 🛡️ **Sanctum SPA** — stateful cookie auth, 3 guards (admin / clinic / web), 30-min session lifetime
- 💾 **Idempotent seeders** + scheduled jobs (subscription-expiry reminders, Google reviews sync, Sheets sync)

---

## 🧰 Tech Stack

| Layer | Tool | Version |
|-------|------|---------|
| **Framework** | Laravel | 12.x |
| **Language (backend)** | PHP | 8.2+ |
| **Panel UI** | React + TypeScript (strict) | 19.x |
| **SPA tooling** | Vite | 7.x |
| **Data layer** | TanStack Query | 5.x |
| **Forms** | React Hook Form + Zod | — |
| **UI primitives** | Radix UI + shadcn-style components | — |
| **Charts** | Recharts | 3.x |
| **Rich text** | Tiptap | 2.x |
| **i18n** | react-i18next (AR/EN, RTL) | — |
| **Auth** | Laravel Sanctum (SPA) | 4.x |
| **Public site** | Blade + Tailwind CSS | v4 |
| **Maps** | Leaflet + OpenStreetMap | (no API key) |
| **Database** | MySQL | 8.0+ |
| **API docs** | Scribe (Blade + OpenAPI + Postman) | 5.x |
| **Real-time chat** | Livewire (public AI widget only) | 3.x |
| **Payments** | Moyasar | (planned) |
| **AI assistant** | Anthropic Claude | (configurable) |

---

## 🏗 Architecture

A single React SPA serves both panels under `/app/*`; the public customer site stays Blade. Each surface has its own Sanctum guard.

```
┌──────────────────────────────────────────────────────────────────┐
│                      sarha.intshar.sa                              │
├────────────────────┬───────────────────────────────────────────┤
│   /  (Blade)        │   /app/*   (React 19 + TS SPA)             │
│   Public site       │   ┌─────────────────┬───────────────────┐ │
│   guard: web        │   │ /app/admin/*    │ /app/clinic/*     │ │
│   model: User       │   │ guard: admin    │ guard: clinic     │ │
│   auth: OTP         │   │ model: Admin    │ model: Clinic     │ │
│                     │   └─────────────────┴───────────────────┘ │
└────────────────────┴───────────────────────────────────────────┘
            │                          │
            │         REST  /api/v1/*  │  (Sanctum SPA, 3 guards)
            └────────────┬─────────────┘
                         │
            ┌────────────┴─────────────┐
            │  Services · Policies ·    │
            │  Observers · Notifications│   ← single source of truth
            └────────────┬─────────────┘
                         │
            ┌────────────┴─────────────┐
            │   MySQL (clinics, services,│
            │   bookings, articles,      │
            │   subscriptions, …)        │
            └───────────────────────────┘
```

---

## 🚀 Quick Start

### Prerequisites
- PHP **8.2+** with: `pdo_mysql`, `mbstring`, `openssl`, `intl`, `gd`
- **Composer 2.x**, **Node.js 18+** & **npm**
- **MySQL 8** (or MariaDB 10.5+)
- **Laragon** (recommended on Windows) or any local PHP server

### Installation

```bash
# 1) Clone
git clone https://github.com/alneamahaqlan/sarha.git
cd sarha

# 2) PHP deps
composer install

# 3) JS deps + build the React SPA and public assets
npm install
npm run build           # production
# or: npm run dev       # hot-reload during development

# 4) Environment
cp .env.example .env
php artisan key:generate
#   set DB_DATABASE=sarha / DB_USERNAME=root / DB_PASSWORD=

# 5) Migrate + seed sample data
php artisan migrate --seed

# 6) Link storage (uploaded logos/gallery/article covers)
php artisan storage:link
```

Open **<https://sarha.intshar.sa>** (public) and **<https://sarha.intshar.sa/app/login>** (panels). Locally the same paths live under `http://sarha.test`.

### One-command dev

```bash
composer dev   # runs php serve + queue + pail + vite together
```

---

## 📚 API Documentation

Every panel screen is backed by a versioned REST API under `/api/v1`, documented with **Scribe**:

| Resource | Production URL |
| --- | --- |
| Interactive HTML docs | [`https://sarha.intshar.sa/docs`](https://sarha.intshar.sa/docs) |
| OpenAPI 3 spec (YAML) | [`https://sarha.intshar.sa/docs.openapi`](https://sarha.intshar.sa/docs.openapi) |
| Postman collection | [`https://sarha.intshar.sa/docs.postman`](https://sarha.intshar.sa/docs.postman) |

> Local equivalents: `http://sarha.test/docs`, `/docs.openapi`, `/docs.postman`.

Regenerate after API changes:

```bash
php artisan scribe:generate
```

Auth is Sanctum SPA (stateful cookies). Guards: `admin`, `clinic`, `web` — enforced by the `api.guard` middleware.

---

## 📂 Project Structure

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── Admin/        # admin REST controllers (clinics, bookings, …, dashboard, analytics)
│   │   ├── Clinic/       # clinic REST controllers (services, bookings, profile, dashboard, …)
│   │   └── Shared/       # auth, lookups, uploads, notifications, AI chat, impersonation
│   ├── Requests/Api/V1/  # form-request validation per resource
│   └── Resources/Api/V1/ # API resources (JSON shaping)
├── Services/             # ClinicService, SalesLeadService, NotificationService,
│                         #   MassNotifyService, GoogleMapsService, GooglePlacesService,
│                         #   GoogleSheetsImportService, AiAssistantService, AuditLogService …
├── Observers/            # Booking/Complaint/PriceQuote/Article side effects
├── Policies/             # per-guard authorization
├── Models/               # Eloquent models
├── Console/Commands/     # NotifySubscriptionExpiry, SyncGoogleReviews, SyncClinicsSheet
└── Livewire/AiChat.php   # public-site AI widget (only remaining Livewire component)

resources/
├── react-admin/src/      # ← React 19 + TS SPA (admin + clinic panels)
│   ├── app/              # routes, layouts (Admin/Clinic/MobileNav), providers (Auth/Locale)
│   ├── features/         # one folder per resource: api.ts, hooks.ts, pages/, components/
│   ├── components/ui/    # shadcn-style primitives (Radix)
│   ├── components/forms/ # FileUpload, RichEditor
│   └── locales/{ar,en}/  # admin.json (panel translations)
├── views/
│   ├── react-admin.blade.php   # SPA shell mounted at /app/*
│   ├── layouts/public.blade.php
│   ├── public/                 # home, search, clinic, partials (incl. Leaflet map)
│   └── vendor/pagination/      # custom RTL pagination view
└── css / js              # public-site Tailwind + Livewire bridge

lang/{ar,en}/             # site.php (public) + admin.php (notifications, validation)
database/
├── migrations/
└── seeders/DatabaseSeeder.php   # idempotent (updateOrCreate)
routes/
├── api.php               # /api/v1/* (admin, clinic, shared groups)
└── web.php               # public site + /app/{any} SPA catch-all
```

---

## 🌐 Bilingual Support

Saerha is **fully bilingual by design**. Locale resolution per request:

```
Cookie (app_locale)  →  Browser Accept-Language  →  config('app.locale')
```

| Surface | Mechanism | Status |
|---------|-----------|--------|
| Public site UI | `lang/{locale}/site.php` + `@lang()` | ✅ |
| React panels | `react-i18next` + `locales/{ar,en}/admin.json` | ✅ |
| Sidebar groups & nav | translation keys | ✅ |
| Cities & Categories | `display_name` accessor (`name` vs `name_en`) | ✅ |
| Notifications & emails | `lang/{locale}/admin.php` | ✅ |

Switch via the navbar (public), the header toggle (panels), or `GET /lang/{ar|en}` (cookie-persisted, 1 year). The `<html lang dir>`, fonts (Cairo ↔ Inter), and panel layout direction all flip automatically.

---

## 🗺 Routes Map

```
# Public (Blade)
GET    /                          home (+ clinics map)
GET    /search                    search results (+ map, geo-sort)
GET    /clinic/{slug}             clinic profile
POST   /clinic/{slug}/book        booking submission
POST   /clinic/{slug}/quote       price-quote request
GET    /login · /login/send-otp · /login/verify · /logout    OTP auth
GET    /lang/{ar|en}              language switcher

# React SPA shell
GET    /app/{any}                 admin + clinic panels (React Router)

# REST API (Sanctum SPA)
POST   /api/v1/auth/login · /auth/logout · /auth/me
*      /api/v1/admin/*            admin resources + dashboard + analytics
*      /api/v1/clinic/*           clinic resources + profile + subscription
*      /api/v1/{uploads,lookups,notifications,ai-chat,impersonation}

# Docs
GET    /docs · /docs.openapi · /docs.postman
```

Run `php artisan route:list` for the full list (~130 routes).

---

## 🛣 Roadmap

- [x] Phase 1: Public site with search, booking, OTP auth
- [x] Phase 2: Clinic dashboard (services / articles / bookings)
- [x] Phase 3: Super-admin panel with full CRUD + widgets
- [x] Bilingual support (AR/EN) with RTL/LTR
- [x] **Filament → React + TypeScript migration** (panels are now a Sanctum-backed SPA)
- [x] REST API (`/api/v1`) + Scribe/OpenAPI documentation (~130 routes)
- [x] Analytics page + per-clinic stats + sales pipeline follow-up badges
- [x] Clinic working hours, Google Maps coords, Google reviews sync, subscription page
- [x] Interactive maps (homepage + search), nearest-geolocation sort
- [x] Google Sheets clinic import + 6-hour auto-sync
- [x] Mass notifications (in-app / email / both) + smart nav badges
- [x] Trusted proxies for Cloudflare Tunnel deployment
- [ ] Unifonic SMS integration for production OTP
- [ ] Moyasar payment gateway for subscriptions
- [ ] `.xlsx` import (needs PhpSpreadsheet dependency)
- [ ] Claude API wiring for the public AI chat (currently local matching)
- [ ] Push notifications (web + mobile)
- [ ] Mobile apps (Flutter / React Native)

---

## 📄 License

Released under the [MIT License](https://opensource.org/licenses/MIT). Built on top of [Laravel](https://laravel.com) and [React](https://react.dev), each under their respective open-source licenses.

---

<div align="center">

**Built with ❤️ for the Saudi medical community.**

</div>
