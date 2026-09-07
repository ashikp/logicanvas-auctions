=== Logicanvas Auctions for WooCommerce ===
Contributors: logicanvasio
Donate link: https://wordpress.org/plugins/logicanvas-auctions/
Tags: woocommerce, auction, bidding, live auction, marketplace
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 8.0
WC tested up to: 10.1

Timed and live auctions for WooCommerce with server-side bidding, winner checkout, commission settlements, and optional Elementor widgets.

== Description ==

Logicanvas Auctions for WooCommerce lets the store owner and approved third-party auction holders list and run timed and live auctions. Registered bidders place bids that are validated and accepted only on the server. When an auction closes with a winner, checkout, tax, shipping, payment, and invoices run through WooCommerce — your store remains the merchant of record.

WordPress.org: https://wordpress.org/plugins/logicanvas-auctions/
Documentation: https://docs.logicanvas.io/logicanvas-auctions
Plugin page: https://plugins.logicanvas.io/logicanvas-auctions

WooCommerce is required. Elementor is optional. The plugin never stores card numbers and never bypasses your configured WooCommerce payment gateways.

= Important warning after install =

After you install and activate this plugin (and create frontend pages via Auctions → Setup), please be aware of these default behaviors:

* The default WordPress login and register experience is replaced with the plugin login / register page. Visits to wp-login.php (login, register, and lost password) are redirected to the plugin’s dedicated Log in page.
* The WooCommerce shop page is hidden from visitors.
* WooCommerce single product pages are hidden from visitors.

Cart, checkout, and My Account remain available so auction winners can pay through WooCommerce.

To restore the normal WooCommerce shop and product pages, go to Auctions → Settings and turn off “Disable WooCommerce shop and single product pages”. Auction-linked products may still stay hidden from the catalog so they can only be purchased through winner checkout.

= Auction features =

* Timed auctions with start/end times and optional anti-snipe soft close
* Live auction rooms with lobby, start, pause/resume, going once / twice, extend, sell, unsold, and cancel
* Public, unlisted (share link), and invite-only visibility with hashed invitation tokens
* Linked WooCommerce products, gallery, condition, reserve, increment, and fulfilment metadata
* Explicit state machines with audited transitions

= Bidding features =

* Server-authoritative bid acceptance with row locks and idempotency keys
* Minimum increment rules, rate limits, and no self-bidding
* Soft-close extensions with configurable window, length, and maximum extensions
* Optional proxy / maximum bidding for timed auctions (off by default)
* Bid history, leading / outbid UI, and masked public bidder identity

= Commerce features =

* Immutable award records and configurable payment deadlines
* Winner-only checkout at the awarded final price (quantity locked to one)
* HPOS (High-Performance Order Storage) and block checkout compatibility
* Commission ledger with manual payout states: pending, approved, paid, reversed, disputed
* Refunds and cancellations update settlement records
* Currency locked to the store currency at auction creation

= Frontend and integrations =

* Setup wizard creates ordinary editable WordPress pages
* Shortcodes for catalog, single lot, live room, dashboards, and bidding UI
* Optional Elementor widgets and dynamic tags (core works without Elementor)
* Dedicated login, register, and password-reset flows
* Holder and bidder dashboards
* REST polling for live updates (push-ready; polling is the default)
* Email notifications with preference hooks
* Privacy exporter / eraser integration
* WP-CLI commands for listing, closing, rebuilding, and diagnostics

= How bidding works =

Every bid is validated on the server: auction state, time window, eligibility, currency, minimum increment, rate limits, and idempotency. The auction row is locked during acceptance. Duplicate clicks with the same idempotency key return the original result instead of creating another bid. The first valid committed bid wins a tie. Administrators may void a bid only with a recorded reason.

= How winner checkout works =

1. An award record is created with auction, winner, final amount, currency, and payment deadline.
2. Only that winner can start checkout.
3. Quantity is locked to one and the line price is the awarded amount.
4. Taxes and shipping use WooCommerce.
5. Order and line-item meta store auction and award IDs.
6. Payment, refund, failure, and cancellation update auction and settlement rows.

