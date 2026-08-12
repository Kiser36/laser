# Docker Commands — Owere & Associates Website

Everything you need to run, stop, inspect and maintain the site with Docker.
Run all commands from the **project root** (the folder containing `docker-compose.yml`).

> **Tip for Windows:** open **Git Bash** (or PowerShell) inside the project folder.
> The short form `docker compose` (space) works on all modern Docker versions;
> the older `docker-compose` (hyphen) also works if your install predates 2020.

---

## 🚀 Everyday commands

| What you want | Command |
|---|---|
| **Start the site** (first time or after shutdown) | `docker compose up -d` |
| Start **and rebuild** the PHP image (after changing the Dockerfile or php.ini) | `docker compose up -d --build` |
| Start in the foreground with **live logs** (Ctrl+C stops it) | `docker compose up` |
| **Stop** the containers (keeps your database data) | `docker compose down` |
| **Stop** containers but leave them existing (faster restart) | `docker compose stop` |
| **Restart** everything (e.g. after a config change) | `docker compose restart` |
| **Full reset** — stops everything AND **wipes the database data** ⚠️ | `docker compose down -v` |
| Build the PHP image only (no start) | `docker compose build` |

**After starting, open:**
- Website → http://localhost:8080
- Admin panel → http://localhost:8080/admin

---

## 📋 Checking what's running

| What you want | Command |
|---|---|
| See container status (running / exited / ports) | `docker compose ps` |
| See all containers incl. stopped ones | `docker ps -a` |
| Follow the website's log output (PHP errors, requests) | `docker compose logs -f web` |
| Follow the database's log output | `docker compose logs -f db` |
| Live CPU/memory usage of the containers | `docker stats` |

---

## 🖥️ Going inside the containers

| What you want | Command |
|---|---|
| Open a **shell inside the PHP container** (browse/run files) | `docker compose exec web bash` |
| Run a one-off PHP command (e.g. check version) | `docker compose exec web php -v` |
| **Lint-check a PHP file** for syntax errors | `docker compose exec web php -l index.php` |
| Open a **MySQL shell** inside the DB container | `docker compose exec db mysql -u root -prootpassword owere_db` |
| Run any SQL directly (e.g. see the leads table) | `docker compose exec db mysql -u root -prootpassword owere_db -e "SELECT * FROM inquiries;"` |

---

## 💾 Database backup & restore

| What you want | Command |
|---|---|
| **Back up** the whole database to a file | `docker compose exec db mysqldump -u root -prootpassword owere_db > backup.sql` |
| **Restore** a backup file into the database | `docker compose exec -T db mysql -u root -prootpassword owere_db < backup.sql` |

> Store `backup.sql` somewhere safe (it contains all your leads, logos and admin accounts).
> Run a backup before doing anything destructive.

---

## 🧹 Cleanup & free up disk space

| What you want | Command |
|---|---|
| Remove stopped containers + unused images/networks | `docker system prune` |
| Remove **everything** unused incl. images (answer `y`) | `docker system prune -a` |
| Remove unused Docker volumes | `docker volume prune` |
| Stop everything and **delete the site's image too** | `docker compose down --rmi all` |

---

## ⚠️ Things worth knowing about THIS project

1. **Your code edits are instant.** The `web` container mounts your project folder
   straight into the site (`.:/var/www/html`), so any PHP/CSS/JS change appears
   after a browser refresh — **no rebuild needed**. Only changes to `Dockerfile`
   or `docker/php.ini` require `docker compose up -d --build`.

2. **The database is a named volume (`db_data`).** `docker compose down` keeps your
   data. Only `docker compose down -v` deletes it — treat that as "factory reset".

3. **The schema auto-seeds on first run only.** `schema.sql` is copied into the DB
   container on the very first start of the `db_data` volume. To re-seed (fresh DB):
   `docker compose down -v` then `docker compose up -d`.

4. **Ports:** website = `8080`, MySQL = `3307` (not the usual 3306, to avoid
   clashing with a local MySQL/XAMPP install). Tools like MySQL Workbench connect
   to `localhost:3307`, user `root`, password `rootpassword`.

5. **Database credentials** are set in `docker-compose.yml` (root / rootpassword,
   database `owere_db`) — the site reads them via the `DB_*` environment variables.

6. **Admin login:** the first time you open `/admin`, the site auto-creates a
   default account — **username `admin`, password `admin123`** (created by
   `ensure_default_admin()` in `includes/functions.php`). Log in and change the
   password immediately under **Settings → Change Password**.

---

## ❓ Quick troubleshooting

| Symptom | Fix |
|---|---|
| `Port 8080 is already in use` | Something else uses 8080. Change the port in `docker-compose.yml` (e.g. `8081:80`) then `docker compose up -d` |
| Site loads but no styles / 404s | Make sure Apache rewrite is on (it is, via the Dockerfile) and you're viewing through http://localhost:8080 |
| DB connection error on the site | Wait for the DB health check: `docker compose ps` — then `docker compose restart web` |
| Weird stale behaviour after edits | `docker compose restart web` |
| Want logs of a specific moment | `docker compose logs --tail=50 web` (last 50 lines) |
