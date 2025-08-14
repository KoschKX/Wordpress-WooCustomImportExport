<?php
/**
 * Blank Slate
 *
 * @package           wooimportproductdates
 * @author            Gary Angelone
 * @copyright         Copyright 2019-2020 by Gary Angelone - All rights reserved.
 * @license           GPL2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Woo Import Export
 * Plugin URI:
 * Description:       Custom CSV Importers and Exporters.
 * Version:           1.2.1
 * Requires PHP:      5.3
 * Requires at least: 4.7
 * Author:            Gary Angelone
 * Author URI:
 * Text Domain:       wooimportproductdates
 * Domain Path:       /languages
 * License:           GPL V2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */


define( 'WOOIMPORTEXPORT_DIR', __dir__ );
define( 'WOOIMPORTEXPORT_URI', plugin_dir_url( __FILE__ ));

require WOOIMPORTEXPORT_DIR . '/includes/terms_manager.php';
require WOOIMPORTEXPORT_DIR . '/includes/filters.php';
require WOOIMPORTEXPORT_DIR . '/functions.php';
