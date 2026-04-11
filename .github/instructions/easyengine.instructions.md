---
applyTo: "app/Core/Engines/EasyEngine/**,app/Http/Controllers/SiteController.php"
---

# EasyEngine v4 CLI Reference

Source: https://easyengine.io/cli/commands/site/

EasyEngine (ee) is a Docker-based WordPress/PHP server management CLI.
All `ee` commands in this project are executed remotely via SSH using `sudo`.
For **site-management flows** covered by this instruction (`app/Core/Engines/EasyEngine/**` and `SiteController`), commands run as the server's configured `ssh_execution_username`, which is typically `easyengine` after provisioning.
Provisioning recipes are separate: they default to `root` and may allow a different pre-run execution user depending on recipe metadata.

## Critical: Non-Interactive Execution

All `ee` commands run over SSH — there is never an interactive TTY.
Any command that prompts for confirmation **must** include `--yes` to proceed automatically.
Without `--yes`, the command will hang indefinitely waiting for input that never arrives.

Commands that require `--yes` in this project:
- `ee site delete <domain> --yes`
- `ee site create <domain> --type=wp --yes` (for WordPress type only)

---

## ee site create

Runs site installation with the specified site type.
Default type when `--type` is omitted is **html**.

### HTML site

```bash
sudo ee site create example.com
sudo ee site create example.com --ssl=le
sudo ee site create example.com --ssl=le --wildcard
sudo ee site create example.com --ssl=self
sudo ee site create example.com --public-dir=src
sudo ee site create example.com --type=html --alias-domains='a.com,*.a.com,b.com' --ssl=le
```

