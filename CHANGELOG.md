# Changelog

All notable changes to SimpleBlog are documented here. Entries are tagged
with a severity hint when the change is security-relevant.

## [0.3.0] — 2026-05-22 — Update notifications

### Added
- **Version upgrade notification.** Admins are now told when a newer
  SimpleBlog release is published. The app fetches `APP_VERSION` from the
  public repo's `main` branch (`UPDATE_SOURCE_URL` in `version.php`), caches
  it in `site_settings`, and compares it to the running version.
  - An amber dot appears on the **Settings** nav link (admins only) when an
    update is available.
  - The dashboard shows an "Update available: vX.Y.Z" line with a changelog
    link, plus a **Check now** button to force an immediate check.
  - The check runs lazily on a dashboard view, gated to once per 24h
    (`run_update_check()` in `db.php`), with a 4s timeout that fails silently
    so a network blip never blocks the page or clears a known-good value.

## [0.2.2] — 2026-05-22 — Security sweep

Full security review of the now-standalone codebase. Hardening only, no
functional changes. All inline JavaScript was removed so the Content-
Security-Policy no longer permits `'unsafe-inline'` for scripts.

### Fixed
- **Open redirect** in `comment.php` — the redirect validator accepted
  `//host` and `/\host` (protocol-relative URLs), allowing off-site
  redirects. Only genuine same-site paths now pass. [low]

### Changed
- **CSP `script-src`** — dropped `'unsafe-inline'`; inline `<script>` blocks
  now carry a per-request nonce (`csp_nonce()` in `auth.php`). All 38 inline
  `on*` handlers converted to `addEventListener` / event delegation; global
  behaviors (theme toggle, user menu, confirm dialogs) moved to `nav.js` via
  `data-action` / `data-confirm`. `style-src` keeps `'unsafe-inline'`
  deliberately (inline styles are pervasive, far lower risk). [medium]
- **Seeded admin** — fresh installs get a random initial password (written to
  the error log / `docker logs`) instead of `admin`/`admin`, closing the
  first-login takeover race. Still `must_change_password=1`. [low]
- **`sanitize_html()`** — the inline `style` attribute is now filtered through
  a CSS-property allowlist (previously a regex blocklist), and
  `data:image/svg+xml` is no longer allowed in `src`/`href`. [low]
- **Login timing** — a dummy bcrypt verify now runs when the username does not
  exist, equalizing response time to reduce username enumeration. [low]

### Added
- **`TRUST_PROXY_HEADERS`** config constant (documented in
  `config.example.php`) — gates trust of `X-Real-IP` / `X-Forwarded-For` so a
  directly-exposed instance can't be fed spoofed client IPs to evade rate
  limits or forge audit-log entries. Defaults to `true` (proxy deployment). [low]

### Notes
- Verified on the dev image: CSP header nonce matches every inline script, no
  `unsafe-inline` in `script-src`, zero inline `on*` handlers, admin pages load
  when authenticated. Prepared statements, CSRF, upload validation, and the
  secret/DB git-ignore status were reviewed and found solid.
- SimpleBlog is now an independent project; changes are no longer back-ported
  to or from GameNight.

## [0.2.1] — 2026-05-15 — Desktop framework polish

