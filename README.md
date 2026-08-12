# Owere & Associates — Web Platform

Financial, tax advisory & corporate compliance website built with **PHP 8 (native/PDO)**,
**MySQL 8** and vanilla **HTML/CSS/JS**. Executive Navy (`#0B192C`) & Gold (`#D4AF37`) design system.

---

## 1. Quick start (Docker — recommended)

1. Make sure **Docker Desktop** is running on your machine.
2. Open a terminal in this project folder and run:

   ```bash
   docker compose up -d
   ```

3. Wait about 30 seconds for MySQL to initialise, then visit:

   | | |
   |---|---|
   | **Public site** | http://localhost:8080 |
   | **Admin login** | http://localhost:8080/admin |
   | **Credentials** | `admin` / `admin123` |

4. To stop the containers:

   ```bash
   docker compose down
   ```

> The database is imported automatically from `schema.sql` on first start.
> Your data persists in a Docker volume across restarts. To reset the DB:
> `docker compose down -v && docker compose up -d`

---

## 2. Local setup (XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) (Apache + MySQL + PHP 8).
2. Copy this whole folder into `C:\xampp\htdocs\` — e.g. `C:\xampp\htdocs\owere-associates\`.
3. Start **Apache** and **MySQL** from the XAMPP Control Panel.
4. Open **phpMyAdmin** (`http://localhost/phpmyadmin`) → *Import* → choose `schema.sql` → **Go**.
   This creates the `owere_db` database, its 5 tables and seed data.
5. `config/db.php` auto-detects the environment — it works with Docker AND XAMPP without changes.
6. Visit **http://localhost/Tax_website/**

> No database imported yet? The site still renders using built-in defaults; only
> the forms, logos and admin panel need the database.

---

## 3. Admin panel

| | |
|---|---|
| Docker URL | `http://localhost:8080/admin/` |
| XAMPP URL | `http://localhost/Tax_website/admin/` |
| First-run login | `admin` / `admin123` |

The default admin is **auto-created** the first time you open `/admin/` on a fresh database.
**Change the password immediately** via *Settings → Change Password*.

Admin capabilities:

- **Leads** — every booking form submission, with live status (new / contacted / closed),
  search & filtering, and delete.
- **Partner Logos** — upload, show/hide, reorder and delete client/partner logos.
- **Media Library** — upload and manage all site images in one place (up to **20 MB** each),
  then **place any image directly onto a specific spot on the website** — the homepage hero,
  the About photo, or any pillar's card icon / Services-page photo — with one click from
  the library page. No file names to remember. The library also shows which spots each
  image currently fills (“In use”), has a live search box, and warns you before deleting
  an image that's still on the site. Images can equally be picked per-field via
  “Choose from library” inside the Website Content editor.
- **Website Content** — a full no-code editor for the whole site, organised by page:
  Home (hero, logos strip, services heading, stats), Services (every pillar's name,
  descriptions, checklist items, photos), About & More, FAQ (a homepage accordion
  of question/answer rows), Contact & Booking pop-up, Footer & Navigation, and
  SEO & Branding. The **company logo** (your real
  logo image, optional) is uploaded under *Footer & Nav → Company logo* and
  replaces the initials box in the header/footer; the browser-tab icon is the
  file `assets/images/icons/favicon.svg`. Each service pillar has its own
  WhatsApp number field (falls back to the main number from Settings). **Pillars
  are not fixed** — use *“+ Add another pillar”* / *“Remove pillar”* on the
  Services tab to grow or shrink the service list; every pillar automatically
  gets a homepage card, a Services-page section and a booking-form option.
  Includes a formatting toolbar (no HTML
  needed) with an **“Insert image” button** that drops photos from the media
  library straight into paragraphs, inline image uploads, repeatable rows,
  a **photo gallery** section (its own **Gallery** tab → add as many photos
  as you like, each with a caption; the homepage grid appears below About),
  a **Design & Theme** tab with preset palettes, colour pickers, font
  pairings and **per-section backgrounds** (hero, stats, CTA band, footer)
  that re-skin the whole site with no code (shade variants auto-generated),
  one-click “Restore defaults”,
  automatic timestamped backups in `content/backups/` and **automatic draft saving**
  (unsaved edits survive closing the tab — they are restored with a banner on return
  and cleared once you click Save). The **FAQ tab** adds question/answer rows that
  render as an accordion on the homepage (hidden until you add at least one question)
  and also feed Google's FAQ rich results. Stored in `content/site-content.json`.
- **Settings** — **My Profile** (your username, display name, email and profile
  photo, shown in the panel top bar), WhatsApp number, contact details, SMTP email
  (Zoho/Google/any), and password change.
- **Admin Users** — create, edit and delete staff accounts from the panel (no
  SQL needed): username, display name, email, profile photo and a temporary
  password for each; reset anyone's password; delete accounts (you can't delete
  your own, and the last remaining account is protected). Account management is
  recorded in the activity log.