= Roles =

* Auction holders (approved sellers) can create and host auctions they own.
* Auction bidders can browse allowed auctions, bid, watch lots, and pay for wins.
* Administrators receive all plugin capabilities and can moderate holders, bids, awards, and settlements.
* Nobody may bid on their own auction, including administrators acting as holders.

= Shortcodes =

* [wcap_auction_grid] — auction catalog / grid
* [wcap_single_auction] — single auction page
* [wcap_live_room] — live auction room
* [wcap_live_host] — live host console
* [wcap_submit_auction] — create / submit a listing
* [wcap_holder_dashboard] — seller dashboard
* [wcap_bidder_dashboard] — buyer dashboard
* [wcap_my_bids] — current user bids
* [wcap_my_wins] — current user wins
* [wcap_pay_award] — winner payment page
* [wcap_holder_apply] — become an auction holder
* [wcap_login] — login / register / lost password
* [wcap_register] — registration view
* [wcap_countdown] — countdown
* [wcap_bid_panel] — bid form
* [wcap_bid_history] — bid history

Example: [wcap_auction_grid state="active,scheduled,live,lobby" per_page="12"]

= REST API =

Namespace: logicanvas-auctions/v1

Cookie authentication plus a REST nonce is required for write routes. Public GET responses hide private fields such as emails, IPs, and proxy maxima. Live clients poll auction events by sequence for incremental updates.

= Privacy and security =

* Integrates with WordPress personal data export and erase tools
* Financial rows may be anonymized rather than deleted when retention requires them
* Optional IP hashing and masked public bidder names
* Capability checks on every privileged action; REST permission callbacks on every route
* Prepared SQL; invite tokens stored as hashes
* Rate limits on bidding, authentication, holder applications, and image uploads

== Installation ==

1. Install and activate WooCommerce.
2. From WordPress.org: Plugins → Add New → search for “Logicanvas Auctions for WooCommerce”, then install and activate.
   Or upload the plugin zip / copy the `logicanvas-auctions` folder into `wp-content/plugins/` and activate it.
3. Open Auctions → Setup and create the frontend pages.
4. Configure Auctions → Settings (increments, soft close, commission, payment deadline, login redirects).
5. Confirm Action Scheduler / WP-Cron is running under WooCommerce → Status → Scheduled Actions.
6. Optional: approve holder applications under Auctions → Holders, then create your first timed or live auction.

Important: After setup, login/register use the plugin pages, and the WooCommerce shop and single product pages are hidden by default. See the “Important warning after install” section above. Cart, checkout, and account stay available.

= Minimum requirements =

* WordPress 6.4 or newer
* PHP 8.1 or newer
* WooCommerce 8.0 or newer
* Elementor is optional

Do not activate this plugin on a site that already runs a branded auction-platform plugin that shares the same `wcap_` database prefix.

== Frequently Asked Questions ==

= Will this plugin replace my login and register pages? =

Yes. After the plugin login page is created (Auctions → Setup), WordPress login, register, and lost-password requests (wp-login.php) are redirected to the plugin’s Log in page ([wcap_login]). Cart, checkout, and My Account are not replaced.

= Will the WooCommerce shop and product pages be hidden? =

Yes, by default. After install, “Disable WooCommerce shop and single product pages” is enabled so the shop catalog and single product pages are hidden from visitors. Cart, checkout, and My Account remain available for winner payment. Turn the option off under Auctions → Settings if you still want a normal WooCommerce shop. Auction products may remain catalog-hidden so they are sold only through auction checkout.

= What is Logicanvas Auctions for WooCommerce? =

A WooCommerce plugin for timed and live auctions. Store owners and approved third-party holders list lots. Registered bidders place server-side bids. Winners pay through normal WooCommerce checkout, tax, shipping, and payment gateways.

= Is WooCommerce required? =

Yes. The plugin shows an admin notice and fails gracefully if WooCommerce is missing. It never stores card numbers and never bypasses your store’s payment gateways.

= Is Elementor required? =

No. Shortcodes, templates, REST endpoints, and dashboards work without Elementor. Widgets and dynamic tags load only when Elementor is active.

