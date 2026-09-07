<?php
/**
 * Versioned database schema.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

namespace LogicanvasAuctions\Infrastructure\Database;

use LogicanvasAuctions\Config;

final class Schema {

	/**
	 * SQL statements compatible with dbDelta().
	 *
	 * @return string[]
	 */
	public function statements(): array {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();
		$state   = Config::table( Config::TABLE_AUCTION_STATE );
		$bids    = Config::table( Config::TABLE_BIDS );
		$events  = Config::table( Config::TABLE_EVENTS );
		$invites = Config::table( Config::TABLE_INVITATIONS );
		$parts   = Config::table( Config::TABLE_PARTICIPANTS );
		$awards  = Config::table( Config::TABLE_AWARDS );
		$settle  = Config::table( Config::TABLE_SETTLEMENTS );
		$payouts = Config::table( Config::TABLE_PAYOUT_REQUESTS );
		$audit   = Config::table( Config::TABLE_AUDIT );
		$watches = Config::table( Config::TABLE_WATCHES );
		$holders = Config::table( Config::TABLE_HOLDERS );
		$prefs   = Config::table( Config::TABLE_NOTIFICATION_PREFS );
		$idem    = Config::table( Config::TABLE_IDEMPOTENCY );

		return array(
			"CREATE TABLE {$state} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				holder_id bigint(20) unsigned NOT NULL,
				product_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(20) NOT NULL,
				visibility varchar(32) NOT NULL DEFAULT 'public',
				state varchar(40) NOT NULL,
				currency varchar(3) NOT NULL,
				starting_amount decimal(26,8) NOT NULL,
				reserve_amount decimal(26,8) DEFAULT NULL,
				reserve_display varchar(20) NOT NULL DEFAULT 'met_only',
				current_amount decimal(26,8) NOT NULL,
				min_increment decimal(26,8) NOT NULL,
				increment_strategy varchar(40) NOT NULL DEFAULT 'fixed',
				buy_now_amount decimal(26,8) DEFAULT NULL,
				current_leader_id bigint(20) unsigned DEFAULT NULL,
				bid_count int(10) unsigned NOT NULL DEFAULT 0,
				sequence bigint(20) unsigned NOT NULL DEFAULT 0,
				start_at_utc datetime NOT NULL,
				end_at_utc datetime DEFAULT NULL,
				original_end_at_utc datetime DEFAULT NULL,
				extension_count int(10) unsigned NOT NULL DEFAULT 0,
				soft_close_window int(10) unsigned NOT NULL DEFAULT 120,
				soft_close_extend int(10) unsigned NOT NULL DEFAULT 120,
				soft_close_max int(10) unsigned NOT NULL DEFAULT 20,
				payment_deadline_hours int(10) unsigned NOT NULL DEFAULT 48,
				quantity int(10) unsigned NOT NULL DEFAULT 1,
				tax_class varchar(100) NOT NULL DEFAULT '',
				shipping_class varchar(100) NOT NULL DEFAULT '',
				fulfilment_type varchar(40) NOT NULL DEFAULT 'shipping',
				timezone varchar(64) NOT NULL DEFAULT 'UTC',
				proxy_enabled tinyint(1) NOT NULL DEFAULT 0,
				award_id bigint(20) unsigned DEFAULT NULL,
				order_id bigint(20) unsigned DEFAULT NULL,
				settlement_id bigint(20) unsigned DEFAULT NULL,
				terms_version varchar(20) NOT NULL DEFAULT '',
				unlisted_token_hash char(64) DEFAULT NULL,
				created_at_utc datetime NOT NULL,
				updated_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_id (auction_id),
				KEY state_end (state,end_at_utc),
				KEY holder_id (holder_id),
				KEY product_id (product_id),
				KEY type_state (type,state),
				KEY visibility (visibility)
			) {$charset};",
			"CREATE TABLE {$bids} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				bidder_id bigint(20) unsigned NOT NULL,
				amount decimal(26,8) NOT NULL,
				currency varchar(3) NOT NULL,
				type varchar(20) NOT NULL DEFAULT 'regular',
				max_amount decimal(26,8) DEFAULT NULL,
				status varchar(20) NOT NULL DEFAULT 'accepted',
				idempotency_key varchar(64) NOT NULL,
				ip_hash char(64) DEFAULT NULL,
				user_agent_hash char(64) DEFAULT NULL,
				created_at_utc datetime NOT NULL,
				voided_at_utc datetime DEFAULT NULL,
				voided_by bigint(20) unsigned DEFAULT NULL,
				void_reason text,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_idempotency (auction_id,idempotency_key),
				KEY auction_created (auction_id,created_at_utc),
				KEY auction_amount (auction_id,amount),
				KEY bidder_id (bidder_id),
				KEY status (status)
			) {$charset};",
			"CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				sequence bigint(20) unsigned NOT NULL,
				event_type varchar(64) NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				actor_type varchar(20) NOT NULL DEFAULT 'system',
				payload longtext,
				correlation_key varchar(64) DEFAULT NULL,
				created_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_sequence (auction_id,sequence),
				KEY auction_type (auction_id,event_type),
				KEY created_at_utc (created_at_utc)
			) {$charset};",
			"CREATE TABLE {$invites} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				token_hash char(64) NOT NULL,
				email_hash char(64) DEFAULT NULL,
				user_id bigint(20) unsigned DEFAULT NULL,
				max_uses int(10) unsigned NOT NULL DEFAULT 1,
				use_count int(10) unsigned NOT NULL DEFAULT 0,
				expires_at_utc datetime DEFAULT NULL,
				revoked_at_utc datetime DEFAULT NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY token_hash (token_hash),
				KEY auction_id (auction_id)
			) {$charset};",
			"CREATE TABLE {$parts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				role varchar(20) NOT NULL DEFAULT 'bidder',
				joined_at_utc datetime NOT NULL,
				last_seen_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_user (auction_id,user_id),
				KEY last_seen (auction_id,last_seen_utc)
			) {$charset};",
			"CREATE TABLE {$awards} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				winner_id bigint(20) unsigned NOT NULL,
				bid_id bigint(20) unsigned NOT NULL DEFAULT 0,
				amount decimal(26,8) NOT NULL,
				currency varchar(3) NOT NULL,
				status varchar(32) NOT NULL DEFAULT 'pending',
				payment_deadline_utc datetime NOT NULL,
				order_id bigint(20) unsigned DEFAULT NULL,
				offered_to_runner_up tinyint(1) NOT NULL DEFAULT 0,
				correlation_key varchar(64) NOT NULL,
				created_at_utc datetime NOT NULL,
				updated_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_active (auction_id,correlation_key),
				KEY winner_status (winner_id,status),
				KEY deadline (payment_deadline_utc,status)
			) {$charset};",
			"CREATE TABLE {$settle} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				award_id bigint(20) unsigned NOT NULL DEFAULT 0,
				order_id bigint(20) unsigned NOT NULL DEFAULT 0,
				holder_id bigint(20) unsigned NOT NULL,
				currency varchar(3) NOT NULL,
				gross_amount decimal(26,8) NOT NULL,
				commission_amount decimal(26,8) NOT NULL,
				fee_amount decimal(26,8) NOT NULL DEFAULT 0.00000000,
				refund_amount decimal(26,8) NOT NULL DEFAULT 0.00000000,
				net_amount decimal(26,8) NOT NULL,
				released_amount decimal(26,8) NOT NULL DEFAULT 0.00000000,
				payout_status varchar(20) NOT NULL DEFAULT 'pending',
				commission_snapshot longtext,
				created_at_utc datetime NOT NULL,
				updated_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_id (auction_id),
				KEY holder_status (holder_id,payout_status),
				KEY order_id (order_id)
			) {$charset};",
			"CREATE TABLE {$payouts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				holder_id bigint(20) unsigned NOT NULL,
				amount decimal(26,8) NOT NULL,
				currency varchar(3) NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				payment_method varchar(40) NOT NULL DEFAULT '',
				payment_details longtext,
				holder_note text,
				admin_note text,
				transaction_reference varchar(191) NOT NULL DEFAULT '',
				transaction_details text,
				proof_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				processed_by bigint(20) unsigned DEFAULT NULL,
				processed_at_utc datetime DEFAULT NULL,
				created_at_utc datetime NOT NULL,
				updated_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY holder_status (holder_id,status),
				KEY status (status),
				KEY created_at_utc (created_at_utc)
			) {$charset};",
			"CREATE TABLE {$audit} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(64) NOT NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				actor_type varchar(20) NOT NULL DEFAULT 'user',
				reason text,
				correlation_key varchar(64) DEFAULT NULL,
				metadata longtext,
				created_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY auction_id (auction_id),
				KEY action (action),
				KEY created_at_utc (created_at_utc)
			) {$charset};",
			"CREATE TABLE {$watches} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				auction_id bigint(20) unsigned NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				created_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY auction_user (auction_id,user_id),
				KEY user_id (user_id)
			) {$charset};",
			"CREATE TABLE {$holders} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				company varchar(191) NOT NULL DEFAULT '',
				application_notes longtext,
				moderation_notes longtext,
				commission_fixed decimal(26,8) DEFAULT NULL,
				commission_percent decimal(26,8) DEFAULT NULL,
				reviewed_by bigint(20) unsigned DEFAULT NULL,
				reviewed_at_utc datetime DEFAULT NULL,
				created_at_utc datetime NOT NULL,
				updated_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_id (user_id),
				KEY status (status)
			) {$charset};",
			"CREATE TABLE {$prefs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				event_key varchar(64) NOT NULL,
				enabled tinyint(1) NOT NULL DEFAULT 1,
				updated_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_event (user_id,event_key)
			) {$charset};",
			"CREATE TABLE {$idem} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				scope varchar(64) NOT NULL,
				idempotency_key varchar(64) NOT NULL,
				response_hash char(64) DEFAULT NULL,
				payload longtext,
				created_at_utc datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY scope_key (scope,idempotency_key)
			) {$charset};",
		);
	}
}
