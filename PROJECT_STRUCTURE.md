# Owere & Associates — Project Architecture & Directory Structure

## 1. Executive Summary & Stack Overview
Owere & Associates is a financial, tax advisory, and corporate compliance web platform built to handle client consultations, present verified organizational credibility (partner logos), and route inquiries to both a database log and SMTP mailboxes.

* **Backend:** PHP 8.x (Native / PDO)
* **Database:** MySQL 8.0+
* **Frontend:** HTML5, CSS3 (Executive Navy & Gold Design System), Vanilla JS
* **Mail Infrastructure:** SMTP via Zoho Mail Lite by default (`info@owereassociates.com`, `director@`, `finance@`); any provider works
* **Integrations:** Floating WhatsApp Business API trigger

---

## 2. Directory & File Blueprint

```text
owere-associates/
├── assets/
│   ├── css/
│   │   ├── main.css              # Executive theme tokens (Navy #0B192C, Gold #D4AF37)
│   │   └── admin.css             # Dashboard stylesheet
│   ├── js/
│   │   ├── main.js               # Modal handlers, smooth scroll, WhatsApp routing
│   │   └── admin.js              # CMS editor (tabs, rich text, image upload, AJAX save), lead filters
│   └── images/
│       ├── hero-advisory.jpg     # Main hero background visual
│       ├── partners/             # Dynamic client & partner logo uploads
│       │   ├── sample-corporate.svg
│       │   ├── sample-ngo.svg
│       │   └── sample-audit.svg
│       └── icons/                # Service vector icons
├── config/
│   ├── db.php                    # MySQL PDO database connection
│   └── mail.php                  # SMTP configuration (Zoho by default, any provider)
├── includes/
│   ├── header.php                # Global site header & navigation
│   ├── footer.php                # Global footer & floating WhatsApp widget
│   └── functions.php             # Security sanitization, session checks, file uploaders
├── admin/
│   ├── index.php                 # Admin login page
│   ├── dashboard.php             # Inquiry lead viewer & filter table
│   ├── logos.php                 # Dynamic partner logo manager (Upload/Delete)
│   ├── media.php                 # Media Library: upload, browse, copy paths, delete images
│   ├── content.php               # Full no-code CMS for the entire website
│   ├── settings.php              # WhatsApp number & SMTP email config
│   └── logout.php                # Session termination
├── api/
│   ├── submit-booking.php        # Processes booking form -> DB -> SMTP Email
│   ├── whatsapp-router.php       # Dynamic pre-filled WhatsApp link generator
│   ├── upload-image.php          # Authenticated AJAX image upload (CMS editor)
│   └── media-list.php            # JSON list of uploaded images (media picker)
├── content/
│   ├── site-content.json         # Whole-site editable content (JSON, merged with defaults)
│   └── backups/                  # Timestamped content backups (kept automatically)
└── assets/images/content/        # Images uploaded from the CMS editor
├── ARCHITECTURE.md              # System blueprint, brand & conventions
├── architecture-config.yml      # Project metadata (stack, DB, services)
├── index.php                     # Public homepage
├── services.php                  # Consolidated 3 service pillars & booking triggers
└── schema.sql                    # Initial MySQL database table definitions