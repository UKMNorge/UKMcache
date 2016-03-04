<?php
class UKMcache {
	var $table_name = 'ukm_cache';
	var $cache_extension = '.ukmcache.html';
	var $cache_dir = '/tmp/UKMcache/';
	
	public function exists( $url ) {
		$cache_file = $this->_url_to_cache_name( $url );
		$cache_path = $this->cache_dir . $cache_file;
		
		if( file_exists( $cache_path ) ) {
			#echo '<h1>CACHE:</h1>';
			echo file_get_contents( $cache_path );
			die();
		}
		return false;
	}
	
	public function create( $post_id, $pl_id, $view, $url, $html ) {

		$expires = $this->_expires( $view );
		$this->_database_insert( $post_id, $pl_id, $view, $url, $expires );
		$this->_file_write( $url, $html );
	}
	
	/*************************************************************************************** */
	/* CLEAN
	/*************************************************************************************** */
	public function clean_page_and_post( $post_id ) {
		global $post;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;	
		if( $post->post_type == 'revision' ) return;
		
		if( $post->post_type == 'post' ) {
			$this->clean_category();
			$this->clean_homepage();
		}
		
		$results = $this->_find_by_post_id( $post_id );
		foreach( $results as $row ) {
			$this->_remove( $row->url );
		}
	}
	
	public function clean_url( $url ) {
		$url = $this->_url_to_cache_name( $url );
		$this->_remove( $url );
	}
	
	public function clean( $verbose=true ) {
		$table = $this->_database_table();
		if( !defined('CLEAN_START') ) {
			define( 'CLEAN_START', date('Y-m-d H:i:s') );
		}

		$results = $this->wpdb->get_results("SELECT * FROM `$table` WHERE `expires` < '". CLEAN_START ."'");
		foreach( $results as $row ) {
			$this->_remove( $row->url );
			if( $verbose ) {
				echo '<br />DELETE: '. var_export( $row, true );
			}
		}
	}
	
