# Logicanvas Auctions for WooCommerce

**Timed and live auctions for WooCommerce** — server-authoritative bidding, winner checkout through your store’s payment gateways, optional Elementor widgets, and a commission / settlement ledger for third-party sellers.

[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/plugins/logicanvas-auctions/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2%20or%20later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![WordPress.org](https://img.shields.io/badge/WordPress.org-Approved-46b450.svg)](https://wordpress.org/plugins/logicanvas-auctions/)

| | |
| --- | --- |
| **Plugin slug** | `logicanvas-auctions` |
| **Version** | 1.1.0 |
| **Author** | [Logicanvas.io](https://logicanvas.io) |
| **Plugin URI** | https://wordpress.org/plugins/logicanvas-auctions/ |
| **Documentation** | https://docs.logicanvas.io/logicanvas-auctions |
| **WordPress.org** | https://wordpress.org/plugins/logicanvas-auctions/ |
| **Text domain** | `logicanvas-auctions` |
| **REST namespace** | `logicanvas-auctions/v1` |
| **Internal prefix** | `wcap_` |
| **License** | GPLv2 or later |

WordPress.org also requires [`readme.txt`](readme.txt) in this folder. Keep both files in sync for releases.

---

## Table of contents

1. [Overview](#overview)
2. [Features](#features)
3. [Requirements](#requirements)
4. [Installation](#installation)
5. [Quick start](#quick-start)
6. [Frontend pages & shortcodes](#frontend-pages--shortcodes)
7. [Roles & capabilities](#roles--capabilities)
8. [Auction types & states](#auction-types--states)
9. [Bidding](#bidding)
10. [Live auctions](#live-auctions)
11. [WooCommerce checkout & settlements](#woocommerce-checkout--settlements)
12. [Elementor](#elementor)
13. [REST API](#rest-api)
14. [Hooks & filters](#hooks--filters)
15. [Database](#database)
16. [Settings](#settings)
17. [WP-CLI](#wp-cli)
18. [Scheduler](#scheduler)
19. [Privacy & security](#privacy--security)
20. [Template overrides](#template-overrides)
21. [Development](#development)
22. [Frequently asked questions](#frequently-asked-questions)
23. [Support](#support)
24. [Changelog](#changelog)

---

## Overview

Logicanvas Auctions for WooCommerce lets the **site owner** and **approved third-party auction holders** list and run auctions. **Registered bidders** place bids that are validated and accepted only on the server. When an auction closes with a winner, checkout, tax, shipping, payment, and invoices run through **WooCommerce** — the store remains the merchant of record.

This is the **public WordPress.org edition**. It uses a generic frontend and admin design (not a site-branded custom build).

The plugin never stores card numbers and never bypasses your configured WooCommerce payment gateways.

### Important warning after install

After activation and **Auctions → Setup** (frontend pages):

- WordPress **login** and **register** (`wp-login.php`) are redirected to the plugin login / register page.
- The WooCommerce **shop** page is **hidden** by default.
- WooCommerce **single product** pages are **hidden** by default.

Cart, checkout, and My Account remain available for winner payment. Turn off **Disable WooCommerce shop and single product pages** under **Auctions → Settings** if you want the normal WooCommerce catalog.

---

## Features

### Auctions

- **Timed auctions** with start/end times and optional anti-snipe **soft close**
- **Live auction rooms** with lobby, start, pause/resume, going once / twice, extend, sell, unsold, and cancel
- **Visibility:** public, unlisted (share link), invite-only (hashed tokens)
- Linked **WooCommerce products**, gallery, condition, reserve, increment, and fulfilment metadata
- Explicit **state machines** with audited transitions

### Bidding

- Server-authoritative acceptance with row locks and **idempotency keys**
- Minimum increment rules, rate limits, and **no self-bidding**
- Soft-close extensions with configurable window, length, and max extensions
- Optional **proxy / maximum bidding** for timed auctions (off by default)
- Bid history, leading / outbid UI, and masked public bidder identity

### Commerce

- Immutable **award** records and payment deadlines
- Winner-only checkout at the **awarded final price** (qty locked to 1)
- **HPOS** and block checkout compatibility declarations
- Commission ledger with **manual payout** states (pending → approved → paid / reversed / disputed)
- Refunds and cancellations update settlement records

### Frontend & integrations

- Setup wizard creates ordinary editable WordPress pages
- Shortcodes for catalog, single lot, live room, dashboards, and bidding UI
- Optional **Elementor** widgets and dynamic tags (core works without Elementor)
- Dedicated login / register / password flows
- Holder and bidder dashboards
- REST polling for live updates (push-ready; polling is the default)
- Email notifications with preference hooks
- Privacy exporter / eraser integration
- WP-CLI for ops and recovery

---

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.4+ |
| PHP | 8.1+ |
| WooCommerce | 8.0+ (required) |
| Elementor | Optional |
| Action Scheduler | Bundled with WooCommerce |

**Do not** activate this plugin on a site that already runs the branded `auction-platform` client plugin. Both use the `wcap_` table prefix and must not be installed together.

---

## Installation

### From WordPress.org

1. In wp-admin go to **Plugins → Add New**.
2. Search for **Logicanvas Auctions for WooCommerce**.
3. Install and activate.
4. Ensure **WooCommerce** is installed and active.

### From a ZIP

1. Install and activate WooCommerce.
2. Upload `logicanvas-auctions-x.y.z.zip` via **Plugins → Add New → Upload Plugin**,  
   or copy the `logicanvas-auctions` folder to `wp-content/plugins/`.
3. Activate **Logicanvas Auctions for WooCommerce**.

### After activation

1. Open **Auctions → Setup** and create the frontend pages.
2. Configure **Auctions → Settings** (increments, soft close, commission, payment deadline).
3. Confirm Action Scheduler / WP-Cron is healthy (**WooCommerce → Status → Scheduled Actions**).

---

## Quick start

1. **Create a timed auction** — **Auctions → Add Auction** (or use the holder submission form after approval).
2. Set starting price, optional reserve, increment, start/end times, and visibility.
3. Publish / approve so the auction becomes **scheduled** then **active**.
4. As a bidder account, open the auction page and place a bid.
5. When the auction closes, the winner uses **Pay for won auction** / award checkout.
6. Complete payment through WooCommerce; settlement rows update from order events.

For live lots: open the lobby → start → accept bids → going once / twice → sell (or unsold / cancel).

---

## Frontend pages & shortcodes

The setup wizard creates ordinary WordPress pages. Edit them in the block editor or Elementor. Deleted pages are **not** recreated unless you run setup again for missing keys.

| Page key | Default title | Shortcode |
| --- | --- | --- |
| `archive` | Auctions | `[wcap_auction_grid]` |
| `single` | Auction | `[wcap_single_auction]` |
| `live` | Live auction | `[wcap_live_room]` |
| `submit` | Submit auction | `[wcap_submit_auction]` |
| `holder` | Auction holder dashboard | `[wcap_holder_dashboard]` |
| `bidder` | Bidder dashboard | `[wcap_bidder_dashboard]` |
| `my_bids` | My bids | `[wcap_my_bids]` |
| `my_wins` | My wins | `[wcap_my_wins]` |
| `pay` | Pay for won auction | `[wcap_pay_award]` |
| `terms` | Auction terms | page content |
| `apply` | Become an auction holder | `[wcap_holder_apply]` |
| `login` | Log in | `[wcap_login]` |

### Shortcode reference

| Shortcode | Purpose | Common attributes |
| --- | --- | --- |
| `[wcap_auction_grid]` | Catalog / grid | `type=""`, `state="active,scheduled,live,lobby"`, `per_page="12"` |
| `[wcap_single_auction]` | Single lot | `id=""` (current auction if omitted) |
| `[wcap_live_room]` | Live room | `id=""` |
| `[wcap_live_host]` | Host console | `id=""` |
| `[wcap_submit_auction]` | Create / submit listing | — |
| `[wcap_holder_dashboard]` | Seller dashboard | — |
| `[wcap_bidder_dashboard]` | Buyer dashboard | — |
| `[wcap_my_bids]` | Current user bids | — |
| `[wcap_my_wins]` | Current user wins | — |
| `[wcap_pay_award]` | Winner payment | — |
| `[wcap_holder_apply]` | Holder application | — |
| `[wcap_login]` | Login / register / lost password | — |
| `[wcap_register]` | Registration view | — |
| `[wcap_countdown]` | Countdown | `id=""` |
| `[wcap_bid_panel]` | Bid form | `id=""` |
| `[wcap_bid_history]` | Bid history | `id=""` |

Dashboard views use the `wcap_view` query var, for example: `overview`, `bids`, `watchlist`, `purchases`, `pickup`, `listings`, `orders`, `payouts`, `create`, `address`.

---

## Roles & capabilities

Two domain roles are added:

- `wcap_auction_holder` — approved sellers / hosts  
- `wcap_auction_bidder` — registered bidders  

Administrators receive every plugin capability. Authorization uses **capabilities**, not hard-coded role names.

| Capability | Purpose |
| --- | --- |
| `read_auctions` | View allowed auctions |
| `bid_on_auctions` | Place bids |
| `create_auctions` | Create listings |
| `edit_own_auctions` | Edit own drafts |
| `submit_auctions` | Submit for review |
| `host_live_auctions` | Host live rooms |
| `view_own_auction_bids` | Own bid history |
| `view_own_settlements` | Own settlements |
| `moderate_auctions` | Approve, pause, close, cancel |
| `manage_auction_holders` | Approve holder accounts |
| `manage_auction_bids` | Audit and void bids |
| `manage_auction_settings` | Settings and diagnostics |
| `manage_auction_settlements` | Payout tracking |

Holders also receive bidder capabilities, but the bid service still **rejects bids on their own lots**.

### Holder account states

`pending` → `approved` | `rejected` | `suspended`

Suspended holders cannot publish or host.

---

## Auction types & states

### Timed

Typical path:  
`draft` → `pending review` → `scheduled` → `active` → `closing` → `ended` / `reserve not met` / `payment pending` → `paid` → `completed`  
Also: `unsold`, `cancelled`, `payment defaulted`. Administrators can pause an active timed auction.

### Live

Typical path:  
`draft` → `pending review` → `scheduled` → `lobby` → `live` → `paused` / `going once` / `going twice` → `closing` → `sold/payment pending` → `paid` → `completed`  
Also: `unsold`, `cancelled`, `payment defaulted`.

Invalid transitions are rejected in domain services. Each transition writes an audit event (actor, timestamps, optional reason, correlation key).

---

## Bidding

Bids are accepted **only on the server**:

1. Begin transaction and lock the canonical auction state row  
2. Reload state and re-validate window, eligibility, increment, currency, rate limits, and idempotency  
3. Insert an immutable bid **or** return the original result for a repeated key  
4. Update price, leader, bid count, sequence, and optional soft-close extension  
5. Insert an event / outbox row  
6. Commit, then broadcast  

**Rules of note**

- Repeating the same client idempotency key returns the first result  
- The first valid committed bid wins a tie  
- Administrators may void a bid only with a reason (rows are not silently edited)  
- Soft close (defaults): last **120s** → extend **+120s**, up to **20** extensions  
- Proxy / maximum bidding for timed auctions is **off by default**

---

## Live auctions

Holders can:

- Open the lobby and start the session  
- Pause / resume bidding  
- Adjust increment when permitted  
- Mark going once / going twice  
- Extend bidding time  
- Sell to the current highest bidder  
- Mark unsold or cancel with a recorded reason  

**Realtime (default):** authenticated REST polling by event sequence (~1s while live; slower in lobby / paused). Adaptive backoff on errors; immediate refresh after a bid; visibility-aware pause when the tab is inactive.

A configured push transport may wake clients, but clients must still load authoritative state from WordPress. **A push message cannot accept a bid or declare a winner.**

---

## WooCommerce checkout & settlements

When an auction closes successfully:

1. An immutable **award** is stored (auction, winner, amount, currency, deadline — default **48 hours**)  
2. Only that winner can start checkout  
3. Quantity is locked to **1**; line price is the awarded amount  
4. Taxes and shipping use WooCommerce  
5. Order and line-item meta store auction / award IDs  
6. Payment, refund, failure, and cancellation update auction and settlement rows  

If the winner does not pay before the deadline, the award can be marked **defaulted**. The plugin does **not** auto-charge bidders and does **not** auto-offer to a runner-up unless that policy is explicitly enabled.

### Commission & payouts

- The website is the checkout merchant  
- Settlements track gross, commission, fees, refunds, net, and payout status  
- Manual payout tracking: pending, approved, paid, reversed, disputed  
- Commission: global fixed fee, percentage, minimum; filters for future strategies  
- A payout provider interface exists for later marketplace payouts (e.g. Stripe Connect); this edition does **not** pretend to perform automatic connected-account payouts  

Each auction is locked to the **store currency at creation**. If the store currency changes later, mixed-currency settlement should be blocked until handled explicitly.

---

## Elementor

If Elementor is inactive, integration classes are **not** loaded. Core shortcodes, templates, REST, and dashboards continue to work.

**Widgets include:** auction grid, search/filters, single auction, gallery, details, current price, countdown, bid panel, bid history, live room, host console, holder/bidder dashboards, submission form, my auctions / bids / wins, status badge, share, and login.

**Dynamic tags** (when available): auction ID, current price, starting price, end time, countdown, state, type, bid count, reserve-met, holder name, condition.

Editor previews must not expose private bidder data.

---

## REST API

**Namespace:** `logicanvas-auctions/v1`

Cookie authentication plus a REST nonce is required for write routes. Public GET responses hide private fields (emails, IPs, proxy maxima, etc.).

| Method | Route |
| --- | --- |
| GET, POST | `/auctions` |
| GET | `/auctions/{id}` |
| GET | `/auctions/{id}/state` |
| GET, POST | `/auctions/{id}/bids` |
| GET | `/auctions/{id}/events` |
| POST | `/auctions/{id}/watch` |
| POST | `/auctions/{id}/submit` |
| POST | `/auctions/{id}/approve` |
| POST | `/media` |
| POST | `/live/{id}/host` |
| POST | `/live/{id}/join` |
| POST | `/awards/{id}/checkout` |
| GET | `/products` |
| GET | `/products/{id}` |
| POST | `/holder/apply` |
| GET | `/me/dashboard` |

Live clients poll `/auctions/{id}/events` by sequence for incremental recovery.

---

## Hooks & filters

### Actions (selected)

`wcap_auction_created`, `wcap_auction_approved`, `wcap_auction_started`, `wcap_before_bid_validation`, `wcap_bid_accepted`, `wcap_bidder_outbid`, `wcap_auction_extended`, `wcap_auction_closed`, `wcap_award_created`, `wcap_winner_checkout_initialized`, `wcap_order_linked`, `wcap_payment_completed`, `wcap_payment_failed`, `wcap_payment_deadline_expired`, `wcap_settlement_calculated`, `wcap_payout_status_changed`, `wcap_notification_dispatch`, `wcap_realtime_notify`, `wcap_holder_approved`, `wcap_holder_rejected`, `wcap_logged`

### Filters (selected)

`wcap_bid_increment`, `wcap_commission_amount`, `wcap_public_bidder_display_name`, `wcap_role_restricted_access`, `wcap_enqueue_frontend_assets`, `wcap_user_can_access_wp_admin`, `wcap_dashboard_url_for_user`, `wcap_login_redirect`, `wcap_allow_frontend_registration`, `wcap_max_image_bytes`, `wcap_image_uploads_per_hour`

Public APIs keep stable parameter contracts and PHPDoc. See the online docs for full signatures.

---

## Database

Custom tables use `$wpdb->prefix` + the names below (InnoDB on MySQL hosts):

| Table | Role |
| --- | --- |
| `wcap_auction_state` | Canonical price, leader, sequence, timestamps |
| `wcap_bids` | Immutable bids; unique idempotency |
| `wcap_events` | Event / outbox; unique auction + sequence |
| `wcap_invitations` | Hashed invite tokens |
| `wcap_participants` | Live joins |
| `wcap_awards` | Winner awards |
| `wcap_settlements` | Commission and payout ledger |
| `wcap_payout_requests` | Holder payout requests |
| `wcap_audit_log` | Admin / system audit |
| `wcap_watches` | Watchlists |
| `wcap_holder_accounts` | Holder status |
| `wcap_notification_prefs` | Email preferences |
| `wcap_idempotency` | Extra idempotency keys |

Auction copy (title, description, gallery) lives on the `wcap_auction` custom post type so content stays editable. **Bids are never stored as comments or ordinary post meta.**

- **Deactivation** does not delete business data  
- **Uninstall** deletes tables and auction posts only if **Remove all plugin data on uninstall** is enabled (default: off)  

Schema version option: `wcap_db_version` (current: `1.0.2`).

---

## Settings

**Auctions → Settings** (capability: `manage_auction_settings`)

- Default increment; soft-close window / extension / max extensions  
- Payment deadline (hours)  
- Commission fixed fee, percent, and minimum  
- Proxy bidding (timed only)  
- Coupons and mixed-cart rules on auction checkout  
- Hide WooCommerce shop / single product pages by default (cart, checkout, and account stay; can be turned off in settings)  
- Login redirect to dashboards  
- Block wp-admin for holders and bidders (administrators and shop managers keep access)  
- Hash stored IP addresses  
- Remove data on uninstall  

**Auctions → Diagnostics** reports scheduler health, schema, and realtime mode.

---

## WP-CLI

```bash
wp logicanvas-auctions list [--state=<state>]
wp logicanvas-auctions close <id>
wp logicanvas-auctions overdue
wp logicanvas-auctions rebuild <id>
wp logicanvas-auctions schema
wp logicanvas-auctions diagnostics
wp logicanvas-auctions reschedule <id>
wp logicanvas-auctions migrate
```

---

## Scheduler

Closing, starts, payment reminders, expirations, and notifications use **WooCommerce Action Scheduler** (WP-Cron fallback).

If a job is late, opening an auction page or state endpoint **after** end time still runs the **idempotent** close. Check **WooCommerce → Status → Scheduled Actions** and hosting cron reliability if auctions appear stuck.

---

## Privacy & security

### Privacy

- WordPress personal-data exporter and eraser (transaction rows may be **anonymized**, not deleted, when retention requires them)  
- Optional IP hashing  
- Public bidder display names filtered via `wcap_public_bidder_display_name`  
- Terms acceptance can record timestamp and terms version  

### Security

Follows WordPress security APIs: sanitize on input, escape on output, authorize with capabilities (a nonce is CSRF protection, **not** authorization).

- REST `permission_callback` on every route; ownership checks for submit, host, checkout, and private auctions  
- Invite tokens stored as hashes; public APIs omit emails, IPs, proxy maxima, and sensitive actor data  
- Prepared SQL; table names from a fixed prefix list  
- Atomic option locks and rate-limit increments for concurrent requests  
- Rate limits on bidding, auth, holder applications, and image uploads  
- No `eval`, unsafe unserialize, or raw card storage  

---

## Template overrides

Copy templates into your theme:

```text
your-theme/logicanvas-auctions/
```

Use the same relative paths as the plugin `templates/` directory (for example `frontend/single.php`, `live/room.php`).

Frontend CSS is scoped under `.wcap-root`. Admin screens under **Auctions** share a card layout in `assets/dist/css/admin.css`.

---

## Development

```bash
composer install
composer test      # PHPUnit unit suite
composer phpstan
composer phpcs
```

PHPUnit unit tests do not boot WordPress. Integration tests need a WordPress test install.

Build a distribution ZIP (excludes tests, Composer dev packages, and source SCSS):

```bash
bash bin/build-zip.sh
```

Output: `dist/logicanvas-auctions-1.0.0.zip`

Structured FAQ / guide data used by documentation tooling lives in [`docs/faq-and-guides.json`](docs/faq-and-guides.json).

---

## Frequently asked questions

### General & requirements

**What is Logicanvas Auctions for WooCommerce?**  
A WooCommerce plugin for timed and live auctions. Store owners and approved third-party holders list lots; registered bidders place server-side bids; winners pay through normal WooCommerce checkout, tax, shipping, and gateways.

**Is WooCommerce required?**  
Yes. The plugin shows an admin notice and fails gracefully if WooCommerce is missing.

**Is Elementor required?**  
No. Shortcodes, templates, REST, and dashboards work without Elementor. Widgets and dynamic tags load only when Elementor is active.

**What versions are supported?**  
WordPress 6.4+, PHP 8.1+, WooCommerce 8.0+. HPOS and block checkout compatibility are declared.

**Does the plugin store credit card details?**  
No. Payments go only through configured WooCommerce gateways.

**Can I run this with another auction-platform plugin?**  
Do not activate alongside the branded `auction-platform` plugin. They share the `wcap_` table prefix.

### Auctions & bidding

**What is the difference between timed and live auctions?**  
Timed auctions run for a fixed window and close automatically (with optional soft close). Live auctions use a lobby and host-controlled room. In both modes the **server** is authoritative for price, winner, and state.

**When does an auction accept bids?**  
Timed: while **active**. Live: while **live**, **going once**, or **going twice**. Lobby and paused states do not accept bids.

**Who can create auctions?**  
Administrators, and approved holders from the holder dashboard. Suspended holders cannot publish or host.

**How do I become an auction holder?**  
Use the Become an auction holder page (`[wcap_holder_apply]`), submit the form, and wait for admin approval (`pending` / `approved` / `rejected` / `suspended`).

**Who can place a bid?**  
Authenticated users with bidding capability on auctions they may access. Guests can usually view public lots but must log in before bidding. Nobody may bid on their own auction.

**Can I bid on my own auction?**  
No. Self-bidding is always rejected by the server.

**How are bids accepted?**  
Server validation with a locked auction row, increment / eligibility / rate-limit checks, and idempotency. Duplicate clicks with the same key do not create a second bid. First valid committed bid wins a tie.

**What is soft close (anti-sniping)?**  
If a valid bid arrives near the end, the end time extends (defaults: last 120s → +120s, max 20 extensions). New end times come from the server.

**What happens if the reserve is not met?**  
The auction can close as reserve not met / unsold. Public UI typically shows only whether reserve is met, not a hidden reserve amount.

**Is proxy (maximum) bidding available?**  
Yes for timed auctions as an optional setting (off by default). Not enabled for live auctions by default. Maxima stay private.

**What visibility options exist?**  
Public, unlisted (share link), and invite-only. Invite tokens are hashed, can expire or be revoked, and never replace authentication for bidding.

### Payments & settlements

**How does the winner pay?**  
An award is created with amount and deadline. Only the winner can check out at the awarded price (qty 1). Taxes and shipping use WooCommerce.

**What if the winner does not pay?**  
After the deadline the award can be marked defaulted. No automatic charge and no automatic runner-up offer unless that policy is enabled.

**How do commissions and seller payouts work?**  
The site is the merchant. Settlements track gross, commission, net, and manual payout status. Automatic connected-account payouts are not included in this edition.

**What currency is used?**  
Store currency locked at auction creation. Changing store currency later needs careful handling for open settlements.

### Setup, UI & ops

**How do I create the frontend pages?**  
**Auctions → Setup → Create auction pages.** Pages are normal WordPress pages with shortcodes.

**Where do I manage auctions in wp-admin?**  
Under **Auctions**: Dashboard, Add Auction, All Auctions, Holders, Bids, Awards, Settlements, Settings, Diagnostics, Setup.

**Is there a dedicated login page?**  
Yes — Setup creates a Log in page with `[wcap_login]`. Settings can redirect to dashboards and optionally block wp-admin for holder/bidder roles.

**Which emails are sent?**  
Approvals, scheduling, live start, bid accepted, outbid, reserve met (without revealing hidden reserve), extensions, winner / unsuccessful, payment reminders and expiry, payment received, holder sale, settlements, cancellations. Preferences and throttling apply where configured.

**How do live rooms stay in sync?**  
Default: REST event polling by sequence. Push is optional and never authoritative for bids or winners.

**Auctions did not close on time. Why?**  
Action Scheduler / cron may be delayed. Opening the auction after end time still runs an idempotent close. Check Scheduled Actions and hosting cron.

**Does uninstall delete my data?**  
Only if **Remove all plugin data on uninstall** is enabled. Deactivation never deletes business data.

**How is personal data handled?**  
WordPress export/erase tools; anonymize financial rows when required; optional IP hashing; masked public bidder names.

**Can I override templates?**  
Yes — `your-theme/logicanvas-auctions/` mirroring `templates/`.

**Is there a REST API?**  
Yes — `logicanvas-auctions/v1`.

**Are WP-CLI commands available?**  
Yes — `wp logicanvas-auctions …` (list, close, overdue, rebuild, schema, diagnostics, reschedule, migrate).

---

## Support

- **Documentation:** https://docs.logicanvas.io/logicanvas-auctions  
- **WordPress.org:** https://wordpress.org/plugins/logicanvas-auctions/  
- **Plugin page:** https://plugins.logicanvas.io/logicanvas-auctions  

- **Author:** [Logicanvas.io](https://logicanvas.io)  

When requesting help, include WordPress, WooCommerce, PHP versions, and relevant entries from **Auctions → Diagnostics**.

---

## Changelog

### 1.1.0

- Dedicated frontend **Edit listing** page (seller dashboard) with REST PUT/PATCH
- Clickable gallery with lightbox on single auctions
- Description HTML matches the WordPress editor (`the_content`)
- Share tab: copy link, social links, QR codes
- Yoast / SEO helpers for auction CPT singles; permalink slug `auctions/`
- Dashboard UI polish

### 1.0.0

- First public release on WordPress.org  
- Timed and live auctions, atomic bidding, WooCommerce winner checkout  
- Shortcodes, Elementor widgets, REST polling, settlements, privacy tools, WP-CLI  

---

## License

This plugin is free software; you can redistribute it and/or modify it under the terms of the **GNU General Public License** as published by the Free Software Foundation; either version **2** of the License, or (at your option) any later version.

See [LICENSE](LICENSE) and https://www.gnu.org/licenses/gpl-2.0.html.
