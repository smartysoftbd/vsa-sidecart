<?php
/**
 * AJAX Endpoints for VSA Side Cart.
 *
 * Provides dedicated, high-speed, type-safe endpoints for:
 * - Getting cart contents & totals
 * - Adding course/product to cart
 * - Removing item by key (string preservation)
 * - Updating item quantity
 *
 * @package VSA\SideCart
 */

declare(strict_types=1);

namespace VSA\SideCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ajax_Handler {

	private static ?Ajax_Handler $instance = null;

	public static function get_instance(): Ajax_Handler {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Fetch cart
		add_action( 'wp_ajax_vsa_sidecart_get', [ $this, 'ajax_get_cart' ] );
		add_action( 'wp_ajax_nopriv_vsa_sidecart_get', [ $this, 'ajax_get_cart' ] );

		// Add item
		add_action( 'wp_ajax_vsa_sidecart_add', [ $this, 'ajax_add_item' ] );
		add_action( 'wp_ajax_nopriv_vsa_sidecart_add', [ $this, 'ajax_add_item' ] );

		// Remove item
		add_action( 'wp_ajax_vsa_sidecart_remove', [ $this, 'ajax_remove_item' ] );
		add_action( 'wp_ajax_nopriv_vsa_sidecart_remove', [ $this, 'ajax_remove_item' ] );

		// Update quantity
		add_action( 'wp_ajax_vsa_sidecart_update_qty', [ $this, 'ajax_update_qty' ] );
		add_action( 'wp_ajax_nopriv_vsa_sidecart_update_qty', [ $this, 'ajax_update_qty' ] );

		// Apply coupon
		add_action( 'wp_ajax_vsa_sidecart_apply_coupon', [ $this, 'ajax_apply_coupon' ] );
		add_action( 'wp_ajax_nopriv_vsa_sidecart_apply_coupon', [ $this, 'ajax_apply_coupon' ] );

		// Remove coupon
		add_action( 'wp_ajax_vsa_sidecart_remove_coupon', [ $this, 'ajax_remove_coupon' ] );
		add_action( 'wp_ajax_nopriv_vsa_sidecart_remove_coupon', [ $this, 'ajax_remove_coupon' ] );
	}

