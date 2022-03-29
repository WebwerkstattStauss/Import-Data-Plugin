<?php
/**
 * BIC Import Zip Data 
 *
 * @wordpress-plugin
 * Plugin Name: BIC Import Zip Data 
 * Plugin URI: https://wordpress.org/plugins/bic-izd/
 * Description: This plugin import objects data from zip archive file to wordpress Easy Real Estate sections
 * Author: BestiCoder
 * Version: 1.0
 * Requires PHP: 5.6
 * Requires at least: 4.0
 * License: Apache 2 (https://www.apache.org/licenses/LICENSE-2.0.html)
 * Text Domain: bic-izd
 */

//set new interval
add_filter( 'cron_schedules', 'cron_add_minute' );

function cron_add_minute( $schedules ) {
	// Adds once weekly to the existing schedules.
	$schedules['two_hour'] = array(
		//'interval' => 7200, // сек.
		'interval' => 1200, // сек.
		'display' => __( 'two_hour' )
	);
	return $schedules;
}

// if task do not planning - planning it
if( !wp_next_scheduled('import_objects_from_zip_at_night' ) ){
	wp_schedule_event( strtotime('00:00:00'), 'daily', 'import_objects_from_zip_at_night');
}

// вот он хук и мы вешаем на него произвольную функцию, цифра 3 - количество передаваемых параметров
add_action( 'import_objects_from_zip_at_night', 'import_data_function', 10 );
// конечно можно повесить и несколько функций на один хук!
 
function import_data_function() {
	// отправляем емайл каждый час
    require_once(plugin_dir_path(__FILE__) . 'oi_import.php');

}

// add_action('init', function(){
    
//     require_once(plugin_dir_path(__FILE__) . 'oi_import.php');
// });