Iteration on v0.2.0 after the editorial-reader layout felt too austere
on wide screens. Inspired by Auth0 Blog, Tom Preston-Werner's site, and
Lattice — all from the [Jekyll showcase](https://jekyllrb.com/showcase/).

### Changed
- **Wider main wrapper** on desktop (1080px) while keeping the reading
  column (`.post-body`) capped at 68ch for comfortable reading.
- **Posts render as soft cards** — light surface bg, border, generous
  padding, subtle hover lift. Replaces the flat-with-dividers look that
  read as a thin column on big monitors.
- **Featured/pinned posts** get an accent left-rule + slightly larger
  title (`.is-featured` modifier).
- **Horizontal nav links** restored in the desktop nav (Home, Posts,
  Settings for admins). Hidden below 720px where the user dropdown
  handles it.
- **Mobile spacing** tightened — cards now have 1.5rem padding on
  phones, list gap reduced.

### Notes
- Theme tokens, dark mode, fonts, footer archive, auth/admin pages:
  all unchanged.
- The `.is-featured` modifier is set automatically for pinned posts;
  unpinned posts can still be visually highlighted later (e.g., "newest"
  treatment) by adding the same class.

## [0.2.0] — 2026-05-15 — Editorial reader redesign + calendar removal

### Removed
- **Calendar feature** — `www/calendar.php`, the `events` and `event_exceptions`
  tables, all calendar links in the nav, the homepage "Upcoming Events"
  block, and the `show_upcoming_events` site setting. SimpleBlog is now a
  pure blog. Existing event data is cleaned up by an idempotent one-shot
  migration (`calendar_removed_v1` marker in `site_settings`).

### Added
- **Editorial Reader theme** — single-column reading layout, serif post
  titles (Source Serif 4), sans-serif body (Inter), max-width 68ch.
- **Dark mode** — system-aware via `prefers-color-scheme`, with a manual
  toggle in the nav that persists choice via `localStorage`. Inline
  bootstrap script in `_head.php` avoids FOUC.
- **Self-hosted webfonts** — Inter (variable) + Source Serif 4 (variable)
  under `www/vendor/fonts/`. No external CDN, no CSP changes needed.
- **Reading time** — every post displays a `N min read` estimate;
  computed by `reading_time()` in `www/db.php`.
- **Footer archive** — month index moved out of the fixed left sidebar
  into a `<details>` dropdown in the page footer. No JavaScript needed.

### Changed
- **`style.css` rewritten** around CSS custom properties: tokens for
  surface/text/accent in both light and dark modes; user-configured
  `accent_color` continues to drive `--accent` in both themes.
- **`_nav.php`** — collapsed from two rows (brand + tabs) to a single
  row (brand + theme toggle + user dropdown). Removes the mobile
  hamburger; navigation now lives inside the user menu, which simplifies
  small-screen layout.
- **Post template** — `.post-article` instead of `.post-card`; no card
  background; subtle dividers between posts; pinned posts render as a
  small chip near the meta line instead of a yellow tinted card.
- **`comment.php`** — `type` parameter now only accepts `'post'`.
- **Footer** — version + date moved into a clean small-print row at the
  bottom of the read column.

### Notes
- `posts_chunk.php` JSON contract preserved (`data-chunk-count` marker
  still emitted) — the infinite-scroll observer in index.php is unchanged.
- CSRF, rate limits, HTTPS-aware headers, all auth flows: unchanged from
  v0.1.0.
- Admin pages keep their `.dash-wrap` → `.admin-wrap` shell; tables
  remain horizontal-scroll on narrow viewports (card-list-on-mobile is
  deferred).

## [0.1.0] — 2026-05-15 — Initial release + security hardening pass

First tagged release. Exposes `APP_VERSION` via `www/version.php`, shown in
the footer of every page and on the admin dashboard.

### Security hardening pass

Back-ported from [GameNight](https://github.com/Isorgcom/GameNight)'s
security review. SimpleBlog shares a common ancestor with GameNight, so
fixes that map onto features SimpleBlog has are applied here. Items that
touch GameNight-only endpoints (WhatsApp / SMS / RSVP / walk-in / API)
are intentionally out of scope.

### Added
- **Forgot-password flow** — `forgot_password.php` + `reset_password.php`.
  Tokenized link emailed via the existing `send_email()` helper. Token in
  POST body, not GET. 1-hour expiry, one-use.
- **Email verification** — `verify_email.php` + `resend_verification.php`.
  Required only when SMTP is configured; dev installs without SMTP still
  work normally.
- **`rate_limited()` helper** in `auth.php` — wraps the activity-log
  COUNT-with-time-window pattern so future endpoints can opt in trivially.
- **`get_site_url()` helper** in `db.php` + a `site_url` admin setting
  for building absolute URLs in outgoing emails (replaces blind trust in
  `HTTP_HOST`).
- **`enforce_password_change()` middleware** — gated at the top of
  `auth.php` so a forced password change can't be bypassed by visiting a
  page that doesn't call `require_login()`.

### Changed
- **`CSP`** — added `form-action 'self'` to limit form submission targets.
  [medium]
- **HSTS + Secure cookie flag** — emitted only when the request was
  served over TLS (detected via `HTTPS` server var or
  `X-Forwarded-Proto: https`). Keeps dev (HTTP) working. [medium]
- **Password minimum** — registration form raised from 8 → 12 chars to
  match the rest of the app. [low]
- **Seeded admin** — `admin/admin` now ships with `must_change_password=1`
  and is redirected to `/settings.php?force_change=1` until they change it.
  [medium]
- **`json_encode()` in JS contexts** — all 20 sites now pass
  `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT` so
  user-derived strings can't break out of `<script>` blocks or HTML
  attribute boundaries. [medium]
- **Banner upload MIME detection** — replaced deprecated
  `mime_content_type()` with `finfo`. [low]
- **`docker-compose.yml`** — `mem_limit: 256m`, `read_only: true`,
  tmpfs at `/tmp`, `/var/run`, `/var/lock`, `/var/log/apache2`. [medium]
- **`db_log_activity()`** — strips `\x00-\x1F\x7F` to prevent log
  injection / forgery. [medium]

### Fixed
- **CSRF empty-token bypass** — `csrf_verify()` now returns false when
  either the stored session token or the submitted token is empty.
  Previously `hash_equals('', '')` returned true, letting fresh-session
  POSTs through. [high]
- **Rate limiting** — login (10/IP/hr), registration (5/IP/hr),
  comments (10/IP/5min), forgot-password (3/IP/hr),
  resend-verification (3/IP/hr). [medium]