	/**
	 * Verify nonce.
	 */
	private function verify_security(): void {
		if ( ! check_ajax_referer( 'vsa_sidecart_nonce', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed. Please refresh the page.', 'vsa-sidecart' ) ], 403 );
		}
	}

	/**
	 * Ensure StoreEngine cart is loaded.
	 */
	private function ensure_cart(): ?\StoreEngine\Classes\Cart {
		if ( class_exists( '\StoreEngine' ) ) {
			\StoreEngine::init()->load_cart();
		}
		if ( class_exists( '\StoreEngine\Utils\Helper' ) ) {
			return \StoreEngine\Utils\Helper::cart();
		}
		return null;
	}

	/**
	 * Return standardized cart response payload.
	 */
	private function get_cart_payload(): array {
		$cart = $this->ensure_cart();
		if ( ! $cart ) {
			return [
				'count'          => 0,
				'subtotal_html'  => '$0.00',
				'total_html'     => '$0.00',
				'discount_total' => 0.0,
				'has_discount'   => false,
				'coupons'        => [],
				'items_html'     => Drawer_Renderer::get_instance()->render_empty_cart_html(),
				'totals_html'    => '',
				'is_empty'       => true,
				'checkout_url'   => home_url( '/checkout/' ),
				'cart_url'       => home_url( '/cart/' ),
			];
		}

		$is_empty = $cart->is_cart_empty();
		$count    = $is_empty ? 0 : (int) $cart->get_count();

		$subtotal_html = '';
		if ( method_exists( $cart, 'get_cart_subtotal' ) ) {
			$subtotal_html = $cart->get_cart_subtotal();
		} elseif ( class_exists( '\StoreEngine\Utils\Formatting' ) ) {
			$subtotal_html = \StoreEngine\Utils\Formatting::price( (float) $cart->get_subtotal() );
		} else {
			$subtotal_html = sprintf( '$%.2f', (float) $cart->get_subtotal() );
		}

		$total_html = '';
		if ( method_exists( $cart, 'get_total' ) ) {
			$total_html = $cart->get_total();
		} elseif ( class_exists( '\StoreEngine\Utils\Formatting' ) ) {
			$total_html = \StoreEngine\Utils\Formatting::price( (float) $cart->get_total( 'edit' ) );
		} else {
			$total_html = sprintf( '$%.2f', (float) $cart->get_total( 'edit' ) );
		}

		$discount_total = method_exists( $cart, 'get_discount_total' ) ? (float) $cart->get_discount_total() : 0.0;
		$coupons_obj    = method_exists( $cart, 'get_coupons' ) ? $cart->get_coupons() : [];
		$has_discount   = $discount_total > 0 || ! empty( $coupons_obj );

		$coupons_data = [];
		foreach ( $coupons_obj as $coupon ) {
			if ( ! is_object( $coupon ) ) {
				continue;
			}
			$code = method_exists( $coupon, 'get_code' )
				? (string) $coupon->get_code()
				: ( property_exists( $coupon, 'code' ) ? (string) $coupon->code : '' );

			if ( '' === $code ) {
				continue;
			}
			$amt = method_exists( $cart, 'get_coupon_discount_amount' )
				? (float) $cart->get_coupon_discount_amount( $code, method_exists( $cart, 'display_prices_excluding_tax' ) ? $cart->display_prices_excluding_tax() : true )
				: 0.0;

			$coupons_data[] = [
				'code'     => $code,
				'discount' => $amt,
			];
		}

		$items_html  = $is_empty
			? Drawer_Renderer::get_instance()->render_empty_cart_html()
			: Drawer_Renderer::get_instance()->render_cart_items_html( $cart );

		$totals_html = $is_empty
			? ''
			: Drawer_Renderer::get_instance()->render_totals_html( $cart );

		$checkout_url = class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::get_checkout_url() : home_url( '/checkout/' );
		$cart_url     = class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::get_cart_url() : home_url( '/cart/' );

		return [
			'count'          => $count,
			'subtotal_html'  => $subtotal_html,
			'total_html'     => $total_html,
			'discount_total' => $discount_total,
			'has_discount'   => $has_discount,
			'coupons'        => $coupons_data,
			'items_html'     => $items_html,
			'totals_html'    => $totals_html,
			'is_empty'       => $is_empty,
			'checkout_url'   => esc_url( $checkout_url ),
			'cart_url'       => esc_url( $cart_url ),
		];
	}

	/**
	 * AJAX: Get cart contents.
	 */
	public function ajax_get_cart(): void {
		$this->verify_security();
		wp_send_json_success( $this->get_cart_payload() );
	}

	/**
	 * AJAX: Add item to cart.
	 */
	public function ajax_add_item(): void {
		$this->verify_security();
		$cart = $this->ensure_cart();
		if ( ! $cart ) {
			wp_send_json_error( [ 'message' => __( 'StoreEngine cart not initialized.', 'vsa-sidecart' ) ], 500 );
		}

		$price_id   = isset( $_POST['price_id'] ) ? absint( $_POST['price_id'] ) : 0;
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$course_id  = isset( $_POST['academy_course_id'] ) ? absint( $_POST['academy_course_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, (float) $_POST['quantity'] ) : 1.0;

		if ( ! $price_id ) {
			wp_send_json_error( [ 'message' => __( 'Price ID is required.', 'vsa-sidecart' ) ], 400 );
		}

		$item_data = [];
		if ( $course_id ) {
			$item_data['academy_course_id'] = $course_id;
		}

		$result = $cart->add_product_to_cart( $price_id, $quantity, 0, [], $item_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], 422 );
		}

		wp_send_json_success( array_merge(
			$this->get_cart_payload(),
			[ 'message' => __( 'Item added to cart successfully!', 'vsa-sidecart' ) ]
		) );
	}

	/**
	 * AJAX: Remove item from cart.
	 */
	public function ajax_remove_item(): void {
		$this->verify_security();
		$cart = $this->ensure_cart();
		if ( ! $cart ) {
			wp_send_json_error( [ 'message' => __( 'Cart not available.', 'vsa-sidecart' ) ], 500 );
		}

		// Ensure item_key is handled strictly as a string
		$item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( strval( $_POST['item_key'] ) ) : '';

		if ( empty( $item_key ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid item key.', 'vsa-sidecart' ) ], 400 );
		}

		$item = $cart->get_cart_item( $item_key );
		if ( ! $item ) {
			// Check if key exists in cart_items array directly
			$items = $cart->get_cart_items();
			if ( ! isset( $items[ $item_key ] ) ) {
				wp_send_json_error( [ 'message' => __( 'Cart item not found.', 'vsa-sidecart' ) ], 404 );
			}
		}

		$removed = $cart->remove_cart_item( $item_key );

		if ( ! $removed ) {
			wp_send_json_error( [ 'message' => __( 'Failed to remove item.', 'vsa-sidecart' ) ], 500 );
		}

		wp_send_json_success( array_merge(
			$this->get_cart_payload(),
			[ 'message' => __( 'Item removed from your cart.', 'vsa-sidecart' ) ]
		) );
	}

	/**
	 * AJAX: Update item quantity.
	 */
	public function ajax_update_qty(): void {
		$this->verify_security();
		$cart = $this->ensure_cart();
		if ( ! $cart ) {
			wp_send_json_error( [ 'message' => __( 'Cart not available.', 'vsa-sidecart' ) ], 500 );
		}

		$item_key = isset( $_POST['item_key'] ) ? sanitize_text_field( strval( $_POST['item_key'] ) ) : '';
		$quantity = isset( $_POST['quantity'] ) ? max( 0, (float) $_POST['quantity'] ) : 1.0;

		if ( empty( $item_key ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid item key.', 'vsa-sidecart' ) ], 400 );
		}

		$updated = $cart->update_quantity( $item_key, $quantity );

		if ( is_wp_error( $updated ) ) {
			wp_send_json_error( [ 'message' => $updated->get_error_message() ], 422 );
		}

		wp_send_json_success( $this->get_cart_payload() );
	}

	/**
	 * AJAX: Apply coupon to cart.
	 */
	public function ajax_apply_coupon(): void {
		$this->verify_security();
		$cart = $this->ensure_cart();
		if ( ! $cart ) {
			wp_send_json_error( [ 'message' => __( 'Cart not available.', 'vsa-sidecart' ) ], 500 );
		}

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( strval( $_POST['coupon_code'] ) ) : '';
		$coupon_code = trim( $coupon_code );

		if ( empty( $coupon_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Please enter a coupon code.', 'vsa-sidecart' ) ], 400 );
		}

		if ( $cart->is_cart_empty() ) {
			wp_send_json_error( [ 'message' => __( 'Your cart is empty. Add a course first to apply coupons.', 'vsa-sidecart' ) ], 400 );
		}

		if ( method_exists( $cart, 'is_coupon_applied' ) && $cart->is_coupon_applied( $coupon_code ) ) {
			wp_send_json_error( [ 'message' => sprintf( __( 'Coupon "%s" has already been applied.', 'vsa-sidecart' ), esc_html( strtoupper( $coupon_code ) ) ) ], 400 );
		}

		$applied = $cart->apply_coupon( $coupon_code );

		if ( is_wp_error( $applied ) ) {
			wp_send_json_error( [ 'message' => $applied->get_error_message() ], 422 );
		}

		if ( method_exists( $cart, 'calculate_totals' ) ) {
			$cart->calculate_totals();
		} elseif ( method_exists( $cart, 'calculate_cart_totals' ) ) {
			$cart->calculate_cart_totals();
		}

		if ( method_exists( $cart, 'store_on_database' ) ) {
			$cart->store_on_database();
		}

		wp_send_json_success( array_merge(
			$this->get_cart_payload(),
			[ 'message' => sprintf( __( 'Coupon "%s" applied successfully!', 'vsa-sidecart' ), esc_html( strtoupper( $coupon_code ) ) ) ]
		) );
	}

	/**
	 * AJAX: Remove coupon from cart.
	 */
	public function ajax_remove_coupon(): void {
		$this->verify_security();
		$cart = $this->ensure_cart();
		if ( ! $cart ) {
			wp_send_json_error( [ 'message' => __( 'Cart not available.', 'vsa-sidecart' ) ], 500 );
		}

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( strval( $_POST['coupon_code'] ) ) : '';
		$coupon_code = trim( $coupon_code );

		if ( empty( $coupon_code ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid coupon code.', 'vsa-sidecart' ) ], 400 );
		}

		$removed = $cart->remove_coupon( $coupon_code );

		if ( is_wp_error( $removed ) ) {
			wp_send_json_error( [ 'message' => $removed->get_error_message() ], 422 );
		}

		if ( method_exists( $cart, 'calculate_totals' ) ) {
			$cart->calculate_totals();
		} elseif ( method_exists( $cart, 'calculate_cart_totals' ) ) {
			$cart->calculate_cart_totals();
		}

		if ( method_exists( $cart, 'store_on_database' ) ) {
			$cart->store_on_database();
		}

		wp_send_json_success( array_merge(
			$this->get_cart_payload(),
			[ 'message' => __( 'Coupon removed successfully.', 'vsa-sidecart' ) ]
		) );
	}
}
