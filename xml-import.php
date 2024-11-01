<?php
/**
 * Plugin Name: XML Import
 * Plugin URI: http://designs.dirlik.nl
 * Description: Import XML Feeds and map result on (custom) posts
 * Version: 1.0.4
 * Author: Simon Dirlik
 * Author URI: http://designs.dirlik.nl
 * Text Domain: xml-import
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */
 
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-xml-import.php';
	
	$xmli = new XML_Import( plugin_dir_path( __FILE__ ) );
	
	add_action( 'init', array( $xmli, 'init' ) );
	add_action( 'admin_print_scripts', array( $xmli, 'scripts' ) );
	add_action( 'admin_print_styles', array( $xmli, 'styles' ) );
	
	add_action( 'save_post', array( $xmli, 'save' ), 10, 3);
	
	add_action( 'plugins_loaded', array( $xmli, 'load_textdomain' ) );

	add_action('wp_ajax_xmli_select_root', array( $xmli, 'select_root_callback' ) );
	add_action('wp_ajax_xmli_select_changed', array( $xmli, 'select_changed' ) );
	add_action('wp_ajax_xmli_get_level', array( $xmli, 'get_level' ) );
	add_action('wp_ajax_xmli_save_map', array( $xmli, 'save_map' ) );
	add_action('wp_ajax_xmli_import_map', array( $xmli, 'import_map' ) );
	add_action('wp_ajax_xmli_download_feed', array( $xmli, 'download_feed' ) );
