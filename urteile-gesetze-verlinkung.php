<?php
defined('ABSPATH') or die("Thanks for visiting");

/**
  * Plugin Name:  Urteile und Gesetze verlinken - urteile-gesetze.de
  * Plugin URI: https://urteile-gesetze.de
  * Description: Plugin zur automatischen Verlinkung von Urteilen und Gesetzen mit urteile-gesetze.de
  * Version: 1.0
  * Author: neoSearch UG
  * Author URI: https://urteile-gesetze.de
  * License: GPL2
  * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
  * Text Domain:  urteile_gesetze_verlinkung
  */

function urteile_gesetze_verlinkung_install() {
    global $wpdb;
	include_once("wp-config.php");
    include_once("wp-includes/wp-db.php");

	$sqlStatement = "create table if not exists urteile_gesetze_post (
		id bigint(20) primary key,
		post_content longtext) character set utf8mb4 collate utf8mb4_unicode_ci";
	$wpdb->get_results($sqlStatement);
}

function urteile_gesetze_verlinkung_uninstall() {
    global $wpdb;
	include_once("wp-config.php");
    include_once("wp-includes/wp-db.php");

	$sqlStatement = "drop table urteile_gesetze_post";
	$wpdb->get_results($sqlStatement);
}

function urteile_gesetze_verlinkung_create_links($post_id)
{
    remove_action( 'save_post', 'urteile_gesetze_verlinkung_create_links' );

    global $wpdb;
	include_once("wp-config.php");
    include_once("wp-includes/wp-db.php");

    $post = get_post($post_id);
    $content = $post->post_content;
    $content = apply_filters('the_content', $content);
    urteile_gesetze_verlinkung_save_content($content);
    
    add_action( 'save_post', 'urteile_gesetze_verlinkung_create_links', 10, 3);
}

function urteile_gesetze_verlinkung_save_content($content){
    global $wpdb;
	include_once("wp-config.php");
    include_once("wp-includes/wp-db.php");
    
    $restserviceUrl = 'https://rest.urteile-gesetze.de/verlinkung';
    $curl = curl_init($restserviceUrl);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, Array("Content-Type: application/json"));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $post_data = array('text' => $content);
    $json_post_data = json_encode($post_data);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $json_post_data);
    
    try {
    $curl_response = curl_exec($curl);
    if (!is_null($curl_response)){
        $content = json_decode($curl_response)->{'text'}; 
    }
    curl_close($curl);
    } catch (\Exception $ex){
        error_log('Exception: '.$ex);
    }

	$sqlStatement = "select id from urteile_gesetze_post where id = %s";
    $wpdb->get_results($wpdb->prepare($sqlStatement, $post_id));
    
    if ($wpdb->num_rows > 0){
        $sqlStatement = "update urteile_gesetze_post set post_content = %s where id = %s";
        $wpdb->get_results($wpdb->prepare($sqlStatement, $content, $post_id));
    } else {
        $sqlStatement = "insert into urteile_gesetze_post (id, post_content) values (%s, %s)";
        $wpdb->get_results($wpdb->prepare($sqlStatement, $post_id, $content));
    }

    return $content;
}

function urteile_gesetze_verlinkung_show_content_with_links($text){
    global $wpdb;
	include_once("wp-config.php");
    include_once("wp-includes/wp-db.php");

    $post_id = get_the_ID();
    $sqlStatement = "select post_content from urteile_gesetze_post where id = %s";
    $results = $wpdb->get_results($wpdb->prepare($sqlStatement, $post_id));
    if ($wpdb->num_rows > 0) {
        return $results[0]->post_content;
    } else {
        return urteile_gesetze_verlinkung_save_content($text);
    }
}

 if (is_admin()) {
	register_activation_hook(__FILE__, 'urteile_gesetze_verlinkung_install');
	register_deactivation_hook(__FILE__, 'urteile_gesetze_verlinkung_uninstall');
	register_uninstall_hook(__FILE__, 'urteile_gesetze_verlinkung_uninstall' );
 }

add_action( 'save_post', 'urteile_gesetze_verlinkung_create_links', 10, 3);
add_filter('the_content', 'urteile_gesetze_verlinkung_show_content_with_links');

?>