**Options:**
- `--ssl=<le|self|inherit|custom>` — Enable SSL (le = Let's Encrypt)
- `--ssl-key=<path>` / `--ssl-crt=<path>` — Custom SSL certificate paths
- `--wildcard` — Wildcard SSL certificate
- `--type=<html|php|wp>` — Site type
- `--alias-domains=<domains>` — Comma-separated list of alias domains
- `--skip-status-check` — Skip site status check after creation
- `--public-dir=<dir>` — Custom source directory inside htdocs

### PHP site

```bash
sudo ee site create example.com --type=php
sudo ee site create example.com --type=php --with-db
sudo ee site create example.com --type=php --ssl=le
sudo ee site create example.com --type=php --php=8.0
sudo ee site create example.com --type=php --with-db --dbhost=localhost --dbuser=username --dbpass=password
sudo ee site create example.com --type=php --public-dir=public
```

**Additional PHP options:**
- `--cache` — Use Redis cache for PHP
- `--with-db` — Create a database for the PHP site
- `--local-db` — Separate DB container instead of global DB
- `--with-local-redis` — Local Redis container
- `--php=<5.6|7.0|7.2|7.3|7.4|8.0|latest>` — PHP version (default: latest)
- `--dbname`, `--dbuser`, `--dbpass`, `--dbhost` — Database credentials
- `--dbprefix` — Database table prefix
- `--skip-check` — Skip database connection check
- `--force` — Reset remote database if not empty

### WordPress site

```bash
sudo ee site create example.com --type=wp
sudo ee site create example.com --type=wp --ssl=le
sudo ee site create example.com --type=wp --mu=subdir
sudo ee site create example.com --type=wp --mu=subdom
sudo ee site create example.com --type=wp --cache
sudo ee site create example.com --type=wp --php=8.0
sudo ee site create example.com --type=wp --dbhost=localhost --dbuser=username --dbpass=password
sudo ee site create example.com --type=wp --title=MyBlog --locale=pt_BR --admin-email=admin@example.com --admin-user=admin --admin-pass=secret
sudo ee site create example.com --type=wp --yes  # skip confirmation prompt (required for non-interactive use)
```

**Additional WordPress options:**
- `--cache` — Use Redis cache for WordPress
- `--mu=<subdir|subdom>` — WordPress Multisite type
- `--title=<title>` — Site title
- `--admin-user`, `--admin-pass`, `--admin-email` — WP admin credentials
- `--locale=<locale>` — WordPress locale (e.g. `pt_BR`, `en_US`)
- `--version=<version>` — WordPress version (accepts version number, `latest`, or `nightly`)
- `--skip-content` — Download WP without default themes and plugins
- `--skip-install` — Skip wp-core install
- `--proxy-cache=<on|off>` — Enable/disable proxy cache (default: off)
- `--proxy-cache-max-size=<size>` — Max proxy cache size (e.g. `1g`)
- `--proxy-cache-max-time=<time>` — Max proxy cache time (e.g. `30s`)
- `--yes` — **Do not prompt for confirmation** (required for automated/SSH execution)
- `--vip` — WordPress VIP GO site

---

## ee site delete

Deletes a website, its Docker containers, files, and database.

```bash
sudo ee site delete example.com --yes
```

**`--yes` is mandatory** when running over SSH. Without it the command will hang.

---

## ee site list

Lists all created websites.

```bash
sudo ee site list
sudo ee site list --format=json   # returns JSON array (used for parsing in PHP)
```

**Options:**
- `--format=<table|json|csv|yaml|count>` — Output format (default: table)

The `--format=json` flag is used in `EasyEngineCommandBuilder::buildListSites()` so the output can be parsed programmatically.

---

## ee site info

Displays all relevant site information, credentials and useful links.

```bash
sudo ee site info example.com
sudo ee site info example.com --format=json
```

**Options:**
- `--format=<table|json|csv|yaml|count>` — Output format

---

## ee site enable

Enables a site. Starts Docker containers if they are stopped.

```bash
sudo ee site enable example.com
sudo ee site enable example.com --verify     # also checks global services (slower)
sudo ee site enable example.com --force
sudo ee site enable example.com --refresh    # regenerates docker-compose.yml first
```

**Options:**
- `--force` — Force enable
- `--verify` — Verify dependent global services are working
- `--refresh` — Force enable after regenerating docker-compose.yml

---

## ee site disable

Disables a site. Stops and removes its Docker containers.

```bash
sudo ee site disable example.com
```

---

## ee site update

Updates or upgrades a site configuration.

```bash
sudo ee site update example.com --ssl=le
sudo ee site update example.com --ssl=le --wildcard
sudo ee site update example.com --ssl=self
sudo ee site update example.com --php=8.0
sudo ee site update example.com --proxy-cache=on
sudo ee site update example.com --proxy-cache=on --proxy-cache-max-size=1g --proxy-cache-max-time=30s
sudo ee site update example.com --add-alias-domains='a.com,*.a.com,b.com'
sudo ee site update example.com --delete-alias-domains='a.com'
```

**Options:**
- `--ssl=<le|self|inherit|custom|"off">` — Update SSL configuration
- `--wildcard` — Enable wildcard SSL
- `--php=<version>` — Change PHP version
- `--proxy-cache=<on|off>` — Enable/disable proxy cache
- `--proxy-cache-max-size=<size>` — Max proxy cache size
- `--proxy-cache-max-time=<time>` — Max proxy cache TTL
- `--proxy-cache-key-zone-size=<size>` — Proxy cache key zone size
- `--add-alias-domains=<domains>` — Add alias domains
- `--delete-alias-domains=<domains>` — Remove alias domains

---

## ee site clean

Clears Object and Page cache for a site.

```bash
sudo ee site clean example.com
```

---

## ee site restart / reload / refresh

```bash
sudo ee site restart example.com   # Restart Docker containers
sudo ee site reload example.com    # Reload services without restarting containers
sudo ee site refresh example.com   # Re-create docker-compose file and update containers
```

---

## ee site ssl / ssl-renew / ssl-verify

```bash
sudo ee site ssl example.com         # Verify and renew if expired
sudo ee site ssl-renew example.com   # Force renew Let's Encrypt certs
sudo ee site ssl-verify example.com  # Verify SSL challenge
```

---

## Global Parameters

These can be appended to any `ee` command:

| Flag | Description |
|------|-------------|
| `--sites-path=<path>` | Absolute path where all sites are stored |
| `--locale=<locale>` | Locale for WordPress (e.g. `pt_BR`) |
| `--le-mail=<email>` | Email for Let's Encrypt |
| `--wp-mail=<email>` | Default email for WordPress installations |
| `--[no-]color` | Colorize output |
| `--debug[=<group>]` | Show PHP errors and verbose bootstrap |
| `--quiet` | Suppress informational messages |

---

## How EasyEngine Maps to This Project

| Engine action (`runAction`) | EasyEngineCommandBuilder method | Generated command |
|---|---|---|
| `list_sites` | `buildListSites()` | `sudo ee site list --format=json` |
| `create_site` | `buildCreateSite($domain, $options)` | `sudo ee site create <domain> [flags]` |
| `delete_site` | `buildDeleteSite($domain)` | `sudo ee site delete <domain> --yes` |
| `site_info` | `buildSiteInfo($domain)` | `sudo ee site info <domain> --format=json` |
| `enable_site` | `buildToggleSite($domain, true)` | `sudo ee site enable <domain>` |
| `disable_site` | `buildToggleSite($domain, false)` | `sudo ee site disable <domain>` |
| `clean_site` | `buildCleanSite($domain)` | `sudo ee site clean <domain>` |
| `update_site` | `buildUpdateSiteCommands($domain, $options)` | one or more `sudo ee site update <domain> [flags]` commands |

Read-only commands are wrapped with a login shell so JSON output can be parsed cleanly. State-changing commands are PTY-wrapped to keep EasyEngine/Docker behavior working correctly over SSH callbacks.
