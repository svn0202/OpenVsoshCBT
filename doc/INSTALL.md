# TCExam — Installation

There are two supported ways to run TCExam: a Docker stack (best for evaluation and quick
local runs) and a manual install on your own PHP/web/database stack (for production).

> Already running an older version? See [UPGRADE.md](UPGRADE.md) instead.

## Quick start (Docker)

Requires Docker with the Compose plugin:

```sh
make up            # or: docker compose up --build
```

The stack **installs itself automatically** — the container entrypoint runs the non-interactive
installer (`install/install_cli.php`) using the database settings from `docker-compose.yml`, so
there is no browser install step. Read the generated initial password with
`docker compose logs app`, then open <http://localhost:8080/admin/code/> and sign in as `admin`.
Set `TCEXAM_ADMIN_PASSWORD` before startup to supply your own password (minimum 12 characters).

The installer is idempotent and the configuration is kept in a named volume, so the installed
instance survives `docker compose down` / `up`; use `docker compose down -v` to start fresh. The
The interactive web installer is disabled.

See the **Quick start** section of [README.md](../README.md) for more details (PostgreSQL, font
generation and persistence notes).

## Manual install

1. **Install the prerequisites:**
   - PHP **>= 8.2** with the extensions: `mysqli` and/or `pgsql`, `gd`, `intl`, `bcmath`,
     `mbstring`, `zip`, `curl`, `xml`, `openssl`, `posix` (Oracle additionally needs `oci8`).
   - [Composer](https://getcomposer.org/).
   - A database server: MySQL/MariaDB, PostgreSQL or Oracle.
   - A web server (Apache + `mod_php` recommended — the app ships `.htaccess` access controls).

2. **Install dependencies and build the bundled assets:**

   ```sh
   composer install     # also generates the PDF fonts via the post-install hook
   make lang            # optional: pre-build the translation caches (built lazily otherwise)
   ```

3. **Set up the filesystem.** Point the web-server document root at the project directory, and
   make `cache/`, `install/`, `admin/backup/` and the `*/config/` parents writable by the web
   user.

4. **Run the installer.** Run `php install/install_cli.php` with the environment variables
   documented in [install/README.md](../install/README.md). The installer creates the
   configuration files, generates `K_RANDOM_SECURITY`, and assigns a unique initial administrator
   password. The web installer is intentionally unavailable.

5. **Secure the installation:**
   - **Delete the `install/` directory** once installation is complete.
   - Store the generated initial password securely, change it on first sign-in, and create named
     administrator accounts for routine use.

   See [SECURITY.md](../SECURITY.md) for the full hardening checklist.

## Further documentation

- Detailed manual: [install/README.md](../install/README.md)
- Project website: <https://tcexam.org>
- Upgrade notes: [UPGRADE.md](UPGRADE.md)
