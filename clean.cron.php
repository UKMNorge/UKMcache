<?php
date_default_timezone_set('Europe/Oslo');
header('Content-Type: text/html; charset=utf-8');

if( $_SERVER['HTTP_HOST'] == 'ukm.dev' || isset($_GET['debug']) ) {
	error_reporting(E_ALL ^ E_DEPRECATED);
	ini_set('display_errors',1);
	define('CURRENT_UKM_DOMAIN', 'ukm.dev');
} else {
	error_reporting(0);
	ini_set('display_errors',0);
	define('CURRENT_UKM_DOMAIN', 'ukm.no');
}

define( 'SHORTINIT', true );
define( 'CLEAN_START', date('Y-m-d H:i:s') ); # BRUKES AV SELECT i cleanExpired
require_once( $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php' );

setlocale(LC_ALL, 'nb_NO', 'nb', 'no');

require_once( 'UKMcache.class.php' );

global $wpdb;
$UKMcache = new UKMcache( $wpdb, $blog_id );

echo '<h1>INIT clean</h1>';
$UKMcache->clean();
echo '<h1>COMPLETE clean</h1>';