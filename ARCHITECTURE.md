# Owere & Associates — Architecture & System Blueprint

> Developer-facing specification: brand, services, infrastructure, layout & admin requirements.
> This document supersedes the previous `agent_context.md` — see that file for a migration pointer.

---

## 1. Executive Summary & Brand Identity
Owere & Associates is a premier tax advisory, auditing, corporate compliance, and NGO financial management practice based in Uganda. The web platform is engineered to project corporate authority, demonstrate social proof through client logos, and convert visitors into booked consultations seamlessly.

### Visual Identity & Color System
* **Primary Base:** `#0B192C` (Deep Executive Navy — navigation, hero banners, primary CTAs)
* **Accent Highlight:** `#D4AF37` (Warm Gold — badges, borders, active states, WhatsApp ring)
* **Secondary Surface:** `#1E293B` (Slate Gray — cards, footers, section backgrounds)
* **Background Neutral:** `#F8FAFC` (Off-White — crisp content sections)
* **Text Primary:** `#0F172A` (Charcoal Black — body and headlines)

---

## 2. Infrastructure & Communication Architecture

### Mail Infrastructure (Zoho Mail Lite — default; Google Workspace optional)
The domain `owereassociates.com` routes to 3 dedicated mailboxes:
* `director@owereassociates.com`: Managing Director & executive partnerships.
* `info@owereassociates.com`: General inquiry inbox & web consultation booking alerts.
* `finance@owereassociates.com`: Client billing, tax invoicing, and retainers.

### Instant Messaging Routing
* **Floating WhatsApp Business Widget:** Persistent bottom-right floating trigger.
* **Contextual Prefill:** Tapping WhatsApp on a service card pre-populates a message:
  > *"Hello Owere & Associates, I am interested in your [Service Name] offering and would like to inquire about a consultation."*
* **Configurable Target:** Number and welcome greeting editable via `/admin` settings.

---

## 3. Consolidated Core Service Pillars

To prevent site congestion and eliminate duplicate offerings, all services are grouped into **3 Core Pillars**:

### Pillar 1: Tax Advisory & Compliance (URA)
* **Scope:** Corporate & individual tax planning, URA tax health checks, EFRIS setup & integration, and filing monthly/quarterly/annual tax returns.
* **Actions:** "Book Consultation" (modal trigger) & "Quick Inquiry via WhatsApp".

### Pillar 2: NGO Financial Management & Grant Audits
* **Scope:** Donor financial reporting, grant accountability frameworks, project fund tracking, and setup of donor-compliant accounting systems (QuickBooks/Xero).
* **Actions:** "Book Consultation" (modal trigger) & "Quick Inquiry via WhatsApp".

### Pillar 3: Corporate Advisory & Business Registration (URSB)
* **Scope:** Company incorporation with URSB, annual returns filing, corporate secretarial services, bookkeeping, and financial modeling.
* **Actions:** "Book Consultation" (modal trigger) & "Quick Inquiry via WhatsApp".

---

## 4. Frontend Layout & User Flow Blueprint

1. **Hero Section:** Executive tagline, high-trust background image, and primary "Book Consultation" CTA.
2. **Client Credibility Banner (Logo Grid):**
   * Positioned immediately below the Hero section.
   * Renders monochrome/gray client, NGO, and compliance framework logos.
   * Powered dynamically by the `partner_logos` database table with SVG/PNG sample fallbacks.
3. **Consolidated Service Cards:** 3-column responsive layout with dual action buttons (**Book Form** and **WhatsApp**).
4. **Booking & Consultation Modal:**
   * Auto-selects the clicked service in a dropdown.
   * Captures: Name, Company/Organization, Email, Phone, Preferred Date, and Project Brief.
   * On Submit: Inserts into MySQL `inquiries` table and sends an SMTP notification (provider configurable in `/admin` → Settings) to `info@owereassociates.com`.

---

## 5. Administrative Portal (`/admin`) Requirements

The client has full autonomy to update text, photos, and partner logos without touching code:

* **Authentication:** Password hashing via `password_hash()` (BCRYPT) and secure session handling.
* **Logo Manager (`/admin/logos.php`):** Drag-and-drop or file select interface allowing uploading (`.png`, `.jpg`, `.webp`, `.svg`), toggling visibility, or deleting client logos.
* **Content Editor (`/admin/content.php`):** Form fields to edit homepage hero text, service titles, and descriptions.
* **Inquiry Dashboard (`/admin/dashboard.php`):** Filterable table of leads received via the website, showing client contact info, selected service, date, and status (`new`, `contacted`, `closed`).
* **Settings Manager (`/admin/settings.php`):** Fields to update the active WhatsApp phone number and system notification email addresses.

---

## 6. Technical Guidelines & Security Constraints

* **Database Operations:** Use PDO with prepared statements for all queries (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) to prevent SQL injection.
* **File Upload Safety:** Validate MIME types and file extensions for image uploads (`assets/images/partners/`). Prevent execution of uploaded scripts.
* **Code Standards:** Native PHP 8.x, clean separation of database configs (`config/db.php`), reusable header/footer includes, and modern CSS custom properties (`var(--primary)`, `var(--accent)`).