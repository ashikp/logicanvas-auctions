# Logicanvas Auctions — Future development plan

**Plugin:** `logicanvas-auctions` (public WordPress.org edition)  
**Current version baseline:** 1.2.1  
**Location:** `docs/future.md` (local / repo only — excluded from production ZIP)  
**Last updated:** 2026-09-29  

This file is the roadmap for installs, activation, and retention. Implement in order unless a blocker forces a change. Every feature must pass the [Security checklist](#security-checklist-non-negotiable) before release.

---

## Goals

1. **More installs** — clear first-run, complete competitor feature list, trustworthy WordPress.org listing.
2. **More active use** — empty site → first auction → first bid → first win → return visits.
3. **No new vulnerabilities** — WordPress/plugin security standards on every surface (capabilities, nonces, sanitize/escape, prepared SQL, no IDOR, rate limits).

---

## Recommended ship order (do this first)

| Priority | Slice | Outcome |
| --- | --- | --- |
| **1 (now)** | Phase A + **Buy Now** + **Relist/duplicate** | Fast “aha”, stronger feature list, sellers stay productive |
| **2 (next)** | Phase B — outbid / ending-soon alerts (+ proxy UI polish) | Daily return bids |
| **3** | Runner-up offer, deposits, seller profile | Trust + marketplace depth |
| **4** | Gutenberg blocks, live multi-lot, push transport | Platform depth |

**Public phrase for this direction:**

> Activate, create a demo lot in one click, pick a design, and take your first bid the same day — with server-side bidding, WooCommerce checkout, and WordPress-standard security on every action.

---

## Phase A — First-run conversion (install → “this works”)

**Why:** People abandon plugins that feel empty or scary after activate.

| Feature | Detail | Security notes |
| --- | --- | --- |
| **Sample auction on Setup** | One button: “Create demo auction” with images, start/end, starting price. Clearly marked as demo; one-click delete. | Admin-only (`manage_auction_settings`). Nonce on create/delete. No unsafe remote image fetch — use WP sideload/media APIs only. Demo holder = current admin. |
| **Post-setup success path** | After pages exist: “View catalog → Add auction → Invite a bidder” with real links. | No open redirects; only known admin/frontend URLs. |
| **Empty states that teach** | Catalog / dashboards show short next steps, not blank panels. | Escape all strings; no unsanitized HTML in notices. |
| **Safe-by-default messaging** | Keep soft login/shop defaults; Appearance themes stay opt-in customization. | Settings: enum/hex/CSS sanitization; strip dangerous custom CSS (`@import`, `expression`, etc.). |

**Success metric:** % of new installs that create ≥1 auction within 24 hours.

---

## Phase B — Bid engagement (active daily use)

**Why:** Bidding must feel alive or people never come back.

| Feature | Detail | Security notes |
| --- | --- | --- |
| **Outbid + ending-soon alerts** | Email + optional browser Notification API for watched / leading lots. Throttle during live sessions. | Authenticated users preferred. Never expose emails/IPs in public REST. Rate-limit notification jobs. |
| **Watchlist UX polish** | Clear Watch/Watching, ending-soon filter, badge counts on bidder dashboard. | Capability + ownership checks. Watch CRUD via nonce or REST `permission_callback`; user ID from session, not client-supplied alone. |
| **Proxy / max-bid real UI** | When setting enabled: set max, “You’re leading (max private)”, server-side auto-bids. | Max amount **never** in public bid history or Elementor preview. Atomic bid transaction + row lock. Idempotency keys. Money as sanitized decimals. |
| **Quick-bid + confirm** | Next-increment button + confirm; disable double-submit. | Client confirm is UX only — server remains authoritative. Rate-limit per user/auction/IP hash. |

**Success metric:** Bids per auction; 7-day return visits.

---

## Phase C — Commerce checklist (installs vs competitors)

**Why:** Shoppers compare feature lists; missing Buy Now and relist hurts installs and seller retention.

| Feature | Detail | Security notes |
| --- | --- | --- |
| **Buy Now** | Optional price on timed auctions; ends auction; award at Buy Now amount; winner checkout. | Only in accepting states. Atomic lock. Revalidate price server-side. Same award guards (winner-only, qty 1, locked price). Capability + nonce/REST permission. |
| **Relist / duplicate** | Copy title, gallery, pricing rules; new dates; fresh state row. | `edit_own_auctions` + ownership. Never copy bids, awards, or private maxima. All fields through existing input sanitizer. |
| **Runner-up offer (opt-in)** | After payment default: offer to #2 with new deadline. | Setting default **off**. Confirm + reason + audit event. No auto-charge. Idempotent award creation. |
| **Payment reminder polish** | Clear Pay now in email + dashboard countdown. | Award ownership on every pay path. Expiry enforced server-side. No guessable-only access to awards. |

**Success metric:** Checkout starts / paid awards; fewer “buy now / relist” support questions.

---

## Phase D — Trust & seller activity

**Why:** Third-party sellers create inventory; inventory attracts bidders.

| Feature | Detail | Security notes |
| --- | --- | --- |
| **Seller public profile** | `/seller/{slug}`: bio, lots, sold count; no private emails. | Public data only. Escape output. Slug via `sanitize_title`; block reserved paths. |
| **Bidder deposit (optional)** | WooCommerce product/fee before bidding on selected lots/categories. | Never store cards. WC CRUD only. Deposit status checked server-side before bid accept. Ignore client “I paid” claims. |
| **CSV lot import (admin)** | Column map → draft auctions; dry-run preview. | Admin capability only. MIME/size validation. Strict column allowlist. Cap rows per run. No `eval` / unsafe unserialize. |
| **Moderation queue UX** | Pending holders + pending auctions in one “Needs review” screen. | Per-action capabilities; nonce; audit reason on reject/suspend. |

**Success metric:** Approved holders posting lots; time to first third-party auction.

---

## Phase E — Discoverability (WordPress.org + SEO)

| Feature | Detail | Security notes |
| --- | --- | --- |
| **Gutenberg blocks** | Grid, single, bid panel, countdown for block themes. | Editor preview must not leak proxy max / hidden reserve. Same REST permissions as shortcodes. Escape attributes. |
| **Screenshots + readme “5-minute demo”** | Setup → sample lot → bid → Appearance. | No review-star filter gaming. Honest docs. |
| **Optional onboarding emails** | Admin drip: create lot, share link, check settlements. | Opt-in; respect prefs; no PII in logs. |

---

## Phase F — Live auction depth (later, high effort)

| Feature | Detail | Security notes |
| --- | --- | --- |
| **Multi-lot live sale** | One room, lot queue; host advances lots. | Server state machine only; clients never declare winners. Sequence/events as today. |
| **Push transport** | WebSocket/push = “new sequence”; client still loads state from WP. | Payload: auction id + sequence only. No bid accept over push. Writes still cookie + REST nonce. |
| **Host console polish** | Larger type, shortcuts, sold confirm. | Host capabilities; confirm dangerous actions; audit every transition. |

---

## Security checklist (non-negotiable)

Before merging any roadmap item:

1. **Authorize** with capabilities + resource ownership (a nonce is CSRF protection, not authorization).
2. **Every REST route** has a real `permission_callback` and request/response schema.
3. **Sanitize on input**; **escape on output** (`esc_html`, `esc_attr`, `esc_url`, `wp_kses_post` only where HTML is allowed).
4. **SQL** via `$wpdb->prepare` / WP APIs only — no user-controlled SQL fragments.
5. **No IDOR** — auctions, bids, watches, invitations, awards, settlements scoped correctly.
6. **Financial / state changes** — transactions, idempotency keys, immutable audit/events.
7. **Uploads** — WordPress media APIs; validate type and size.
8. **Secrets** — never in HTML, localized JS, logs, or public JSON (proxy maxima, raw invite tokens, emails, IPs).
9. **Rate limits** on bid, join, invite, and notification bursts.
10. **Custom CSS / rich text** — strip dangerous constructs; unfiltered HTML only for trusted roles if ever needed.
11. **Tests** — capability denial, IDOR, XSS escaping, CSRF/nonce failure, concurrent bids, Buy Now double-purchase, award pay ownership.

---

## Already shipped (do not re-build)

Track so future work stays additive:

- Timed + live auctions, soft close, server-authoritative bidding
- WooCommerce winner checkout, awards, settlements (manual payouts)
- Setup wizard, soft defaults (login/shop opt-in), activation → Setup
- Appearance: six themes + accent / radius / density / cards / custom CSS
- Shortcodes, optional Elementor, REST polling, watch table foundation, proxy setting (UI still thin)
- Categories, seller edit listing, privacy tools, WP-CLI

---

## Implementation notes for this repo

- Work only in `logicanvas-auctions/` unless explicitly told otherwise (do not mix with `auction-platform/`).
- Prefer extending existing domain services (`Bidding`, `Award`, `Auction` state machines) over new one-off controllers.
- Keep money as normalized decimal strings; never float math for decisions.
- Update `readme.txt` / `changelog.txt` when a slice ships; bump version only when releasing.
- Rebuild dist with: `bash bin/build-zip.sh`

---

## Progress log

| Date | Item | Status |
| --- | --- | --- |
| 2026-09-29 | Roadmap written; recommended next = Phase A + Buy Now + Relist | Planned |
| 2026-09-29 | Sample auction on Setup + next-steps / empty catalog copy | **Done** (in 1.2.1) |
| 2026-09-29 | Buy Now (timed) | **Done** (in 1.2.1) |
| 2026-09-29 | Relist / duplicate | **Done** (in 1.2.1) |
| | Outbid / ending-soon alerts | Not started |

When a slice ships, move its row to **Done** and note the version.

---

## Next recommended slice

**Phase B:** outbid + ending-soon alerts, watchlist polish, proxy max-bid UI.
