<?php
/**
 * Cart Bridge for Academy LMS & StoreEngine.
 *
 * Ensures data integrity between Academy courses and StoreEngine cart/orders,
 * fixes REST API item_data dropping, and syncs user session carts.
 *
 * @package VSA\SideCart
 */

declare(strict_types=1);

namespace VSA\SideCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Cart_Bridge {

	private static ?Cart_Bridge $instance = null;

	public static function get_instance(): Cart_Bridge {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// 1. Capture academy_course_id during StoreEngine REST API add-to-cart
		add_filter( 'storeengine/api/cart/item_data', [ $this, 'filter_api_cart_item_data' ], 10, 2 );

		// 2. Capture academy_course_id during standard/AJAX StoreEngine add-to-cart
		add_filter( 'storeengine/cart/add_item_data', [ $this, 'filter_cart_add_item_data' ], 5, 1 );

		// 3. Ensure course title, image, and permalink resolve properly
		add_filter( 'storeengine/cart/item_name', [ $this, 'filter_cart_item_name' ], 15, 2 );
		add_filter( 'storeengine/cart/item_image_post_id', [ $this, 'filter_cart_item_image' ], 15, 2 );
		add_filter( 'storeengine/cart/item_permalink', [ $this, 'filter_cart_item_permalink' ], 15, 2 );

		// 4. Synchronize StoreEngine cart with authenticated user on REST API calls
		add_filter( 'rest_pre_dispatch', [ $this, 'sync_cart_session_on_rest' ], 10, 3 );

		// 5. Disable default StoreEngine floating cart button & default mini-cart drawer root
		add_filter( 'storeengine/frontend/show_floating_cart', '__return_false' );
	}

	/**
	 * Attach academy_course_id from the REST request into StoreEngine's item_data.
	 *
	 * @param array            $item_data Existing item data.
	 * @param \WP_REST_Request $request   The REST request.
	 * @return array
	 */
	public function filter_api_cart_item_data( array $item_data, \WP_REST_Request $request ): array {
		$course_id = $request->get_param( 'academy_course_id' );
		if ( ! empty( $course_id ) ) {
			$item_data['academy_course_id'] = absint( $course_id );
		}
		return $item_data;
	}

	/**
	 * Attach academy_course_id from $_REQUEST into StoreEngine's item_data.
	 *
	 * @param array $item_data Existing item data.
	 * @return array
	 */
	public function filter_cart_add_item_data( array $item_data ): array {
		if ( empty( $item_data['academy_course_id'] ) && ! empty( $_REQUEST['academy_course_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$item_data['academy_course_id'] = absint( wp_unslash( $_REQUEST['academy_course_id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		return $item_data;
	}

	/**
	 * Use course title instead of product title if academy_course_id is present.
	 *
	 * @param string $name      Product name.
	 * @param object $cart_item Cart item object.
	 * @return string
	 */
	public function filter_cart_item_name( string $name, $cart_item ): string {
		$course_id = $this->get_course_id_from_cart_item( $cart_item );
		if ( $course_id ) {
			$title = get_the_title( $course_id );
			if ( ! empty( $title ) ) {
				return $title;
			}
		}
		return $name;
	}

	/**
	 * Use course thumbnail post ID if academy_course_id is present.
	 *
	 * @param int    $product_id Product ID.
	 * @param object $cart_item  Cart item object.
	 * @return int
	 */
	public function filter_cart_item_image( int $product_id, $cart_item ): int {
		$course_id = $this->get_course_id_from_cart_item( $cart_item );
		if ( $course_id && has_post_thumbnail( $course_id ) ) {
			return $course_id;
		}
		return $product_id;
	}

	/**
	 * Use course permalink if academy_course_id is present.
	 *
	 * @param string $permalink Product permalink.
	 * @param object $cart_item Cart item object.
	 * @return string
	 */
	public function filter_cart_item_permalink( string $permalink, $cart_item ): string {
		$course_id = $this->get_course_id_from_cart_item( $cart_item );
		if ( $course_id ) {
			$link = get_the_permalink( $course_id );
			if ( ! empty( $link ) ) {
				return $link;
			}
		}
		return $permalink;
	}

	/**
	 * Resolve academy_course_id from cart item.
	 *
	 * @param object $cart_item
	 * @return int
	 */
	private function get_course_id_from_cart_item( $cart_item ): int {
		if ( ! is_object( $cart_item ) ) {
			return 0;
		}
		if ( ! empty( $cart_item->item_data ) && is_array( $cart_item->item_data ) && ! empty( $cart_item->item_data['academy_course_id'] ) ) {
			return absint( $cart_item->item_data['academy_course_id'] );
		}
		return 0;
	}

	/**
	 * Sync StoreEngine customer/cart singleton during REST requests.
	 * Fixes the bug where premature load_cart() during rest_api_init locks to user_id = 0.
	 */
	public function sync_cart_session_on_rest( $result, $server, $request ) {
		$user_id = get_current_user_id();
		if ( $user_id && class_exists( '\StoreEngine' ) ) {
			$storeengine = \StoreEngine::init();
			$customer    = $storeengine->get_customer();

			if ( ! $customer || $customer->get_id() !== $user_id ) {
				if ( class_exists( '\StoreEngine\Classes\Customer' ) ) {
					$storeengine->customer = new \StoreEngine\Classes\Customer( $user_id, true );
				}
				if ( class_exists( '\StoreEngine\Classes\Cart' ) ) {
					$storeengine->cart = \StoreEngine\Classes\Cart::init();
				}
			}
		}
		return $result;
	}
}