= What versions are supported? =

WordPress 6.4+, PHP 8.1+, and WooCommerce 8.0+. HPOS and block checkout compatibility are declared.

= What is the difference between timed and live auctions? =

A timed auction runs for a fixed start and end window. Bidders place bids until the auction closes automatically (with optional soft-close extensions). A live auction uses a lobby and live room controlled by the holder: open lobby, start, pause/resume, going once/twice, extend, sell, mark unsold, or cancel. In both modes the server — not the browser — is authoritative for price, winner, and state.

= When does an auction accept bids? =

Timed auctions accept bids only while active. Live auctions accept bids while live, going once, or going twice. Lobby and paused states do not accept bidding.

= Who can create auctions? =

Site administrators can create and manage auctions. Third-party users must apply to become an auction holder, be approved by an administrator, and then create or submit auctions from the holder dashboard. Suspended holders cannot publish or host.

= How do I become an auction holder? =

Open the Become an auction holder page (created by Setup, shortcode [wcap_holder_apply]), submit the application, and wait for administrator approval. Statuses are pending, approved, rejected, or suspended.

= Who can place a bid? =

Authenticated users with bidding capability can bid on auctions they are allowed to access. Guests can usually view public auctions but must log in or register before bidding. Nobody may bid on their own auction.

= Can I bid on my own auction? =

No. Self-bidding is always rejected by the server, even if the user has both holder and bidder capabilities.

= How are bids accepted? =

Every bid is validated on the server with a locked auction row, increment and eligibility checks, rate limits, and idempotency. Duplicate clicks with the same idempotency key return the original result instead of creating another bid. The first valid committed bid wins a tie.

= What is soft close (anti-sniping)? =

On timed auctions, if a valid bid arrives within the last soft-close window (default 120 seconds), the end time is extended (default +120 seconds), up to a maximum number of extensions (default 20). The new end time is stored and broadcast from the server.

= What happens if the reserve is not met? =

If the auction ends below the reserve price, it can close as reserve not met / unsold. Public displays typically show only whether the reserve is met, not a hidden reserve amount.

= Is proxy (maximum) bidding available? =

Proxy / maximum bidding for timed auctions exists as a setting but is off by default. It is not enabled for live auctions by default. Maximum values stay private.

= What visibility options exist? =

Public (listed), unlisted (share link, not in public listings), and private / invite-only (invitation required). Invitation tokens are stored as hashes, can expire or be revoked, and never replace authentication for bidding.

= How does the winner pay? =

When an auction closes with a winner, an award record is created with the final amount and a payment deadline (default 48 hours). Only that winner can start checkout. Quantity is locked to one and the line price is the awarded amount. Taxes and shipping use WooCommerce. Coupons and mixed carts can be disallowed in settings.

= What if the winner does not pay? =

After the payment deadline, the award can be marked defaulted. The plugin does not automatically charge bidders. It does not automatically offer the lot to a runner-up unless that policy is explicitly enabled.

= How do commissions and seller payouts work? =

The website is the checkout merchant. Each auction records its holder. Settlements track gross amount, commission, fees, refunds, net amount, and payout status. This edition supports manual payout tracking (pending, approved, paid, reversed, disputed). Automatic marketplace payouts (for example Stripe Connect) are not included; a provider interface exists for later integrations.

= What currency is used? =

Each auction is locked to the store currency at creation. If the store currency changes later, mixed-currency settlement should be handled carefully. Multi-currency auction settlement needs a dedicated integration.

= How do I create the frontend pages? =

In wp-admin open Auctions → Setup and create auction pages. The wizard creates ordinary WordPress pages with shortcodes. You can edit them in the block editor or Elementor. Deleted pages are not recreated automatically unless you run setup again for missing pages.

= Which shortcodes are available? =

[wcap_auction_grid], [wcap_single_auction], [wcap_live_room], [wcap_live_host], [wcap_submit_auction], [wcap_holder_dashboard], [wcap_bidder_dashboard], [wcap_my_bids], [wcap_my_wins], [wcap_pay_award], [wcap_holder_apply], [wcap_login], [wcap_register], [wcap_countdown], [wcap_bid_panel], and [wcap_bid_history].

