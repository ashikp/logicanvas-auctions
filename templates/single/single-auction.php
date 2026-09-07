<?php
/**
 * Single auction theme template.
 *
 * @package LogicanvasAuctions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
while ( have_posts() ) :
	the_post();
	echo do_shortcode( '[wcap_single_auction id="' . get_the_ID() . '"]' );
endwhile;
get_footer();