- **Activity Log** — an automatic audit trail of who did what in the panel:
  logins (including failed ones), logouts, profile/settings/password changes,
  content saves and resets, logo and media uploads/placements/deletions, and lead
  status changes — each with the admin's username, timestamp, IP address and a
  human-readable detail. Filter by action type or search, and clear the log when
  needed (the clear itself is recorded). **Owner-only** — staff accounts can't see
  the log at all (no sidebar link, and visiting the page redirects them to Leads).
  Stored in the `admin_activity` table
  (auto-created on first use — existing installs don't need manual SQL).

> **Default account:** if no admin exists, opening `/admin/` auto-creates
> `admin` / `admin123` (see `ensure_default_admin()` in
> `includes/functions.php`). Add more staff accounts under **Admin Users** —
> the panel is the supported way; the `admin_users` table in `owere_db`
> stores only the bcrypt password hash, never plain-text credentials.

---

## 4. Email (Zoho Mail Lite)

The booking form always saves to the database. Email notifications are sent on top
via native SMTP (no composer packages required). Recommended route for this project:
**Zoho Mail Lite** (~$1/user/month) — the only budget provider whose SMTP is
usable from a website.

### 4a. Buy the domain (if you don't own it yet)

Buy `owereassociates.com` from a global registrar — **Porkbun** is recommended
(~$11/yr, accepts Visa/Mastercard & PayPal from Uganda, free privacy). Then sign up
at **Zoho Mail** → *Add existing domain* and follow their wizard — it gives you a
one-time TXT verification code.

### 4b. DNS records at your registrar (one-time, ~10 minutes)

| Type | Name | Value | Priority |
|---|---|---|---|
| TXT | `@` | `zoho=zb…` (Zoho verification code) | — |
| TXT | `@` | `v=spf1 include:zoho.com ~all` | — |
| TXT | `zoho._domainkey` | DKIM key (copy from Zoho admin) | — |
| MX | `@` | `mx.zoho.com` | 10 |
| MX | `@` | `mx2.zoho.com` | 20 |
| MX | `@` | `mx3.zoho.com` | 50 |

Verify in Zoho, create `info@owereassociates.com` (plus `director@`/`finance@` as
needed), and allow 15–60 minutes for DNS to propagate.

### 4c. Connect the site

1. In `/admin/` → **Settings → SMTP Email Settings**, enter:
   - Host: `smtp.zoho.com`, Port: `465`
   - Username: `info@owereassociates.com`
   - **App-specific password** (Zoho Accounts → Security → App Passwords)
2. Set the **Booking notification inbox** to `info@owereassociates.com`.
3. Submit a test enquiry from the public form.

Google Workspace also works if ever preferred: host `smtp.gmail.com`, port `587`,
app password (requires 2-Step Verification). If SMTP isn't configured, the enquiry
is still logged — you just won't get an email.

---

## 5. WhatsApp button

The floating chat button builds a pre-filled message via `api/whatsapp-router.php`
using the number in **Settings → WhatsApp number** (international format, e.g. `+256 701 700 461`).

Each service pillar can use its **own number**: set it in **Website Content → Services**
("WhatsApp number for this pillar"). Leave it empty to fall back to the main number.
The per-service button also auto-fills the service name in the message.

---

## 6. Customising

| Want to change… | Where |
|---|---|
| Any page text, buttons, nav, footer, SEO | Admin → **Website Content** (no code needed) |
| Partner logos | Admin → **Partner Logos** |
| Phone, address, WhatsApp number | Admin → **Settings** |
| Your admin username, display name & photo | Admin → **Settings → My Profile** |
| Company logo (header + footer) | Admin → **Website Content → Footer & Nav → Company logo** |
| Browser-tab icon (favicon) | Replace `assets/images/icons/favicon.svg` (no panel option) |
| Colours & fonts | `assets/css/main.css` (`:root` variables) |
| Page layout / structure | `includes/header.php`, `includes/footer.php`, `index.php`, `services.php` |
| Sample logo SVGs | `assets/images/partners/` |
| Hero / about visuals | `assets/images/about-visual.svg` and the content editor's image uploads |
| Upload size limit (default 20 MB) | `docker/php.ini` (`upload_max_filesize` / `post_max_size`) and `MEDIA_MAX_UPLOAD_BYTES` in `includes/functions.php` |
| SEO title & description per page | Admin → **Website Content → SEO & Branding** |
| Sitemap for search engines | Auto-served at `/sitemap.xml` (from `sitemap.php`) |
| Search-engine crawl rules | Auto-served at `/robots.txt` (from `robots.php`) |
| Photo gallery (homepage grid) | Admin → **Website Content → Gallery** (add/remove photos + captions) |
| Site colours & theme | Admin → **Website Content → Design & Theme** (preset palettes, custom colours, **font pairings**, **per-section backgrounds** — hero, stats & process, CTA band, footer) |
| Photos inside paragraphs | Any rich-text field (e.g. About → Body text) → press the **🖼 Image** button in the toolbar |

---

## 7. File structure

```text
├── index.php               # Public homepage
├── services.php            # 3 service pillars + booking triggers
├── schema.sql              # MySQL schema (owere_db)
├── assets/
│   ├── css/                # main.css (theme) · admin.css (dashboard)
│   ├── js/                 # main.js · admin.js
│   └── images/             # icons, partner logos, visuals
├── config/                 # db.php (PDO) · mail.php (SMTP)
├── includes/               # header, footer, admin layout, functions.php
├── api/
│   ├── submit-booking.php  # form → DB → routed SMTP email
│   ├── whatsapp-router.php # pre-filled wa.me link generator
│   ├── upload-image.php    # AJAX image upload for the content editor
│   └── media-list.php      # JSON list of uploaded images (media picker)
├── admin/                  # login, leads dashboard, logos, media library, content (CMS), settings, users, activity log
├── content/
│   ├── site-content.json   # whole-site content (edited via the CMS, no code)
│   └── backups/            # automatic timestamped content backups (latest 20)
├── assets/images/content/  # Media Library uploads (browse via admin → Media Library)
├── Dockerfile              # PHP 8.2 Apache container definition
├── docker-compose.yml      # Docker orchestration (web + db)
├── docker/                 # PHP configuration overrides
└── architecture-config.yml # Project metadata (stack, DB, services)
```

---

## 8. Going live (hosting, admin access & SEO)

### 8a. Choose hosting

The site is plain PHP 8 + MySQL — it runs on any host. Two routes:

| Option | Cost | Best for | What to do |
|---|---|---|---|
| **Shared hosting (cPanel)** | ~$3–10/month | A non-technical client — easiest to maintain | Upload the files to `public_html`, create a MySQL database + user in cPanel, import `schema.sql` via phpMyAdmin, then edit `config/db.php` with the cPanel database credentials |
| **VPS + Docker** | ~$5–20/month | You keeping full control | Copy the project to the server, run `docker compose up -d` (same as locally) |

On shared hosting, `config/db.php` picks up `DB_HOST`/`DB_USER`/`DB_PASS` from environment variables if present, otherwise edit the four constants at the top of the file (host is usually `localhost`, the user/pass/name come from cPanel).

> The site survives a broken database (text renders from defaults); only the
> forms, logos, media and admin panel need MySQL. The `assets/images/content`,
> `assets/uploads/avatars` and `content/` folders must stay writable by PHP.

### 8b. How the admin panel is accessed on the live site

Exactly like locally, but with your real domain:

- **Admin panel:** `https://yourdomain.com/admin/`
- **First login:** `admin` / `admin123` is auto-created on a fresh database —
  **change it immediately** (Settings → Change Password), then create accounts
  for your staff under **Admin Users** and delete the default `admin` account.
- All the same protections carry over: `.htaccess` blocks `config/`, `content/`
  and data files; every form is CSRF-protected; passwords are bcrypt-hashed.
  (If your host uses Nginx instead of Apache/LiteSpeed, ask them to replicate
  the few `.htaccess` rules — most cPanel hosts support them out of the box.)

### 8c. Production checklist (before handing to the client)

1. **HTTPS** — enable the free Let's Encrypt/SSL certificate your host offers,
   and add a rewrite so `http://` redirects to `https://`.
2. **Change the default admin password** and delete the `admin` account.
3. **Set real contact details** in Settings (WhatsApp, phone, address, hours) —
   the site and the structured data both read from there.
4. **Test a booking form submission** and check it appears in Leads + arrives
   by email (needs SMTP configured).
5. Keep an eye on the **Activity Log** for the first week.

### 8d. Getting found on Google (the client's "tax consultants" goal)

The site ships with the technical SEO basics — you still need the off-site steps
for rankings:

1. **Submit to Google Search Console** (free) — verify the domain, then paste
   your sitemap URL (`https://yourdomain.com/sitemap.xml` — served automatically)
   and request indexing of the homepage and Services page.
2. **Google Business Profile** (free, the single biggest factor for local
   searches like “tax consultants in Kampala”) — create/claim the firm's
   profile, use the exact name, address and phone from the site, add photos,
   and collect reviews from clients.
3. **On-page keywords** — the SEO tab in **Website Content** already controls
   the title/description shown in Google; tune them around what clients search
   (e.g. “tax consultants Uganda”, “URA tax advisory Kampala”).
4. **Backlinks & listings** — get listed in Uganda business directories, and
   link the site from LinkedIn and any professional association pages (ICPAU
   etc.). Every quality link helps.
5. **Be patient** — new sites typically take 1–3 months to appear; consistency
   (reviews, fresh content, real addresses matching across the web) is what
   moves rankings.

---

## 9. Security notes

- All form input is escaped on output; SQL uses prepared statements.
- CSRF tokens protect every POST form.
- Passwords hashed with `password_hash()` (bcrypt).
- Uploaded SVGs are scanned for script payloads; other images are MIME-checked.
- `.htaccess` blocks direct access to `config/`, `includes/`, `content/` and data files.