= Where do I manage auctions in wp-admin? =

Under the Auctions menu: Dashboard, Add Auction, All Auctions, Holders, Bids, Awards, Settlements, Settings, Diagnostics, and Setup. Exact items depend on capabilities.

= Is there a dedicated login page? =

Yes. Setup creates a Log in page with [wcap_login] for login, register, lost password, and reset flows. Settings can redirect users to holder/bidder dashboards after login and optionally restrict wp-admin for those roles.

= Which emails are sent? =

Notifications cover approval/rejection, scheduling, live start, bid accepted, outbid, reserve met (without revealing a hidden reserve), extensions, winner, unsuccessful bidders, payment reminders, deadline expiry, payment received, holder sale, settlement updates, and cancellations. Users may manage preferences where offered.

= How do live rooms stay in sync? =

The default realtime mode is authenticated REST polling by event sequence (about one second while live, slower in lobby/paused). Push providers can notify clients that new state exists, but clients must still load authoritative state from WordPress. A push message cannot accept a bid or declare a winner.

= Auctions did not close on time. Why? =

Closing uses WooCommerce Action Scheduler (WP-Cron fallback). If cron is delayed, opening an auction page or state endpoint after end time still runs an idempotent close. Check WooCommerce → Status → Scheduled Actions and hosting cron reliability.

= Does uninstall delete my data? =

Deactivation never deletes business data. Uninstall deletes tables and auction posts only if “Remove all plugin data on uninstall” is enabled in settings (default is off).

= How is personal data handled? =

The plugin integrates with WordPress personal data export and erase tools. Financial/transaction rows may be anonymized rather than deleted when retention requires them. Public bidder identities are masked/aliased. IP addresses can be hashed.

= Can I override templates? =

Yes. Copy templates into your-theme/logicanvas-auctions/ using the same relative paths as the plugin templates/ folder.

= Can I run this with another auction-platform plugin? =

Do not activate this edition on a site that already runs a branded auction-platform plugin that shares the wcap_ table prefix. They must not be installed together.

= Does the plugin store credit card details? =

No. Payments are processed only through configured WooCommerce gateways.

= Is there a REST API? =

Yes. Namespace logicanvas-auctions/v1. Cookie authentication plus a REST nonce is required for write routes. See the documentation for routes and schemas.

= Are WP-CLI commands available? =

Yes: wp logicanvas-auctions list, close, overdue, rebuild, schema, diagnostics, reschedule, and migrate.

== Screenshots ==

1. Auction catalog / grid on the frontend.
2. Single auction page with current price, countdown, and bid panel.
3. Live auction room with host-controlled status and bid activity.
4. Auction holder dashboard for listings, orders, and settlements.
5. Bidder dashboard for active bids, watchlist, and wins.
6. Auctions admin settings and diagnostics in wp-admin.

== Changelog ==

= 1.1.0 =
* Frontend edit listing flow with REST PUT/PATCH updates for drafts and scheduled lots.
* Single auction gallery lightbox, Details/Share tabs, QR share codes, and edit listing action.
* SEO-friendlier auction permalinks (`/auctions/…`); flush rewrite rules after upgrade.
* Dashboard polish, Yoast/SEO compatibility registration, and holder listing edit links.

= 1.0.0 =
* First public release on WordPress.org.
* Timed and live auctions with server-authoritative bidding.
* Soft close, awards, payment deadlines, and WooCommerce winner checkout.
* Commission / settlement ledger with manual payout tracking.
* Shortcodes, optional Elementor widgets, REST polling, privacy tools, and WP-CLI.
* Holder applications, bidder dashboards, and dedicated login flows.

== Upgrade Notice ==

= 1.1.0 =
Adds frontend listing edits, share/QR tools, and `/auctions/` permalinks. Visit any admin page once after upgrade so rewrite rules flush, or go to Settings → Permalinks and click Save.

= 1.0.0 =
Initial public release. After activation, open Auctions → Setup to create frontend pages, then configure Auctions → Settings.
