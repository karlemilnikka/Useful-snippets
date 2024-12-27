<?php

/**
 * THIS SNIPPET IS NO LONGER NEEDED AFTER UPGRADING TO FLUENTCRM 2.9.31. YOU
 * CAN REMOVE THE SNIPPET FROM YOUR FUNCTIONS.PHP FILE.
 */

/**
 * FluentCRM currently loads Google Fonts from Google’s servers, causing GDPR 
 * compliance issues for us who want to send newsletters with Google Fonts to 
 * EU subscribers. While waiting for an official fix, e.g., local caching of 
 * Google Fonts like the Astra theme does, I solved the issue with this little 
 * snippet. It simply replaces Google’s servers with BunnyCDN’s GDPR compliant 
 * servers (see https://fonts.bunny.net/about).
 *
 * This solution affects all outgoing emails and emails created in the Visual 
 * Editor (Unlayer’s editor). You enable it by simply adding the snippet to your
 * child-theme’s functions.php file. You can remove it when WPManageNinja has 
 * added official support for GDPR compliant fonts. If you want to, you can 
 * tweak the snippet so that it points to resources on your own server. 
 */


/**
 * Opal FluentCRM fontfix for outgoing mail
 * 
 * Replaces requests to Google’s servers with BunnyCDN’s servers. Affects
 * all outgoiing emails. Called by wp_mail.
 * 
 * @param   array   Array with wp_mail arguments.
 * @return  array   Array where message has been filtered.
 */

function opal_fluent_crm_fontfix_mail( $args ) {
    
    if( isset( $args['message'] ) && !empty( $args['message'] ) ) {
        $args['message'] = opal_replace_google_fonts_source( $args['message'] );
    }
    
    return $args;
}

add_filter( 'wp_mail', 'opal_fluent_crm_fontfix_mail', 100, 1 );


/**
 * Opal FluentCRM fontfix for browser view (Visual Builder)
 * 
 * Replaces requests to Google’s servers with BunnyCDN’s servers in the browser
 * view for emails created by the Visual Builder (Unlayer). Called by 
 * fluent_crm/email-design-template-visual_builder.
 * 
 * @param   string      The email content to be displayed.
 * @return  string      The filtered email content to be displayed.
 */

function opal_fluent_crm_fontfix_web( $content ) {
    
    $content = opal_replace_google_fonts_source( $content );
    return $content;
}

add_filter( 'fluent_crm/email-design-template-visual_builder', 'opal_fluent_crm_fontfix_web', 10, 1 );


/**
 * Opal Replace Google Fonts Source
 * 
 * Replaces the URL for Google Fonts with BunnyCDN’s drop-in replacement service.
 * 
 * @param   string      The content where URLs should be changed.
 * @return  string      The content where URLs have been changed.
 */

function opal_replace_google_fonts_source( $content ) {
    
    if( is_string( $content ) ) {
        $google_fonts_url = 'https://fonts.googleapis.com/css';
        $compliant_fonts_url = 'https://fonts.bunny.net/css';
    
        $content = str_replace( $google_fonts_url, $compliant_fonts_url, $content );
    }
    
    return $content;
}
