<div align="center">

# دليل المجمعات الطبية

### Medical Complexes Directory — Saudi Arabia

**Discover, compare, and book medical complexes & clinics across the Kingdom** — a three-sided marketplace connecting patients, clinics, and platform operators.

[![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)](https://react.dev)
[![TypeScript](https://img.shields.io/badge/TypeScript-strict-3178C6?logo=typescript&logoColor=white)](https://www.typescriptlang.org)
[![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v4-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

</div>

---

## 📖 Table of Contents

- [Overview](#-overview)
- [Surfaces & URLs](#-surfaces--urls)
- [Test Credentials](#-test-credentials)
- [Core Domain](#-core-domain)
- [Features](#-features)
- [Subscription Packages](#-subscription-packages)
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

**دليل المجمعات الطبية** is a three-sided medical marketplace built for the Saudi market:

1. **Patients** — search and compare medical complexes by city, specialty, and price; view clinics on an interactive map; book appointments, request multi-clinic price quotes, save favorites, and file complaints/reports — all via phone-only OTP auth.
2. **Clinics** — manage their public profile, services, prices, offers, medical packages, doctors, articles, working hours, and incoming demand (bookings, quotes, complaints) through a dedicated **React** dashboard. Each clinic can invite **team members** with attributed activity, and run a built-in **CRM** over its customers.
3. **Platform admins** — oversee clinics, subscriptions, the sales pipeline, analytics, content moderation, the homepage builder, and an **AI Center**, from a super-admin **React** panel.

The platform is **fully bilingual** (Arabic / English) with automatic RTL ↔ LTR switching, per-language typography (Cairo ↔ Inter), and locale-aware database fields. Laravel is the single source of truth: every panel screen calls a versioned REST API that reuses the same Services, Policies, Observers, and Notifications.

---

## 🔗 Surfaces & URLs

**Local (Laragon):** `http://sarha.test` (or `http://localhost:8000` via `php artisan serve`).

| Surface | Path | Stack | Guard |
| --- | --- | --- | --- |
| 🌐 Public site (patients) | `/` | Blade + Tailwind | web |
| 🔑 Panel login (admin + clinic) | `/app/login` | React | — |
| 🛡️ **Admin dashboard** | `/app/admin/dashboard` | React SPA | admin |
| 🏥 **Clinic dashboard** | `/app/clinic/dashboard` | React SPA | clinic |
| 🤝 Clinic self-registration | `/register-clinic` | Blade | — |
| 📚 API docs (Scribe) | `/docs` · `/docs.openapi` · `/docs.postman` | — | — |

> Everything under `/app/*` loads the React SPA shell; React Router handles internal routing. The login page auto-routes to the correct dashboard based on the guard you authenticate against.

---

## 🔐 Test Credentials

> Seeded by `php artisan db:seed` (idempotent — re-running adds missing demo data without clobbering these rows). **All passwords are `password`** — change before production.

### 🛡️ Admin — `/app/login` → **Admin** tab, sign in by **email**

| Email | Password | Role |
|-------|----------|------|
| `admin@saerha.sa` | `password` | super_admin |

### 🏥 Clinic — `/app/login` → **Clinic** tab, sign in by **phone**

All clinic accounts use password **`password`**; login is by **phone number**. Demo clinics are seeded with deterministic phones `0550000001`…`0550000006` (the last is left **pending** so you can test the admin approval flow).

### 👤 Patient (public site) — `/login`

| Method | Detail |
|--------|--------|
| Phone-only OTP | enter any Saudi phone (`05XXXXXXXX`) |
| Dev shortcut | OTP is flashed on screen when `APP_ENV=local` |
| New users | auto-registered on first successful verification |

---

## 🧩 Core Domain

The schema centers on the **Clinic** and a per-clinic **Customer** (CRM) entity:

- **Clinic** — owns `services`, `offers`, `packages` (medical bundles), `doctors`, `articles`, `workingHours`, `googleReviews`, `subClinics`, `beforeAfterPhotos`, `teamMembers`, `stats`, and a `subscription` linked to a **SubscriptionPackage**.
- **Customer** — a **unified per-clinic record** keyed by `(clinic_id, phone)`. Bookings, complaints, and price quotes are auto-linked to the matching customer by `CustomerLinker` + observers. Carries computed tags (`VIP`, `repeat`, `new`, `prior-complaint`), free-form `tags`, and a multi-note thread (`notes`).
- **Booking** — has a `reference_code`, status workflow, optional `assignee` (team member), `tags`, an activity log, and may be created on behalf of a **Relative**. Surfaced both as a list and an **Odoo-style Kanban board**.
- **PriceQuoteRequest** — can fan out to **multiple clinics / cities**; each clinic answers with a `PriceQuoteReply` (member-attributed).
- **Demand & moderation** — `Complaint` (with clinic reply), `ClinicReport` & `CustomerReport` (abuse reporting), `CategoryRequest` (clinic asks admin for a new category).
- **Growth & insight** — `SalesLead` pipeline, `ClinicStat` (views / clicks / directions), `UserActivityEvent` + `UserVisitSession` (behavioral tracking), impression tracking, and the AI tables (`AiConversation`, `AiAssistantLog`, `AiRestriction`, `AiResponseTemplate`, `AiUserInteractionSummary`).
- **Delivery** — `PlatformNotification` (unified stream) + `PushSubscription` (Web Push / VAPID), `OtpCode`, `AuditLog`, `ClinicActivityLog`.

---

## ✨ Features

### Public Site (Patients)
- 🔍 **Smart search** — filter by city, specialty, free-text; sort by featured / top-rated / cheapest / most-booked / **nearest (geolocation)**; live search suggestions
- 🆚 **Compare** clinics side by side
- 🗺️ **Interactive maps** — clinics on the homepage & search results (Leaflet + OpenStreetMap), "search this area" on pan/zoom
- 🏥 **Clinic profiles** — services & prices, offers, medical packages, doctors, before/after gallery, articles, working hours, Google reviews, directions, booking & quote forms
- 💬 **Price-quote board** — request a quote from one or many clinics across cities and track replies
- 📱 **OTP authentication** — phone-only signup/login
- 👤 **Patient account** — bookings, favorites, price quotes, complaints, abuse reports
- 🤝 **Clinic self-registration** — onboarding funnel feeding the admin sales pipeline
- 🤖 **AI assistant** — floating chat that matches specialties/cities to clinics and refuses medical-advice questions
- 🧭 **Dynamic homepage** — admin-built sections (banner slides, featured rows) rendered server-side
- 🌍 **Bilingual** — Arabic (RTL) / English (LTR), Cairo ↔ Inter fonts, custom RTL pagination; SEO sitemap + robots

### Clinic Dashboard (React)
- 📊 Live stats + smart nav badges (new bookings, expiring subscription/offers, new quotes)
- ✏️ CRUD for **services** (sub-clinic + sort order, grouped by category, multi-category), **offers**, and medical **packages**
- 👨‍⚕️ **Doctors** directory & **before/after** gallery (feature-gated)
- 📰 Rich-text **articles** (Tiptap) with cover images + AI excerpt/body generation; monthly publish limit per plan
- 📅 **Bookings** — list **and Kanban board**: status tabs + counters, tags, assignee, activity timeline, walk-in/outreach creation, scheduling
- 🧑‍🤝‍🧑 **Customer CRM** — per-clinic customer list & profile (timeline of bookings/complaints/quotes), tags, multi-note thread, stats
- 💬 **Price-quote replies** with tap-to-call customer context
- 🧯 **Complaints** inbox with clinic replies
- 🕐 Working-hours editor · 📍 Google Maps coordinate extraction · ⭐ Google reviews sync
- 👥 **Team members** — invite staff; every action is attributed and logged
- 💳 Subscription page (current plan, days remaining, feature comparison)
- ⬆️ **Service import** from CSV / Google Sheets with AI column mapping
- 🏷️ Request new categories from the admin

### Super Admin Panel (React)
- 📊 **Dashboard** — KPI cards + 30-day bookings trend + "expiring soon" / "top clinics" / "clinics by city" + AI-center widget
- 📈 **Analytics** — total views, contact requests, revenue, 6-month comparison, specialty performance; per-clinic stats (views / bookings / quotes, 7/14/30d) vs platform average
- 🏥 **Clinic lifecycle** — approve / reject (with reason) / activate / suspend / extend / **impersonate**; bulk actions, soft-delete + restore; Google Sheets import
- 💳 **Subscriptions & packages** — editable **SubscriptionPackage** catalogue (prices + per-feature toggles, no deploy), subscription CRUD, renew/cancel, "expiring soon" filter
- 💼 **Sales pipeline** — leads with follow-up badges (overdue / today / upcoming) + one-click convert
- 🧯 **Complaints**, 🚩 **clinic & customer reports**, 🏷️ **category requests** — moderation queues with status transitions
- 📨 **Mass notify** — target all / premium / basic / expiring clinics via in-app, email, or both
- 🧭 **Homepage builder** — sections + banner slides with drag-and-drop ordering
- 🤖 **AI Center** — conversation explorer, analytics, restrictions, response templates, per-user AI interests
- 👤 **Users** — profiles with activity timeline, suspend, force-logout
- 🔐 Role-based admins, audit log with explicit action names, grouped sidebar, per-admin language switcher

### Cross-cutting
- 🔁 **Service-extraction pattern** — every action runs through the same Service / Policy / Observer the panels share
- 🎛️ **FeatureGate** — a single gate maps each plan's feature columns to runtime permissions
- 🛎️ **Unified notifications** — one `PlatformNotification` stream polled by the React bell, plus **Web Push** (VAPID) and **Reverb** real-time events
- 🛡️ **Sanctum SPA** — stateful cookie auth, 3 guards (admin / clinic / web)
- 💾 **Idempotent seeders** + scheduled jobs (subscription-expiry reminders, Google reviews sync, Sheets sync, AI-log pruning)

---

## 💳 Subscription Packages

Plans are **data, not code**. The `subscription_packages` table seeds three tiers (**Free / Standard / Premium**) but the super-admin can add tiers, change prices, and flip any single feature from the UI — no deploy. Feature columns are kept flat for an easy toggle UI and indexable queries:

| Feature | Type | Notes |
| --- | --- | --- |
| `services_limit` | int / null | null = unlimited |
| `articles_monthly_limit` | int / null | monthly publish cap |
| `ai_article_generation` | bool | AI excerpt/body |
| `featured_in_search` | bool | priority placement |
| `ai_assistant_priority` | 0/1/2 | rank in AI assistant |
| `google_reviews_sync` | bool | — |
| `verified_badge` | bool | — |
| `analytics_level` | basic \| full | — |
| `quote_replies_monthly_limit` | int / null | — |
| `banner_slots` | int | homepage banner ownership |
| `allow_offers_packages` | bool | Offers + medical Packages |
| `allow_doctors_before_after` | bool | Doctors + before/after gallery |

Enforcement is centralized in `App\Services\FeatureGate`.

---

## 🧰 Tech Stack

| Layer | Tool | Version |
|-------|------|---------|
| **Framework** | Laravel | 12.x |
| **Language (backend)** | PHP | 8.2+ |
| **Panel UI** | React + TypeScript (strict) | 19.x |
| **SPA tooling** | Vite | 7.x |
| **Data layer** | TanStack Query + TanStack Table | 5.x / 8.x |
| **Forms** | React Hook Form + Zod | — |
| **UI primitives** | Radix UI + shadcn-style components | — |
| **Drag & drop** | dnd-kit (Kanban, reordering) | — |
| **Rich text** | Tiptap | 2.x |
| **i18n** | react-i18next (AR/EN, RTL) | — |
| **Auth** | Laravel Sanctum (SPA) | 4.x |
| **Public site** | Blade + Tailwind CSS | v4 |
| **Maps** | Leaflet + OpenStreetMap | (no API key) |
| **Database** | MySQL | 8.0+ |
| **Real-time** | Laravel Reverb | 1.x |
| **Web Push** | minishlink/web-push (VAPID) | 10.x |
| **API docs** | Scribe (HTML + OpenAPI + Postman) | 5.x |
| **Public AI widget** | Livewire | 3.x |
| **Payments** | Moyasar | (planned) |
| **AI assistant** | Anthropic Claude | (configurable) |

---

## 🏗 Architecture

A single React SPA serves both panels under `/app/*`; the public patient site stays Blade. Each surface has its own Sanctum guard, and all writes flow through shared Services.

```
┌──────────────────────────────────────────────────────────────────┐
│                          sarha.test                                │
├────────────────────┬───────────────────────────────────────────┤
│   /  (Blade)        │   /app/*   (React 19 + TS SPA)             │
│   Public site       │   ┌─────────────────┬───────────────────┐ │
│   guard: web        │   │ /app/admin/*    │ /app/clinic/*     │ │
│   model: User       │   │ guard: admin    │ guard: clinic     │ │
│   auth: OTP         │   │ model: Admin    │ model: Clinic      │ │
│                     │   └─────────────────┴───────────────────┘ │
└────────────────────┴───────────────────────────────────────────┘
            │                          │
            │         REST  /api/v1/*  │  (Sanctum SPA, 3 guards)
            └────────────┬─────────────┘
                         │
        ┌────────────────┴─────────────────┐
        │  Services · FeatureGate · Policies │
        │  Observers · Notifications · AI    │   ← single source of truth
        └────────────────┬─────────────────┘
                         │
        ┌────────────────┴─────────────────┐
        │   MySQL  (clinics, customers,      │
        │   bookings, services, offers,      │
        │   packages, subscriptions, AI, …)  │
        └───────────────────────────────────┘
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
# 1) PHP deps
composer install

# 2) JS deps + build the React SPA and public assets
npm install
npm run build            # production  (or: npm run dev for hot-reload)

# 3) Environment
cp .env.example .env
php artisan key:generate
#   set DB_DATABASE / DB_USERNAME / DB_PASSWORD

# 4) Migrate + seed demo data
php artisan migrate --seed

# 5) Link storage (logos / gallery / article covers)
php artisan storage:link
```

Open **`http://sarha.test`** (public) and **`http://sarha.test/app/login`** (panels).

### One-command dev

```bash
composer dev   # php serve + queue + pail (logs) + vite, together
```

---

## 📚 API Documentation

Every panel screen is backed by a versioned REST API under `/api/v1`, documented with **Scribe**:

| Resource | Path |
| --- | --- |
| Interactive HTML docs | `/docs` |
| OpenAPI 3 spec (YAML) | `/docs.openapi` |
| Postman collection | `/docs.postman` |

Regenerate after API changes: `php artisan scribe:generate`.

Auth is Sanctum SPA (stateful cookies). Guards `admin`, `clinic`, `web` are enforced by the `api.guard` middleware; locale by `api.locale`.

---

## 📂 Project Structure

```
app/
├── Http/Controllers/Api/V1/
│   ├── Admin/        # clinics, bookings, subscriptions, packages, sales-leads,
│   │                 #   complaints, reports, category-requests, homepage-sections,
│   │                 #   ai-center, analytics, dashboard, audit, users, …
│   ├── Clinic/       # services, offers, packages, doctors, before-after, bookings
│   │                 #   (+ kanban / tags / assignment / activity), customers (CRM),
│   │                 #   price-quotes, complaints, articles, profile, subscription …
│   └── (shared)      # auth, lookups, uploads, notifications, push, ai-chat, impersonation
├── Services/         # ClinicService, SubscriptionService/-Lifecycle, FeatureGate,
│   ├── Ai/           #   BookingKanbanService, CustomerInsightService, CustomerLinker,
│   │                 #   SalesLeadService, MassNotifyService, NotificationDispatcher,
│   │                 #   Google{Maps,Places,SheetsImport}Service, HomepageRenderService,
│   │                 #   ImpressionTracker, WebPushSender, SmsService, PiiMasker, …
├── Observers/        # Booking/Complaint/PriceQuote/Article + Customer-link + Audit
├── Policies/  Models/  Console/Commands/   (scheduled jobs)
└── Livewire/AiChat.php          # public-site AI widget

resources/
├── react-admin/src/
│   ├── app/          # routes, layouts (Admin/Clinic/MobileNav), Auth/Locale providers
│   ├── features/     # one folder per resource (api.ts, hooks.ts, pages/, components/)
│   ├── components/ui/ + components/forms/   # Radix/shadcn primitives, FileUpload, RichEditor
│   └── locales/{ar,en}/admin.json
├── views/
│   ├── react-admin.blade.php    # SPA shell mounted at /app/*
│   └── public/                  # home, search, compare, clinic, quotes, account, partials
└── css / js

lang/{ar,en}/         # site.php (public) + admin.php (notifications, validation)
database/
├── migrations/       # ~80 migrations (see Core Domain)
└── seeders/DatabaseSeeder.php   # idempotent (updateOrCreate)
routes/
├── api.php           # /api/v1/* (shared, admin, clinic groups)
└── web.php           # public site + /app/{any} SPA catch-all
```

---

## 🌐 Bilingual Support

Fully bilingual by design. Locale resolution per request:

```
Cookie (app_locale)  →  Browser Accept-Language  →  config('app.locale')
```

| Surface | Mechanism |
|---------|-----------|
| Public site UI | `lang/{locale}/site.php` + `@lang()` |
| React panels | `react-i18next` + `locales/{ar,en}/admin.json` |
| Cities & Categories | `display_name` accessor (`name` vs `name_en`) |
| Notifications & emails | `lang/{locale}/admin.php` |

Switch via the navbar (public), the header toggle (panels), or `GET /lang/{ar|en}` (cookie-persisted). `<html lang dir>`, fonts (Cairo ↔ Inter), and panel direction all flip automatically.

---

## 🗺 Routes Map

```
# Public (Blade)
GET    /                              home (+ clinics map, dynamic sections)
GET    /search · /search/suggest      results (+ map, geo-sort) · live suggestions
GET    /compare                       compare clinics
GET    /clinic/{slug}                 clinic profile
GET|POST /clinic/{slug}/book(/verify) booking (OTP-verified)
POST   /clinic/{slug}/quote           price-quote request
GET    /booking/{reference}           booking confirmation
GET    /quotes · /quotes/new          quote board · multi-clinic request
GET    /article/{slug}                article
GET|POST /register-clinic             clinic self-registration
GET    /login · /login/send-otp · /login/verify · /logout    OTP auth
GET    /account/*                     bookings, favorites, quotes, complaints, reports
GET    /lang/{ar|en}                  language switcher
GET    /sitemap.xml · /robots.txt     SEO

# React SPA shell
GET    /app/{any}                     admin + clinic panels (React Router)

# REST API (Sanctum SPA)
POST   /api/v1/auth/{login,logout,me}
*      /api/v1/admin/*                admin resources + dashboard + analytics + ai-center
*      /api/v1/clinic/*               clinic resources + kanban + customers (CRM) + profile
*      /api/v1/{uploads,lookups,notifications,push,ai-chat,impersonation}

# Docs
GET    /docs · /docs.openapi · /docs.postman
```

Run `php artisan route:list` for the full list.

---

## 🛣 Roadmap

- [x] Public site: search, compare, booking, multi-clinic quotes, OTP auth, patient account
- [x] Clinic dashboard: services / offers / packages / doctors / articles / bookings (list + Kanban)
- [x] Per-clinic **Customer CRM**: unified customer, tags, notes, timeline
- [x] **Team members** with attributed activity logs
- [x] Super-admin panel: full CRUD, analytics, sales pipeline, moderation queues
- [x] **Dynamic subscription packages** (editable tiers + per-feature gating)
- [x] **AI Center** (conversations, analytics, restrictions, templates)
- [x] **Homepage builder** (sections + banner slides)
- [x] Web Push (VAPID) + Reverb real-time + unified notifications
- [x] Bilingual (AR/EN) with RTL/LTR · REST API + Scribe/OpenAPI docs
- [ ] Production SMS gateway for OTP (Unifonic)
- [ ] **Moyasar** payment gateway for subscriptions
- [ ] Claude API wiring for the public AI chat (currently local matching)
- [ ] `.xlsx` import (PhpSpreadsheet)
- [ ] Mobile apps (Flutter / React Native)

---

## 📄 License

Released under the [MIT License](https://opensource.org/licenses/MIT). Built on top of [Laravel](https://laravel.com) and [React](https://react.dev), each under their respective open-source licenses.

---

<div align="center">

**Built with ❤️ for the Saudi medical community.**

</div>
