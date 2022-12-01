<?php

/**
 * Opal FluentCRM Unsubscription Redirect
 * 
 * Redirects users who want to unsubscribe, from the default design deviant and 
 * uncustomizable unsubscription page to any page or post. Overrides the
 * redirection URL set in FluentCRM’s settings. Hooks into 
 * fluentcrm_unsubscribe_head on FluentCRM’s unsubscribe.php. Add this snippet
 * to your child-theme’s functions.php file. 
 * 
 * @param   none
 * @return  void
 */

function opal_fluentcrm_unsubscription_redirect() {
    
    $redirect_post_id = 38002; // Enter the post’s or page’s ID here.

    // Return if secure hash isn’t set or the route isn’t unsubscribe.
    if( !isset( $_GET['secure_hash' ] ) ||
        !isset( $_GET['route'] ) || $_GET['route'] !== 'unsubscribe' ) {
        return;
    }

    // Return if FluentCRM isn’t installed and active.
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    if ( !is_plugin_active( 'fluent-crm/fluent-crm.php' ) ) {
        return;
    }
    
    // Get secure hash from URL.
    $secure_hash = sanitize_text_field( $_GET['secure_hash'] );

    // Get subscriber by secure hash.
    $contacts_api = FluentCrmApi( 'contacts' );
    $subscriber = $contacts_api->getContactBySecureHash( $secure_hash );

    // Return if no subscriber was found. 
    if( !$subscriber ) {
        return;
    }
    
    $status = $subscriber->status;

    // Change status to unsubscribed and save.
    if ($status !== 'unsubscribed') {
        $subscriber->status = 'unsubscribed';
        $subscriber->save();
    }

    // Redirect user to custom page.
    wp_safe_redirect( get_permalink( $redirect_post_id ) );
    exit;
}

add_action( 'fluentcrm_unsubscribe_head', 'opal_fluentcrm_unsubscription_redirect', 10, 0 );
