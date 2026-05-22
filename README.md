# SimpleBlog

A self-contained PHP 8.5 + SQLite blog with admin UI, posts, comments,
file uploads, SMTP email, and user management. Ships in Docker.

## Quick start

### Production (behind a reverse proxy on `npm_default`)

```sh
docker compose up -d --build
```

The container exposes port 80 on the `npm_default` external network. Point
your reverse proxy (e.g. Nginx Proxy Manager) at `http://simpleblog:80`.

### Local development

A dev compose file with a published port is gitignored. Create it as:

```yaml
# docker-compose.dev.yml
services:
  simpleblog:
    build: .
    container_name: simpleblog-dev
    restart: unless-stopped
    ports:
      - "8090:80"
    volumes:
      - ./www:/var/www/html
      - ./db:/var/db
      - ./config:/var/config:ro
    mem_limit: 256m
    memswap_limit: 256m
    read_only: true
    tmpfs:
      - /tmp
      - /var/run
      - /var/lock
      - /var/log/apache2
```

Then:

```sh
docker compose -f docker-compose.dev.yml up -d --build
```

Visit http://localhost:8090.

## First-time admin

On first run with an empty database, a seed admin is created:

- Username: `admin`
- Password: a random one-time password, written to the container log —
  read it with `docker logs <container>`
- `must_change_password = 1`

After logging in with the logged password you are forced to set a new one
(at least 12 characters) before you can navigate anywhere else.

## Configuration

The app reads `/var/config/config.php` (mounted from `./config/`) which is
**outside** the web root. Copy `config/config.example.php` to
`config/config.php` and fill in any SMTP credentials you want to hard-code.
Anything left commented out is editable through Site Settings → Email.

### Site URL

In Site Settings → General, set the **Site URL** (e.g.
`https://yourdomain.com`) so password-reset and email-verification links
in outgoing email use the correct host. If left blank, it's auto-detected
from `HTTP_HOST` and `X-Forwarded-Proto`.

### Email-based account recovery

When SMTP is configured (either via `config.php` constants or Site
Settings → Email), three flows become active:

- **Forgot password** — `/forgot_password.php` sends a tokenized reset
  link. Token is one-use, expires in 1 hour.
- **Email verification** — new registrations get an email and cannot
  sign in until verified. Resend from `/resend_verification.php`.
- **Test email** — Site Settings → Email has a "Send test email" button.

When SMTP is **not** configured, registrations bypass verification so dev
installs aren't soft-bricked.

## Upgrading

The application code in `www/` is bind-mounted, so an upgrade is just
swapping those files for a newer release. Your data (`db/`), uploads
(`www/uploads/`), and `config/` are **separate mounts and are preserved**.
The SQLite schema migrates itself on the next request — `db_init()` in
`db.php` runs `CREATE TABLE IF NOT EXISTS` plus idempotent `ALTER`s and any
data migrations.

**Always back up first — the schema migration is one-way.**

1. **Back up** data, config, and the current code (for rollback):
   ```sh
   cd /path/to/simpleblog
   docker compose stop          # quiesce SQLite for a clean copy
   tar czf ../simpleblog-backup-$(date +%F-%H%M%S).tar.gz db config www
   ```
2. **Get the new code** into `www/`:
   - Deployed from a git clone: `git fetch --tags && git checkout vX.Y.Z`
     (or `git pull` for the latest `main`).
   - Deployed by copying files: `rsync` the new release's `www/` over yours,
     **excluding `uploads/`**, then delete files removed upstream (older
     installs may still carry e.g. `calendar.php` or a stray `index.html`):
     ```sh
     rsync -a --exclude 'uploads/' /path/to/new/www/ ./www/
     ```
3. **Restart** — the DB migrates on the first request:
   ```sh
   docker compose up -d         # add --build only if the Dockerfile changed
   ```
   Because `www/` is live-mounted, a rebuild is usually unnecessary.
4. **Verify**: the footer shows the new `vX.Y.Z`, posts/users/comments are
   intact, admin login works, and `docker logs <container>` shows no PHP
   errors.

**Rollback**: `docker compose stop`, restore `www/` and `db/` from the backup
tarball, then `docker compose up -d`.

### Update notifications

Admins see an "update available" banner (Settings → Dashboard) when the
running `APP_VERSION` is older than the latest published on this repo's
`main` branch — a cached, once-per-day check with a manual **Check now**
button (see `UPDATE_SOURCE_URL` in `www/version.php`). The container needs
outbound DNS + HTTPS for the check to reach GitHub.

## Security posture

See `CHANGELOG.md` for the most recent security review. Highlights:

- CSRF tokens on every POST, with empty-token guard
- HTTPS-aware `Secure` cookie + HSTS headers
- CSP with `form-action 'self'` and `frame-ancestors 'none'`
- IP rate limits on login, registration, comments, password reset,
  verification resend
- HTML sanitization (`sanitize_html` in `db.php`) for rich-text post
  content; everything else is `htmlspecialchars`
- `JSON_HEX_*` flags on every `json_encode` embedded in a script /
  attribute context
- File uploads: MIME-detected by `finfo`, size-capped, randomly named,
  uploaded into a directory whose `.htaccess` disables PHP execution
- Docker hardening: read-only rootfs with tmpfs for the writeable paths;
  256 MB memory limit

## Project layout

```
www/                 # PHP application root (mounted to /var/www/html)
config/              # Outside web root (mounted to /var/config:ro)
db/                  # SQLite data (mounted to /var/db; gitignored)
Dockerfile           # PHP 8.5 + Apache + SQLite-from-source
docker-compose.yml   # Production compose
nginx.conf           # Example NPM reverse-proxy config
server-prep.sh       # One-time host bootstrap (Docker + NPM + Portainer)
```
