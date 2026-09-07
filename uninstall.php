<?php
/**
 * Uninstall. Deletes data only when the administrator enabled removal.
 *
 * @package LogicanvasAuctions
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$wcap_remove = get_option( 'wcap_remove_data_on_uninstall', '0' );
$wcap_settings = get_option( 'wcap_settings', array() );
if ( is_array( $wcap_settings ) && ! empty( $wcap_settings['remove_data_on_uninstall'] ) ) {
	$wcap_remove = '1';
}

if ( '1' !== (string) $wcap_remove ) {
	return;
}

global $wpdb;

$wcap_tables = array(
	'wcap_auction_state',
	'wcap_bids',
	'wcap_events',
	'wcap_invitations',
	'wcap_participants',
	'wcap_awards',
	'wcap_settlements',
	'wcap_payout_requests',
	'wcap_audit_log',
	'wcap_watches',
	'wcap_holder_accounts',
	'wcap_notification_prefs',
	'wcap_idempotency',
);

foreach ( $wcap_tables as $wcap_table ) {
	$wcap_name = $wpdb->prefix . $wcap_table;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstall drop of plugin tables only.
	$wpdb->query( "DROP TABLE IF EXISTS {$wcap_name}" );
}

$wcap_posts = get_posts(
	array(
		'post_type'      => 'wcap_auction',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'post_status'    => 'any',
	)
);
foreach ( $wcap_posts as $wcap_id ) {
	wp_delete_post( (int) $wcap_id, true );
}

delete_option( 'wcap_settings' );
delete_option( 'wcap_db_version' );
delete_option( 'wcap_pages' );
delete_option( 'wcap_remove_data_on_uninstall' );

remove_role( 'wcap_auction_holder' );
remove_role( 'wcap_auction_bidder' );
