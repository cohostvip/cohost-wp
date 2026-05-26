<?php
/**
 * Plugin Name:       Cohost
 * Description:       Show your Cohost events on your own WordPress site — your branding, your domain, your audience. Embed the events list anywhere with a shortcode and click through to per-event profile pages.
 * Version:           0.1.2
 * Requires at least: 5.8
 * Requires PHP:      7.0
 * Author:            Cohost
 * Author URI:        https://cohost.vip
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cohost
 * Domain Path:       /languages
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COHOST_WP_VERSION', '0.1.1' );
define( 'COHOST_WP_FILE', __FILE__ );
define( 'COHOST_WP_PATH', plugin_dir_path( __FILE__ ) );
define( 'COHOST_WP_URL', plugin_dir_url( __FILE__ ) );
define( 'COHOST_WP_BASENAME', plugin_basename( __FILE__ ) );

require_once COHOST_WP_PATH . 'includes/class-cohost-api-client.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-settings.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-shortcodes.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-rewrite.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-blocks.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-template-library.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-templates.php';
require_once COHOST_WP_PATH . 'includes/class-cohost-plugin.php';

register_activation_hook( __FILE__, array( 'Cohost_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Cohost_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Cohost_Plugin', 'instance' ) );
