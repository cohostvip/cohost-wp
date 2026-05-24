<?php
/**
 * Bootstraps the plugin and wires all the modules together.
 *
 * @package CohostWP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cohost_Plugin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Translations are auto-loaded by WordPress for plugins hosted on
		// wordpress.org since WP 4.6 — no manual load_plugin_textdomain() call needed.

		Cohost_Settings::init();
		Cohost_Shortcodes::init();
		Cohost_Rewrite::init();
		Cohost_Blocks::init();
		Cohost_Templates::init();
	}

	public static function activate() {
		Cohost_Rewrite::add_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}
}
