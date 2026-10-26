<?php
/**
 * Side Cart Drawer Renderer.
 *
 * Renders the off-canvas drawer structure, empty cart states,
 * and cart item cards in wp_footer.
 *
 * @package VSA\SideCart
 */

declare(strict_types=1);

namespace VSA\SideCart;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Drawer_Renderer {

	private static ?Drawer_Renderer $instance = null;

	public static function get_instance(): Drawer_Renderer {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_footer', [ $this, 'render_drawer_container' ], 99 );
	}

	/**
	 * Output the drawer HTML, overlay, and floating tray button in wp_footer.
	 */
	public function render_drawer_container(): void {
		$cart = null;
		if ( class_exists( '\StoreEngine' ) ) {
			\StoreEngine::init()->load_cart();
		}
		if ( class_exists( '\StoreEngine\Utils\Helper' ) ) {
			$cart = \StoreEngine\Utils\Helper::cart();
		}

		$count    = $cart ? (int) $cart->get_count() : 0;
		$is_empty = ! $cart || $cart->is_cart_empty() || 0 === $count;

		$subtotal_raw  = $cart ? (float) $cart->get_subtotal() : 0.0;
		$subtotal_html = class_exists( '\StoreEngine\Utils\Formatting' )
			? \StoreEngine\Utils\Formatting::price( $subtotal_raw )
			: sprintf( '$%.2f', $subtotal_raw );

		$checkout_url  = class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::get_checkout_url() : home_url( '/checkout/' );
		$cart_url      = class_exists( '\StoreEngine\Utils\Helper' ) ? \StoreEngine\Utils\Helper::get_cart_url() : home_url( '/cart/' );
		?>
		<!-- Floating Cart Tray Button (only shown when cart has items) -->
		<button type="button" id="vsa-floating-cart" class="vsa-floating-cart storeengine-floating-cart <?php echo ( ! $is_empty && $count > 0 ) ? 'is-visible' : ''; ?>" aria-label="<?php esc_attr_e( 'View cart', 'vsa-sidecart' ); ?>">
			<span class="vsa-floating-cart-inner">
				<svg class="vsa-floating-cart-svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="9" cy="21" r="1"></circle>
					<circle cx="20" cy="21" r="1"></circle>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
				</svg>
				<span id="vsa-floating-cart-count" class="vsa-floating-cart-count storeengine-cart-count"><?php echo esc_html( $count > 99 ? '99+' : (string) $count ); ?></span>
			</span>
		</button>

		<div id="vsa-sidecart-backdrop" class="vsa-sidecart-backdrop" aria-hidden="true"></div>

		<aside id="vsa-sidecart-drawer" class="vsa-sidecart-drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Shopping Cart', 'vsa-sidecart' ); ?>">
			<!-- Header -->
			<div class="vsa-sidecart-header">
				<div class="vsa-sidecart-title-wrap">
					<h3 class="vsa-sidecart-title"><?php esc_html_e( 'Your Cart', 'vsa-sidecart' ); ?></h3>
					<span class="vsa-sidecart-count-badge" id="vsa-sidecart-count"><?php echo esc_html( (string) $count ); ?></span>
				</div>
				<button type="button" class="vsa-sidecart-close" id="vsa-sidecart-close" aria-label="<?php esc_attr_e( 'Close cart', 'vsa-sidecart' ); ?>">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</button>
			</div>

			<!-- Body / Items Container -->
			<div class="vsa-sidecart-body" id="vsa-sidecart-body">
				<?php
				if ( $is_empty ) {
					echo $this->render_empty_cart_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} else {
					echo $this->render_cart_items_html( $cart ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				}
				?>
			</div>

			<!-- Footer -->
			<div class="vsa-sidecart-footer <?php echo $is_empty ? 'is-hidden' : ''; ?>" id="vsa-sidecart-footer">
				<!-- Coupon Input Section -->
				<div class="vsa-sidecart-coupon-section" id="vsa-sidecart-coupon-section">
					<?php echo $this->render_coupon_form_html( $cart ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>

				<!-- Totals Summary Section (Subtotal, Discount in green, Total) -->
				<div class="vsa-sidecart-totals-section" id="vsa-sidecart-totals-section">
					<?php echo $this->render_totals_html( $cart ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<p class="vsa-sidecart-notice">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" class="vsa-notice-icon">
						<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
						<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
					</svg>
					<span><?php esc_html_e( 'Instant enrollment access granted upon checkout.', 'vsa-sidecart' ); ?></span>
				</p>
				<div class="vsa-sidecart-actions">
					<a href="<?php echo esc_url( $checkout_url ); ?>" class="vsa-sidecart-btn vsa-sidecart-btn-primary" id="vsa-sidecart-checkout-btn">
						<span><?php esc_html_e( 'Proceed to Checkout', 'vsa-sidecart' ); ?></span>
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
							<line x1="5" y1="12" x2="19" y2="12"></line>
							<polyline points="12 5 19 12 12 19"></polyline>
						</svg>
					</a>
				</div>
			</div>
		</aside>
		<?php
	}

	/**
	 * Render HTML for the cart items list.
	 *
	 * @param \StoreEngine\Classes\Cart $cart
	 * @return string
	 */
	public function render_cart_items_html( $cart ): string {
		$items = $cart->get_cart_items();
		if ( empty( $items ) ) {
			return $this->render_empty_cart_html();
		}

		ob_start();
		?>
		<div class="vsa-sidecart-items-list">
			<?php
			foreach ( $items as $key => $item ) :
				$course_id = ! empty( $item->item_data['academy_course_id'] ) ? absint( $item->item_data['academy_course_id'] ) : 0;
				$post_id   = $course_id ?: $item->product_id;

				$item_title = $course_id ? get_the_title( $course_id ) : ( $item->name ?? get_the_title( $item->product_id ) );
				$item_link  = $course_id ? get_the_permalink( $course_id ) : get_the_permalink( $item->product_id );

				$thumbnail_url = '';
				if ( has_post_thumbnail( $post_id ) ) {
					$thumbnail_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
				} elseif ( $course_id && has_post_thumbnail( $item->product_id ) ) {
					$thumbnail_url = get_the_post_thumbnail_url( $item->product_id, 'thumbnail' );
				}

				$line_price = $item->line_subtotal ?? ( (float) $item->price * (float) $item->quantity );
				$price_html = class_exists( '\StoreEngine\Utils\Formatting' )
					? \StoreEngine\Utils\Formatting::price( (float) $line_price )
					: sprintf( '$%.2f', $line_price );
				?>
				<div class="vsa-cart-item-card" data-item-key="<?php echo esc_attr( (string) $key ); ?>">
					<!-- Thumbnail -->
					<div class="vsa-cart-item-thumb">
						<a href="<?php echo esc_url( $item_link ); ?>" tabindex="-1">
							<?php if ( $thumbnail_url ) : ?>
								<img src="<?php echo esc_url( $thumbnail_url ); ?>" alt="<?php echo esc_attr( $item_title ); ?>" loading="lazy" />
							<?php else : ?>
								<div class="vsa-cart-item-thumb-placeholder">
									<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
										<path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
									</svg>
								</div>
							<?php endif; ?>
						</a>
					</div>

					<!-- Details -->
					<div class="vsa-cart-item-details">
						<div class="vsa-cart-item-top">
							<a href="<?php echo esc_url( $item_link ); ?>" class="vsa-cart-item-title">
								<?php echo esc_html( $item_title ); ?>
							</a>
							<button type="button" class="vsa-cart-item-delete" data-vsa-key="<?php echo esc_attr( (string) $key ); ?>" aria-label="<?php esc_attr_e( 'Remove item', 'vsa-sidecart' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="3 6 5 6 21 6"></polyline>
									<path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
								</svg>
							</button>
						</div>

						<div class="vsa-cart-item-bottom">
							<!-- Quantity Stepper -->
							<div class="vsa-cart-qty-stepper" data-item-key="<?php echo esc_attr( (string) $key ); ?>">
								<button type="button" class="vsa-qty-btn vsa-qty-minus" aria-label="<?php esc_attr_e( 'Decrease quantity', 'vsa-sidecart' ); ?>">−</button>
								<input type="number" class="vsa-qty-input" value="<?php echo esc_attr( (string) $item->quantity ); ?>" min="1" max="99" readonly />
								<button type="button" class="vsa-qty-btn vsa-qty-plus" aria-label="<?php esc_attr_e( 'Increase quantity', 'vsa-sidecart' ); ?>">+</button>
							</div>

							<!-- Price -->
							<div class="vsa-cart-item-price">
								<?php echo $price_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render empty cart state HTML.
	 *
	 * @return string
	 */
	public function render_empty_cart_html(): string {
		$courses_url = home_url( '/courses/' );
		ob_start();
		?>
		<div class="vsa-sidecart-empty">
			<div class="vsa-sidecart-empty-icon">
				<svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
					<circle cx="9" cy="21" r="1"></circle>
					<circle cx="20" cy="21" r="1"></circle>
					<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
				</svg>
			</div>
			<h4 class="vsa-sidecart-empty-title"><?php esc_html_e( 'Your cart is empty', 'vsa-sidecart' ); ?></h4>
			<p class="vsa-sidecart-empty-subtitle"><?php esc_html_e( 'Discover high-impact digital and creative skills courses to jumpstart your career.', 'vsa-sidecart' ); ?></p>
			<a href="<?php echo esc_url( $courses_url ); ?>" class="vsa-sidecart-browse-btn">
				<?php esc_html_e( 'Explore Courses', 'vsa-sidecart' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render coupon input form HTML.
	 *
	 * @param \StoreEngine\Classes\Cart|null $cart Cart instance.
	 * @return string
	 */
	public function render_coupon_form_html( $cart = null ): string {
		ob_start();
		?>
		<div class="vsa-sidecart-coupon-wrap" id="vsa-sidecart-coupon-wrap">
			<form class="vsa-coupon-form" id="vsa-coupon-form" onsubmit="return false;">
				<div class="vsa-coupon-input-group">
					<span class="vsa-coupon-input-icon" aria-hidden="true">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
							<line x1="7" y1="7" x2="7.01" y2="7"></line>
						</svg>
					</span>
					<input type="text"
						id="vsa-coupon-code"
						name="vsa_coupon_code"
						class="vsa-coupon-input"
						placeholder="<?php esc_attr_e( 'Coupon Code', 'vsa-sidecart' ); ?>"
						aria-label="<?php esc_attr_e( 'Coupon Code', 'vsa-sidecart' ); ?>"
						autocomplete="off" />
					<button type="button"
						id="vsa-coupon-apply-btn"
						class="vsa-coupon-apply-btn">
						<span class="vsa-coupon-btn-text"><?php esc_html_e( 'Apply', 'vsa-sidecart' ); ?></span>
					</button>
				</div>
				<div id="vsa-coupon-msg" class="vsa-coupon-msg" style="display:none;" role="alert"></div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render totals summary (Subtotal, Discount in green, Divider, Total).
	 *
	 * @param \StoreEngine\Classes\Cart|null $cart Cart instance.
	 * @return string
	 */
	public function render_totals_html( $cart = null ): string {
		if ( ! $cart ) {
			return '';
		}

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
		$coupons        = method_exists( $cart, 'get_coupons' ) ? $cart->get_coupons() : [];
		$has_discount   = $discount_total > 0 || ! empty( $coupons );

		ob_start();
		?>
		<div class="vsa-sidecart-totals-table">
			<!-- Subtotal Row -->
			<div class="vsa-sidecart-row vsa-subtotal-row">
				<span class="vsa-sidecart-row-label"><?php esc_html_e( 'Subtotal', 'vsa-sidecart' ); ?></span>
				<span class="vsa-sidecart-row-value vsa-subtotal-value" id="vsa-sidecart-subtotal"><?php echo $subtotal_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>

			<!-- Applied Coupon / Discount Row(s) (Green Color!) -->
			<?php if ( $has_discount && ! empty( $coupons ) ) : ?>
				<?php
				foreach ( $coupons as $coupon ) :
					$code = method_exists( $coupon, 'get_code' )
						? (string) $coupon->get_code()
						: ( property_exists( $coupon, 'code' ) ? (string) $coupon->code : '' );

					if ( '' === $code ) {
						continue;
					}

					$coupon_amount = method_exists( $cart, 'get_coupon_discount_amount' )
						? (float) $cart->get_coupon_discount_amount( $code, method_exists( $cart, 'display_prices_excluding_tax' ) ? $cart->display_prices_excluding_tax() : true )
						: 0.0;

					if ( method_exists( $coupon, 'get_free_shipping' ) && $coupon->get_free_shipping() && empty( $coupon_amount ) ) {
						$amount_display = __( 'Free Shipping', 'vsa-sidecart' );
					} else {
						$formatted_amt  = class_exists( '\StoreEngine\Utils\Formatting' )
							? \StoreEngine\Utils\Formatting::price( $coupon_amount )
							: sprintf( '$%.2f', $coupon_amount );
						$amount_display = '-' . $formatted_amt;
					}
					?>
					<div class="vsa-sidecart-row vsa-discount-row" data-coupon="<?php echo esc_attr( $code ); ?>">
						<div class="vsa-sidecart-row-label vsa-discount-label">
							<span><?php esc_html_e( 'Discount', 'vsa-sidecart' ); ?></span>
							<span class="vsa-coupon-pill">
								<svg class="vsa-coupon-pill-icon" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
									<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
									<line x1="7" y1="7" x2="7.01" y2="7"></line>
								</svg>
								<span class="vsa-coupon-pill-code"><?php echo esc_html( strtoupper( $code ) ); ?></span>
								<button type="button"
									class="vsa-coupon-remove-btn"
									data-coupon="<?php echo esc_attr( $code ); ?>"
									aria-label="<?php echo esc_attr( sprintf( __( 'Remove coupon %s', 'vsa-sidecart' ), $code ) ); ?>"
									title="<?php esc_attr_e( 'Remove coupon', 'vsa-sidecart' ); ?>">
									&times;
								</button>
							</span>
						</div>
						<span class="vsa-sidecart-row-value vsa-discount-value">
							<?php echo $amount_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</span>
					</div>
				<?php endforeach; ?>
			<?php elseif ( $discount_total > 0 ) :
				$discount_display = '-' . ( class_exists( '\StoreEngine\Utils\Formatting' )
					? \StoreEngine\Utils\Formatting::price( $discount_total )
					: sprintf( '$%.2f', $discount_total ) );
				?>
				<div class="vsa-sidecart-row vsa-discount-row">
					<span class="vsa-sidecart-row-label vsa-discount-label"><?php esc_html_e( 'Discount', 'vsa-sidecart' ); ?></span>
					<span class="vsa-sidecart-row-value vsa-discount-value">
						<?php echo $discount_display; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</span>
				</div>
			<?php endif; ?>

			<!-- Divider Line -->
			<div class="vsa-sidecart-totals-divider"></div>

			<!-- Total Row -->
			<div class="vsa-sidecart-row vsa-total-row">
				<span class="vsa-sidecart-row-label vsa-total-label"><?php esc_html_e( 'Total', 'vsa-sidecart' ); ?></span>
				<span class="vsa-sidecart-row-value vsa-total-value" id="vsa-sidecart-total"><?php echo $total_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