	public function clean_homepage( ) {
		$blog_id = $this->blog_id;
		$table = $this->_database_table();
		$results = $this->wpdb->get_results("SELECT * 
											FROM `$table` 
											WHERE `blog_id` = '$blog_id'
											AND `view` LIKE 'homepage%'
											");
		foreach( $results as $row ) {
			$this->_remove( $row->url );
		}		

		// Fylke
		$results = $this->wpdb->get_results("SELECT * 
											FROM `$table` 
											WHERE `blog_id` = '$blog_id'
											AND `view` LIKE 'fylke%'
											");
		foreach( $results as $row ) {
			$this->_remove( $row->url );
		}

		// Kommune
		$results = $this->wpdb->get_results("SELECT * 
											FROM `$table` 
											WHERE `blog_id` = '$blog_id'
											AND `view` LIKE 'kommune%'
											");
		foreach( $results as $row ) {
			$this->_remove( $row->url );
		}
	}
	
	public function clean_category( ) {
		$blog_id = $this->blog_id;
		$table = $this->_database_table();
		$results = $this->wpdb->get_results("SELECT * 
											FROM `$table` 
											WHERE `blog_id` = '$blog_id'
											AND `view` = 'archive'
											");
		foreach( $results as $row ) {
			$this->_remove( $row->url );
		}		
	}
	
	public function clean_deltakersider( $action ) {
		$pl_id = get_option('pl_id');
		$table = $this->_database_table();
		$results = $this->wpdb->get_results("SELECT * 
											FROM `$table` 
											WHERE `pl_id` = '$pl_id'
											AND `view` IN('pameldte','program')
											");
		foreach( $results as $row ) {
			$this->_remove( $row->url );
		}
	}
	
	private function _remove( $url ) {
		$this->_file_delete( $url );
		$this->_database_delete( $url );
	}
	
	/*************************************************************************************** */
	/* CACHE FILE LOAD BY...
	/*************************************************************************************** */
	private function _find_by_post_id( $post_id ) {
		$table = $this->_database_table();
		return $this->wpdb->get_results("SELECT * FROM `$table` WHERE `post_id` = '$post_id'");
	}
	
	
	/*************************************************************************************** */
	/* CACHE META HELPERS
	/*************************************************************************************** */
	private function _url_to_cache_name( $url ) {
		$url = str_replace( $this->cache_extension, '', $url );			// Hvis kjørt 2 ganger, fjern dobbel-ext
		$url = preg_replace('/[^A-Za-z0-9-_\/]/', '', $url);// Stripp uønskede tegn
		$filename = str_replace( '/', '__', $url );			// Bytt ut slash med __
		return $filename . $this->cache_extension;
	}
	
	private function _expires( $view ) {
		if( strpos( $view, 'fylke_' ) === 0 ) {
			$view = 'fylke';	
		} elseif ( strpos( $view, 'kommune_' ) === 0 ) {
			$view = 'kommune';
		}
		switch( $view ) {
			case 'dinmonstring':		$cache_for_minutes = 1440;/* 24h */ break;
			case 'homepage_norge':		$cache_for_minutes = 1;				break;
			case 'page':				$cache_for_minutes = 10;			break;
			case 'post':				$cache_for_minutes = 10;			break;
			case 'fylke':				$cache_for_minutes = 1;				break;
			case 'kommune':				$cache_for_minutes = 1;				break;
			case 'program_rekkefolge':
			case 'program':				$cache_for_minutes = 2;				break;
			case 'pameldte':			$cache_for_minutes = 0.5;			break;
			default:					$cache_for_minutes = 1;			
										#echo 'Mangler cache-støtte for '. $view;
																			break;
		}
		
		$expires = time() + ( $cache_for_minutes * 60 );
		return date('Y-m-d H:i:s', $expires);
	}
	
	/*************************************************************************************** */
	/* FILES
	/*************************************************************************************** */
	private function _file_cache_dir_is_writeable() {
		return file_exists( $this->cache_dir ) && is_writeable( $this->cache_dir );
	}

	public function cache_check() {
		## 04.03.16 - Lagt til av @asgeirsh da cachen ikke ble brukt pga mappefeil og vi ikke fikk beskjed.
		## Sjekk om cache-mappen finnes og varsle i network-dash hvis ikke.
		if(!$this->_file_cache_dir_is_writeable()) {
			$MESSAGE[] = array(	'level' => 'alert-danger', 
								'module' => 'UKMcache', 
								'header' => 'Mappen '.$this->cache_dir.' finnes ikke eller er ikke skrivbar - nettsiden vil være tregere!', 
								'body' => 'Rett problemet med å kjøre "mkdir '.$this->cache_dir.'" og "chmod -R 777 '.$this->cache_dir.'".' 
							);
			return $MESSAGE;
		}
		return true;
	}
		
	private function _file_delete( $url ) {
		$filename = $this->_url_to_cache_name( $url );
		$filepath = $this->cache_dir . $filename;
		
		$res = @unlink( $filepath );	
		return $res;
	}
	private function _file_write( $url, $html ) {
		$filename = $this->_url_to_cache_name( $url );
		$filepath = $this->cache_dir . $filename;
		if( $this->_file_cache_dir_is_writeable() ) {
			$handle = fopen( $filepath, 'w' );
			fwrite( $handle, $html );
			fclose( $handle );
			return true;
		} else {
			#echo 'Kunne ikke lagre cache!';
			return false;
		}
	}


	/*************************************************************************************** */
	/* DATABASE
	/*************************************************************************************** */
	private function _database_insert( $post_id, $pl_id, $view, $url, $expires ) {
		$this->_database_delete( $url );
		$data = array(	'post_id' => $post_id,
						'pl_id' => $pl_id,
						'blog_id' => $this->blog_id,
						'view' => $view,
						'url' => $this->_url_to_cache_name( $url ),
						'expires' => $expires
					);
		$this->wpdb->insert( $this->_database_table(), $data );
	}

	private function _database_delete( $url ) {
		$table_name = $this->_database_table();
		
		$this->wpdb->query( 
			$this->wpdb->prepare( 
				"DELETE FROM $table_name
				 WHERE url = %s
				",
			    	$this->_url_to_cache_name( $url )
		        )
		);
	}
	private function _database_table() {
		return $this->wpdb->base_prefix . $this->table_name;
	}

	/*************************************************************************************** */
	/* PLUGIN ACTIVATION
	/*************************************************************************************** */

	/**
	 * Activate UKMcache plugin
	 *
	 * @trigger register_activation_hook
	 */
	public function plugin_activate() {			
		if( !$this->_file_cache_dir_is_writeable() ) {
			die( 'Cache directory not writeable! ('. $this->cache_dir .')');
		}
		$this->_table_create();
	}
	
	public function plugin_deactivate() {
		#echo 'plugin_deactivate UKMcache';
	}

	private function _table_create() {
		$table_name = $this->_database_table();
		
		$sql = "CREATE TABLE `$table_name` (
				  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
				  `post_id` int(11) DEFAULT NULL,
				  `pl_id` int(11) DEFAULT NULL,
				  `blog_id` int(11) DEFAULT NULL,
				  `view` varchar(100) DEFAULT NULL,
				  `url` text NOT NULL,
				  `expires` datetime NOT NULL,
			  PRIMARY KEY (`id`),
			  KEY `post_id` (`post_id`),
			  KEY `pl_id` (`pl_id`),
			  KEY `blog_id` (`blog_id`),
			  KEY `view` (`view`),
			  KEY `expires` (`expires`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;
			";
		
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}
	
	/*************************************************************************************** */
	/* CLASS CONSTRUCTOR
	/*************************************************************************************** */
	public function __construct( $wpdb, $blog_id ) {
		$this->wpdb = $wpdb;
		$this->blog_id = $blog_id;
	}

}
