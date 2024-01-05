<?php

/**
 * Opal Gravity Tweaker File Protector
 * 
 * Improves Gravity Forms’ protection of user uploaded files. Blocks direct
 * access to upload folder, requires logging in before downloading, and 
 * signs download links with a user unique and time restricted hash. A user
 * revokes the validity of their old hashes every time they log in.
 * 
 * @author 		Nikka Systems <support@nikkasystems.com> 
 * @package     Opal
 * @since       2.0.00 beta 1
 * @version		2.0.00 beta 1
 */


// Exit if accessed directly. 
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class Opal_Gravity_File_Protector {

	private static $instance;
	private static $opal_hash_extra_days = 1; // High values affects performance.

	/**
	 *  Singleton initiator
	 */

	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Class constructor
	 * 
	 * @return  void
	 */

	public function __construct() {
		add_filter( 'gform_upload_root_htaccess_rules', array( $this, 'block_access_to_real_uploads_folder' ), 10, 1 );
		add_filter( 'gform_require_login_pre_download', array( $this, 'require_login_for_downloads' ), 10, 3 );
		add_filter( 'gform_secure_file_download_url', array( $this, 'add_opal_hash_to_download_url' ), 10, 2 );
		add_filter( 'gform_permission_granted_pre_download', array( $this, 'require_opal_hash_for_downloads' ), 10, 3 );
	}


	/**
	 * Block Access to Real Uploads Folder
	 * 
	 * Adds rewrite rules to Gravity Forms’ custom uploads folder so that files 
	 * cannot be directly accessed. The feature can be enabled from the form’s
	 * Opal Settings page. Secure download links will still work when enabled.
	 * Works only on Apache/LiteSpeed servers.  
	 * 
	 * The folders’ names start with the folder ID followed by a dash 
	 * (e.g., “10-”). Changes are added through the daily cron-job 
	 * “gravityforms_cron”. 
	 * 
	 * @since 2.0.00
	 * 
	 * @param mixed $rules Gravity Forms’ htaccess rules. 
	 * @return mixed The updated htaccess rules. 
	 */

	public function block_access_to_real_uploads_folder( $rules ) {

		$forms = GFAPI::get_forms();
		$blocked_form_ids = array();
		$setting = 'general_functions_block_direct_access_to_uploads_folder';

		foreach ( $forms as $form ) {
			$gf_helper = new Opal_Gravity_Helper( $form );

			if ( $gf_helper->get_form_setting( $setting ) === '1' ) { // String!
				$blocked_form_ids[] = $form['id'] . '-';
			}
		}

		if ( empty( $blocked_form_ids ) ) {
			return $rules;
		}

		// Create the rule (e.g., “^10-|11- - [F]”). 
		$rewrite_rule = '^' . implode( '|', $blocked_form_ids ) . ' - [F]';

		$rules[] = '<IfModule mod_rewrite.c>';
		$rules[] = '  RewriteEngine On';
		$rules[] = '  RewriteRule ' . $rewrite_rule;
		$rules[] = '</IfModule>';

		return $rules;
	}


	/**
	 * Require Login for Downloads
	 * 
	 * Requires users to log in before downloading files through secure download
	 * links. 
	 * 
	 * @since 2.0.00
	 * 
	 * @param bool $require_login If the user need to be logged in to access the file.
	 * @param int $form_id The ID of the form used to upload the requested file.
	 * @param int $field_id The ID of the field used to upload the requested file.
	 * @return bool If the user need to be logged in to access the file.
	 */

	public function require_login_for_downloads( $require_login, $form_id, $field_id ) {

		$form = GFAPI::get_form( $form_id );
		$setting = 'general_functions_require_login_for_downloads';
		$gf_helper = new Opal_Gravity_Helper( $form );

		if ( $gf_helper->get_form_setting( $setting ) === '1' ) { // String!
			$require_login = true;
		}

		return $require_login;
	}


	/**
	 * Add Opal Hash to Download URL
	 * 
	 * Adds the Opal hash to secure download links.
	 * 
	 * @since 2.0.00
	 * 
	 * @param string $download_url The URL from which to download the file.
	 * @param GF_Field_Fileupload $field The field object for further context.
	 * @return string The URL from which to download the file.
	 */

	public function add_opal_hash_to_download_url( $download_url, $field ) {

		if ( ! is_user_logged_in() ) {
			return $download_url;
		}

		$form_id = $field->formId;
		$form = GFAPI::get_form( $form_id );

		if ( $form === false ) {
			return $download_url;
		}

		$setting = 'general_functions_require_login_for_downloads';
		$gf_helper = new Opal_Gravity_Helper( $form );

		if ( $gf_helper->get_form_setting( $setting ) !== '1' ) { // String!
			return $download_url;
		}

		$url_queries = parse_url( $download_url, PHP_URL_QUERY );

		if ( ! is_string( $url_queries ) || $url_queries === '' ) {
			return $download_url;
		}

		parse_str( $url_queries, $queries );

		if ( ! isset( $queries['hash'] ) ) {
			return $download_url;
		}

		$gf_hash = $queries['hash'];
		$user_id = get_current_user_id();
		$date_today = date( 'Ymd' );
		$opal_hash = $this->get_opal_download_hash( $gf_hash, $user_id, $date_today );

		if ( $opal_hash === false ) {
			return $download_url;
		}

		$download_url = add_query_arg( 'opal-hash', $opal_hash, $download_url );
		return $download_url;
	}


	/**
	 * Require Opal Hash for Downloads
	 * 
	 * Checks secure download links so that they have a valid Opal hash. 
	 * 
	 * @since 2.0.00
	 * 
	 * @param bool $permission_granted If the user have permission to access the file.
	 * @param int $form_id The ID of the form used to upload the requested file.
	 * @param int $field_idThe ID of the field used to upload the requested file.
	 * @return bool If the user have permission to access the file.
	 */

	public function require_opal_hash_for_downloads( $permission_granted, $form_id, $field_id ) {

		$form = GFAPI::get_form( $form_id );
		$setting = 'general_functions_require_login_for_downloads';
		$gf_helper = new Opal_Gravity_Helper( $form );

		if ( $gf_helper->get_form_setting( $setting ) !== '1' ) { // String!
			return $permission_granted;
		}

		$url = site_url() . sanitize_text_field( $_SERVER['REQUEST_URI'] );
		$url_queries = parse_url( $url, PHP_URL_QUERY );

		if ( ! is_string( $url_queries ) || $url_queries === '' ) {
			return false;
		}

		parse_str( $url_queries, $queries );

		if ( ! isset( $queries['hash'] ) || ! isset( $queries['opal-hash'] ) ) {
			return false;
		}

		$user_id = get_current_user_id();
		$gf_hash = $queries['hash'];
		$form = GFAPI::get_form( $form_id );

		if ( $form === false ) {
			return false;
		}

		$provided_opal_hash = $queries['opal-hash'];
		$valid_hashes = array();

		// Generate valid hashes for today and n days back.
		for ( $day = 0; $day <= $this->opal_hash_extra_days; $day++ ) {
			$date = date( 'Ymd', strtotime( '-' . $day . ' days' ) );
			$valid_hashes[] = $this->get_opal_download_hash( $gf_hash, $user_id, $date );
		}

		if ( empty( $valid_hashes ) || ! in_array( $provided_opal_hash, $valid_hashes ) ) {
			return false;
		}

		return $permission_granted;
	}


	/**
	 * Get Opal Download Hash
	 * 
	 * Generates the Opal hash for a specific Gravity Forms hash (which is
	 * unique for each file), user ID and date. 
	 * 
	 * @param string $gf_hash Gravity Forms secure download hash.
	 * @param int $user_id The user ID who should have access to the file.
	 * @param string $date The date (use 'Ymd').
	 */

	private function get_opal_download_hash( $gf_hash, $user_id, $date ) {

		if ( ! defined( 'OPAL_SALT' ) ) {
			GFCommon::log_debug( __METHOD__ . '(): You must define the OPAL_SALT to use this function.' );
			return false;
		}

		// Add sesstion timestamp so old hashes are invalid after logging in again.
		$user_sessions = wp_get_all_sessions();

		if ( empty ( $user_sessions ) ) {
			GFCommon::log_debug( __METHOD__ . '(): The user ' . esc_attr( $user_id ) . ' has no active sessions.' );
			return false;
		}
		$latest_session = end( $user_sessions );
		$session_timestamp = ( $latest_session['login'] );

		$opal_salt = OPAL_SALT;
		$opal_hash = hash( 'sha256', $date . $user_id . $session_timestamp . $gf_hash . $opal_salt );
		return $opal_hash;
	}
}

Opal_Gravity_File_Protector::get_instance();