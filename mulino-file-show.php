<?php
/**
 * Plugin Name:       Mulino file show
 * Description:       Nested-folder document library (e.g. Decade > Year) with drag-and-drop admin upload and a frontend breadcrumb browser shortcode [mfs_documents].
 * Version:           1.0.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Peder Møller
 * License:           GPL v2 or later
 * Text Domain:       mulino-file-show
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MFS_PATH', plugin_dir_path( __FILE__ ) );
define( 'MFS_URL', plugin_dir_url( __FILE__ ) );
define( 'MFS_VERSION', '1.0.1' );

/**
 * Module map, in load order:
 *  - helpers.php      shared file-type/icon helpers (no dependencies)
 *  - post-type.php    the mfs_document post type + mfs_folder taxonomy
 *  - shortcode.php     [mfs_documents] frontend browser
 *  - admin-manager.php the "Mulino file show" admin screen + its AJAX endpoints
 */
require_once MFS_PATH . 'includes/helpers.php';
require_once MFS_PATH . 'includes/post-type.php';
require_once MFS_PATH . 'includes/shortcode.php';
require_once MFS_PATH . 'includes/admin-manager.php';
