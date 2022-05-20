<?php

/**
 * Fix price formating in LearnDash
 * 
 * Puts the currency symbol after the price and adds a space in between to
 * follow Swedish pricing formating. Add this snippet to your child-theme’s
 * functions.php file.
 * 
 * @param   String  Price formating
 * @return  String  Price formating
 */
 
function opal_learndash_swe_currency( $args ) {
    return '{price} {currency}';
}

add_filter( 'learndash_course_grid_price_text_format', 'opal_learndash_swe_currency', 10, 1 );
