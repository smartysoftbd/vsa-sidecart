/**
 * VSA Side Cart - Frontend Interactions
 */
(function ($) {
	'use strict';

	const config = window.vsaSideCartConfig || {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: '',
		checkoutUrl: '/checkout/',
		cartUrl: '/cart/',
		i18n: {}
	};

	const VsaSideCart = {
		drawer: null,
		backdrop: null,
		body: null,
		countBadge: null,
		subtotalEl: null,
		totalsContainer: null,
		couponInput: null,
		couponBtn: null,
		couponMsg: null,
		footer: null,
		floatingCart: null,
		floatingCount: null,
		isOpen: false,

		init() {
			this.drawer          = document.getElementById('vsa-sidecart-drawer');
			this.backdrop        = document.getElementById('vsa-sidecart-backdrop');
			this.body            = document.getElementById('vsa-sidecart-body');
			this.countBadge      = document.getElementById('vsa-sidecart-count');
			this.subtotalEl      = document.getElementById('vsa-sidecart-subtotal');
			this.totalsContainer = document.getElementById('vsa-sidecart-totals-section');
			this.couponInput     = document.getElementById('vsa-coupon-code');
			this.couponBtn       = document.getElementById('vsa-coupon-apply-btn');
			this.couponMsg       = document.getElementById('vsa-coupon-msg');
			this.footer          = document.getElementById('vsa-sidecart-footer');
			this.floatingCart    = document.getElementById('vsa-floating-cart');
			this.floatingCount   = document.getElementById('vsa-floating-cart-count');

			if (!this.drawer) return;

			this.bindEvents();
		},

		bindEvents() {
			const self = this;

			// Close buttons
			const closeBtn = document.getElementById('vsa-sidecart-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', () => self.close());
			}
			if (this.backdrop) {
				this.backdrop.addEventListener('click', () => self.close());
			}

			// Escape key to close
			document.addEventListener('keydown', (e) => {
				if (e.key === 'Escape' && self.isOpen) {
					self.close();
				}
			});

			// Floating cart button click
			if (this.floatingCart) {
				this.floatingCart.addEventListener('click', (e) => {
					e.preventDefault();
					self.open();
					self.refresh();
				});
			}

			// Open triggers across the page
			document.addEventListener('click', (e) => {
				const trigger = e.target.closest(
					'[data-vsa-sidecart-open], .storeengine-mini-cart-drawer-trigger, .storeengine-cart-count, [data-storeengine-mini-cart-open-drawer]'
				);
				if (trigger && !trigger.closest('#vsa-floating-cart')) {
					e.preventDefault();
					self.open();
					self.refresh();
				}
			});

			// Add to Cart interceptor (Academy LMS / StoreEngine forms and buttons)
			$(document).on(
				'click',
				'.storeengine-btn--add-to-cart, .storeengine-btn--direct-checkout, .academy-btn--add-to-cart, .vsa-trigger-cart',
				function (e) {
					const $btn = $(this);
					const $form = $btn.closest('form.storeengine-ajax-add-to-cart-form, form');

					if ($form.length) {
						e.preventDefault();
						e.stopImmediatePropagation();
						self.handleAddToCartForm($form, $btn);
					}
				}
			);

			// Delete Item click
			$(document).on('click', '.vsa-cart-item-delete', function (e) {
				e.preventDefault();
				const itemKey = $(this).attr('data-vsa-key');
				if (itemKey) {
					self.removeItem(itemKey, $(this).closest('.vsa-cart-item-card'));
				}
			});

			// Quantity Steppers
			$(document).on('click', '.vsa-qty-btn', function (e) {
				e.preventDefault();
				const $btn = $(this);
				const $stepper = $btn.closest('.vsa-cart-qty-stepper');
				const itemKey = $stepper.attr('data-item-key');
				const $input = $stepper.find('.vsa-qty-input');
				let currentVal = parseInt($input.val(), 10) || 1;

				if ($btn.hasClass('vsa-qty-plus')) {
					currentVal += 1;
				} else if ($btn.hasClass('vsa-qty-minus')) {
					currentVal = Math.max(1, currentVal - 1);
				}

				$input.val(currentVal);
				self.updateQuantity(itemKey, currentVal);
			});

			// Coupon Apply button click
			$(document).on('click', '#vsa-coupon-apply-btn', function (e) {
				e.preventDefault();
				self.applyCoupon();
			});

			// Coupon Enter key press
			$(document).on('keydown', '#vsa-coupon-code', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					self.applyCoupon();
				}
			});

			$(document).on('input', '#vsa-coupon-code', function () {
				const $msg = $('#vsa-coupon-msg.is-error');
				if ($msg.is(':visible')) {
					$msg.fadeOut(150);
				}
			});

			// Coupon Remove button click
			$(document).on('click', '.vsa-coupon-remove-btn', function (e) {
				e.preventDefault();
				const code = $(this).attr('data-coupon');
				if (code) {
					self.removeCoupon(code, $(this));
				}
			});

			// Sync with StoreEngine events
			window.addEventListener('storeengine_cart_updated', () => {
				self.refresh();
			});
		},

		open() {
			if (!this.drawer) return;
			this.drawer.classList.add('is-open');
			if (this.backdrop) {
				this.backdrop.classList.add('is-active');
			}
			document.body.classList.add('vsa-drawer-open');
			document.body.style.overflow = 'hidden';
			this.isOpen = true;
		},

		close() {
			if (!this.drawer) return;
			this.drawer.classList.remove('is-open');
			if (this.backdrop) {
				this.backdrop.classList.remove('is-active');
			}
			document.body.classList.remove('vsa-drawer-open');
			document.body.style.overflow = '';
			this.isOpen = false;
		},

		handleAddToCartForm($form, $btn) {
			const self = this;
			const priceId = $form.find('input[name="price_id"]:checked, input[name="price_id"][type="hidden"]').val();
			const productId = $form.find('input[name="product_id"]').val();
			const courseId = $form.find('input[name="academy_course_id"]').val();

			const originalText = $btn.text();
			$btn.prop('disabled', true).text(config.i18n.adding || 'Adding...');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'vsa_sidecart_add',
					nonce: config.nonce,
					price_id: priceId,
					product_id: productId,
					academy_course_id: courseId,
					quantity: 1
				},
				success(res) {
					if (res.success && res.data) {
						self.updateDrawerUI(res.data);
						self.open();
						$btn.text(config.i18n.added || 'Added to Cart');
						setTimeout(() => {
							$btn.prop('disabled', false).text(originalText);
						}, 1200);

						$(window).trigger('storeengine_cart_updated');
					} else {
						alert(res.data?.message || config.i18n.addError);
						$btn.prop('disabled', false).text(originalText);
					}
				},
				error(xhr) {
					alert(xhr.responseJSON?.data?.message || config.i18n.addError);
					$btn.prop('disabled', false).text(originalText);
				}
			});
		},

		removeItem(itemKey, $card) {
			const self = this;
			$card.addClass('is-removing');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'vsa_sidecart_remove',
					nonce: config.nonce,
					item_key: itemKey
				},
				success(res) {
					if (res.success && res.data) {
						self.updateDrawerUI(res.data);
						$(window).trigger('storeengine_cart_updated');
					} else {
						$card.removeClass('is-removing');
						alert(res.data?.message || config.i18n.removeError);
					}
				},
				error(xhr) {
					$card.removeClass('is-removing');
					alert(xhr.responseJSON?.data?.message || config.i18n.removeError);
				}
			});
		},

		updateQuantity(itemKey, quantity) {
			const self = this;
			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'vsa_sidecart_update_qty',
					nonce: config.nonce,
					item_key: itemKey,
					quantity: quantity
				},
				success(res) {
					if (res.success && res.data) {
						self.updateDrawerUI(res.data);
						$(window).trigger('storeengine_cart_updated');
					}
				}
			});
		},

		refresh() {
			const self = this;
			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'vsa_sidecart_get',
					nonce: config.nonce
				},
				success(res) {
					if (res.success && res.data) {
						self.updateDrawerUI(res.data);
					}
				}
			});
		},

		applyCoupon() {
			const self = this;
			const $input = $('#vsa-coupon-code');
			const code = ($input.val() || '').trim();
			const $btn = $('#vsa-coupon-apply-btn');

			if (!code) {
				self.showCouponMessage(config.i18n.couponEmpty || 'Please enter a coupon code.', 'error');
				$input.focus();
				return;
			}

			const originalBtnHtml = $btn.html();
			$btn.prop('disabled', true).addClass('is-loading');
			$btn.find('.vsa-coupon-btn-text').text(config.i18n.applying || 'Applying...');

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'vsa_sidecart_apply_coupon',
					nonce: config.nonce,
					coupon_code: code
				},
				success(res) {
					$btn.prop('disabled', false).removeClass('is-loading').html(originalBtnHtml);
					if (res.success && res.data) {
						self.updateDrawerUI(res.data);
						$input.val('');
						self.showCouponMessage(res.data.message || config.i18n.couponApplied || 'Coupon applied!', 'success');
						$(window).trigger('storeengine_cart_updated');
						$(document.body).trigger('storeengine_cart_updated');
					} else {
						const err = res.data?.message || config.i18n.couponError || 'Invalid coupon code.';
						self.showCouponMessage(err, 'error');
					}
				},
				error(xhr) {
					$btn.prop('disabled', false).removeClass('is-loading').html(originalBtnHtml);
					const err = xhr.responseJSON?.data?.message || config.i18n.couponError || 'Failed to apply coupon.';
					self.showCouponMessage(err, 'error');
				}
			});
		},

		removeCoupon(code, $btn) {
			const self = this;
			if ($btn) {
				$btn.prop('disabled', true).css('opacity', '0.5');
			}

			$.ajax({
				url: config.ajaxUrl,
				type: 'POST',
				dataType: 'json',
				data: {
					action: 'vsa_sidecart_remove_coupon',
					nonce: config.nonce,
					coupon_code: code
				},
				success(res) {
					if (res.success && res.data) {
						self.updateDrawerUI(res.data);
						self.showCouponMessage(res.data.message || config.i18n.couponRemoved || 'Coupon removed.', 'success');
						$(window).trigger('storeengine_cart_updated');
						$(document.body).trigger('storeengine_cart_updated');
					} else {
						if ($btn) $btn.prop('disabled', false).css('opacity', '1');
						self.showCouponMessage(res.data?.message || 'Failed to remove coupon.', 'error');
					}
				},
				error(xhr) {
					if ($btn) $btn.prop('disabled', false).css('opacity', '1');
					self.showCouponMessage(xhr.responseJSON?.data?.message || 'Failed to remove coupon.', 'error');
				}
			});
		},

		showCouponMessage(text, type = 'success') {
			const $msg = $('#vsa-coupon-msg');
			if (!$msg.length) return;

			const iconSvg = type === 'success'
				? '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
				: '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';

			$msg.removeClass('is-success is-error')
				.addClass(type === 'success' ? 'is-success' : 'is-error')
				.html('<span class="vsa-coupon-msg-icon">' + iconSvg + '</span><span class="vsa-coupon-msg-text">' + text + '</span>')
				.stop(true, true)
				.fadeIn(200);

			if (type === 'success') {
				setTimeout(() => {
					$msg.fadeOut(300);
				}, 4500);
			}
		},

		updateDrawerUI(data) {
			const count = parseInt(data.count, 10) || 0;
			const isEmpty = Boolean(data.is_empty) || count === 0;

			if (this.body && data.items_html) {
				this.body.innerHTML = data.items_html;
			}
			if (this.countBadge) {
				this.countBadge.textContent = count;
			}

			// Update totals table (Subtotal, Discount in green, Divider, Total)
			const totalsSection = document.getElementById('vsa-sidecart-totals-section');
			if (totalsSection && data.totals_html !== undefined) {
				totalsSection.innerHTML = data.totals_html;
			} else {
				if (this.subtotalEl && data.subtotal_html) {
					this.subtotalEl.innerHTML = data.subtotal_html;
				}
				const totalEl = document.getElementById('vsa-sidecart-total');
				if (totalEl && data.total_html) {
					totalEl.innerHTML = data.total_html;
				}
			}

			if (this.footer) {
				if (isEmpty) {
					this.footer.classList.add('is-hidden');
				} else {
					this.footer.classList.remove('is-hidden');
				}
			}

			// Update floating tray button visibility & count
			if (this.floatingCount) {
				this.floatingCount.textContent = count > 99 ? '99+' : count;
			}
			if (this.floatingCart) {
				if (!isEmpty && count > 0) {
					this.floatingCart.classList.add('is-visible');
				} else {
					this.floatingCart.classList.remove('is-visible');
				}
			}

			// Update any floating badge count on the page
			document.querySelectorAll('.storeengine-cart-count, .vsa-cart-count').forEach((el) => {
				el.textContent = count > 99 ? '99+' : count;
			});
		}
	};

	$(document).ready(function () {
		VsaSideCart.init();
	});

})(jQuery);
