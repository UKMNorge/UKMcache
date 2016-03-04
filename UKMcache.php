<?php  
/* 
Plugin Name: UKM Cache
Plugin URI: http://www.ukm-norge.no
Description: Lagrer HTML-utgaver av nettsiden for å avlaste serveren
Author: UKM Norge / M Mandal 
Version: 1.0 
Author URI: http://www.ukm-norge.no
*/

if( !class_exists( 'UKMcache' ) ) {
	require_once('UKMcache.class.php');
}

global $wpdb;
$UKMcache = new UKMcache( $wpdb, $blog_id );

add_action( 'UKMcache_exists', array($UKMcache, 'exists'), 0, 1 );
add_action( 'UKMcache_create', array($UKMcache, 'create'), 0, 5 );
add_action( 'UKMcache_clean_url', array($UKMcache, 'clean_url'), 0, 1 );

add_filter( 'UKMWPNETWDASH_messages', 'check_cache');

# Made for external checking of cache folder availability from network dashboard.
function check_cache() {
	global $UKMcache;
	return $UKMcache->cache_check();
}

register_activation_hook( __FILE__, array($UKMcache, 'plugin_activate' ) );
register_deactivation_hook( __FILE__, array($UKMcache, 'plugindeactivate' ) );

function test_this() {

}
// POST AND PAGE HOOKS
add_action('save_post', 'test_this' );
add_action('save_post', array($UKMcache, 'clean_page_and_post') );
add_action('delete_post', array($UKMcache, 'clean_page_and_post') );

add_action('UKMprogram_save', array($UKMcache, 'clean_deltakersider'), 'Program' );