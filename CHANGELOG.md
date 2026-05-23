# Changelog

All notable changes to SimpleBlog are documented here. Entries are tagged
with a severity hint when the change is security-relevant.

## [0.7.3] — 2026-05-23 — Mobile nav fit

### Changed
- **Site name auto-fits to one line.** On narrow screens the nav brand shrinks its
  font (via `nav.js`, measured against the available width) instead of wrapping to
  two lines; full size on desktop, skips logo-image headers, re-fits on resize.

### Fixed
- The nav **Sign in** button no longer wraps to two lines on small screens.

## [0.7.2] — 2026-05-23 — Footer wording

### Changed
- Footer link now reads **Powered By SimpleBlog** (SimpleBlog in bold), still
  linking to the project repository.

## [0.7.1] — 2026-05-22 — Year/Month/Post archive

### Changed
- **Archive** is now a **Year → Month → Post** tree. Expanding a year reveals its
  months (with counts), and expanding a month lists that month's posts linking to
  their permalinks. Built from nested native `<details>` (no JS), still a floating,
  scrollable overlay.

## [0.7.0] — 2026-05-22 — App-shell layout

### Changed
- **App-shell layout, site-wide.** The nav and footer are now fixed frames and
  the content scrolls between them, so the footer is always visible — it was
  previously unreachable on the infinite-scroll homepage. Content stays centered
  with the scrollbar at the viewport edge.

### Added
- Shared footer partial (`_footer.php`) used by every page (`© year · site ·
  version · Source`), replacing the per-page footers.

## [0.6.2] — 2026-05-22 — Amber CRT polish

### Fixed
- **Amber CRT** — hovering a post no longer re-covers its images with the
  scanline overlay. The post card's hover-lift `transform` (which created a
  stacking context that re-trapped raised images under the overlay) is dropped
  in this theme; the border/shadow hover feedback is kept.

## [0.6.1] — 2026-05-22 — Amber CRT polish

### Fixed
- **Amber CRT** — the scanline/vignette overlay no longer covers images or
  editable fields. The overlay is lowered beneath the UI layer (so the nav,
  modals, and the post editor stay clean), and images / form inputs are lifted
  above it; body text still sits under the overlay and keeps the CRT effect.

## [0.6.0] — 2026-05-22 — Amber CRT theme + homepage tweaks

### Added
- **Amber CRT theme** — a 6th selectable theme: a retro amber-phosphor terminal
  look (amber-on-near-black, IBM Plex Mono throughout, subtle text glow, faint
  scanlines + edge vignette, a terminal-style header with a `>` prompt and a
  brighter glowing site title). Always dark, regardless of the light/dark toggle.

### Changed
- **Homepage archive** moved from the footer to the top of the page, and now
  opens as a floating, scrollable dropdown that overlays the content instead of
  pushing it down.
- **Footer** gained a **Source** link to the GitHub repository.

## [0.5.1] — 2026-05-22 — Fixes

### Fixed
- **Dark-mode post editor** — in dark mode the page's light text color leaked
  into the Jodit editor's content area, rendering typed text near-invisible.
  The editor now keeps dark text on a white "paper" surface in dark mode. [low]
- **Upgrade docs** — the README upgrade guide now distinguishes code-only `www/`
  swaps from releases that change root files (`Dockerfile`/`nginx.conf`/
  `docker-compose.yml`), which must be deployed **and** rebuilt (`--build`).

## [0.5.0] — 2026-05-22 — Post permalinks + header refresh

### Added
- **Post permalinks.** Every post now has a stable slug-based URL
  (`/post/<slug>`) and a dedicated single-post page (`www/post.php`). The post
  title links to it and a 🔗 icon sits in the post meta. Slugs are generated
  from the title (`slugify()` / `unique_post_slug()` in `db.php`), backfilled
  for existing posts, and kept stable across title edits so links don't break.
  Pretty URLs use an `.htaccess` rewrite + `mod_rewrite`, now enabled in the
  Dockerfile — **deploying this version requires an image rebuild**
  (`docker compose up -d --build`), not just a code swap.

### Changed
- **Header refresh.** The nav bar is tinted with the active theme's accent
  color (brand/toggle/avatar in contrasting text), so the selected theme is
  obvious. The top bar was slimmed to brand · theme-toggle · avatar: the Home
  link was removed (the logo links home) and the admin Posts / Site Settings
  links moved into the avatar dropdown.
- **Shared rendering.** The post-article markup and comment JavaScript were
  extracted into `_post_article.php` and `_comments_script.php`, now shared by
  the homepage, the infinite-scroll chunks, and the single-post page.

## [0.4.0] — 2026-05-22 — Selectable themes

### Added
- **Five selectable themes.** Admins can choose a site-wide theme in
  Settings → Appearance (dropdown): **Editorial** (default), **Evergreen**,
  **Ember**, **Ink**, **Plum**. Each is a full color palette with light *and*
  dark variants plus its own font pairing, applied via a `data-palette`
  attribute on `<html>` (stamped in `_head.php`). Themes compose with the
  per-visitor light/dark toggle and the existing nav/accent color pickers,
  which still override a theme's accent/nav when set.
- **Self-hosted theme fonts** — Lora, Fraunces, Space Grotesk, Sora (variable
  woff2 in `www/vendor/fonts/`); only the active theme's fonts download.

### Fixed
- **Dark mode across the admin UI.** The Settings, Manage Posts, and Edit User
  pages had hard-coded light colors that were unreadable in dark mode (notably
  white-on-white `<select>`s). All admin chrome now uses the theme tokens.
- **Stylesheet caching** — the `<link>` to `style.css` is now cache-busted with
  the file mtime (`?v=…`) on every page, so CSS changes take effect without a
  manual hard refresh.

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

[0.7.3]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.7.3
[0.7.2]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.7.2
[0.7.1]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.7.1
[0.7.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.7.0
[0.6.2]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.6.2
[0.6.1]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.6.1
[0.6.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.6.0
[0.5.1]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.5.1
[0.5.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.5.0
[0.4.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.4.0
[0.3.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.3.0
[0.2.2]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.2.2
[0.2.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.2.0
[0.1.0]: https://github.com/Isorgcom/SimpleBlog/releases/tag/v0.1.0
