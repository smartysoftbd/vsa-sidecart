<?php
/**
 * Plugin Name: VSA Side Cart
 * Plugin URI: https://virtualskillsacademy.com
 * Description: Premium Slide-Out Side Cart Drawer for Virtual Skills Academy with seamless Academy LMS & StoreEngine integration.
 * Version: 1.0.1
 * Author: Virtual Skills Academy
 * Author URI: https://virtualskillsacademy.com
 * License: GPL-2.0-or-later
 * Text Domain: vsa-sidecart
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package VSA\SideCart
 */

declare(strict_types=1);

namespace VSA\SideCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VSA_SIDECART_VERSION', '1.1.0' );
define( 'VSA_SIDECART_FILE', __FILE__ );
define( 'VSA_SIDECART_PATH', plugin_dir_path( __FILE__ ) );
define( 'VSA_SIDECART_URL', plugin_dir_url( __FILE__ ) );

// Load classes directly
require_once VSA_SIDECART_PATH . 'includes/class-cart-bridge.php';
require_once VSA_SIDECART_PATH . 'includes/class-ajax-handler.php';
require_once VSA_SIDECART_PATH . 'includes/class-drawer-renderer.php';

/**
 * Main Plugin Initialization Class
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_components();
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	private function init_components(): void {
		// 1. Data bridge to fix Academy LMS + StoreEngine interactions
		Cart_Bridge::get_instance();

		// 2. AJAX Handlers for adding, removing, updating items
		Ajax_Handler::get_instance();

		// 3. Side cart drawer markup and template renderer
		Drawer_Renderer::get_instance();
	}

	public function enqueue_assets(): void {
		if ( is_admin() ) {
			return;
		}

		$css_ver = file_exists( VSA_SIDECART_PATH . 'assets/css/vsa-sidecart.css' )
			? (string) filemtime( VSA_SIDECART_PATH . 'assets/css/vsa-sidecart.css' )
			: VSA_SIDECART_VERSION;
		$js_ver  = file_exists( VSA_SIDECART_PATH . 'assets/js/vsa-sidecart.js' )
			? (string) filemtime( VSA_SIDECART_PATH . 'assets/js/vsa-sidecart.js' )
			: VSA_SIDECART_VERSION;

		wp_enqueue_style(
			'vsa-sidecart',
			VSA_SIDECART_URL . 'assets/css/vsa-sidecart.css',
			[],
			$css_ver
		);

		wp_enqueue_script(
			'vsa-sidecart',
			VSA_SIDECART_URL . 'assets/js/vsa-sidecart.js',
			[ 'jquery' ],
			$js_ver,
			true
		);

		$checkout_url = class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::get_checkout_url() : home_url( '/checkout/' );
		$cart_url     = class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::get_cart_url() : home_url( '/cart/' );

		wp_localize_script(
			'vsa-sidecart',
			'vsaSideCartConfig',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'vsa_sidecart_nonce' ),
				'checkoutUrl' => esc_url( $checkout_url ),
				'cartUrl'     => esc_url( $cart_url ),
				'i18n'        => [
					'adding'        => __( 'Adding...', 'vsa-sidecart' ),
					'added'         => __( 'Added to Cart', 'vsa-sidecart' ),
					'removeConfirm' => __( 'Remove this item from your cart?', 'vsa-sidecart' ),
					'emptyTitle'    => __( 'Your cart is empty', 'vsa-sidecart' ),
					'emptySubtitle' => __( "Looks like you have not added any courses yet.", 'vsa-sidecart' ),
					'browseCourses' => __( 'Explore Courses', 'vsa-sidecart' ),
					'removeError'   => __( 'Could not remove item. Please try again.', 'vsa-sidecart' ),
					'addError'      => __( 'Failed to add item to cart. Please try again.', 'vsa-sidecart' ),
					'apply'         => __( 'Apply', 'vsa-sidecart' ),
					'applying'      => __( 'Applying...', 'vsa-sidecart' ),
					'couponApplied' => __( 'Coupon applied successfully!', 'vsa-sidecart' ),
					'couponRemoved' => __( 'Coupon removed successfully.', 'vsa-sidecart' ),
					'couponEmpty'   => __( 'Please enter a coupon code.', 'vsa-sidecart' ),
					'couponError'   => __( 'Failed to apply coupon. Please try again.', 'vsa-sidecart' ),
				],
			]
		);
	}
}

// Bootstrap plugin on plugins_loaded
add_action( 'plugins_loaded', static function (): void {
	Plugin::get_instance();
}, 25 );
