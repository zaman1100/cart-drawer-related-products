<?php
/**
 * Plugin Name:       Cart Drawer Related Products for WooCommerce
 * Plugin URI:        https://github.com/zaman1100/cart-drawer-related-products
 * Description:       Shows related products, upsells or cross-sells inside the WooCommerce mini cart / cart drawer, with one-click AJAX add to cart. Works with any theme that uses the standard mini cart (WoodMart, Flatsome, Astra, Storefront and more).
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            Haidar Zaman
 * Author URI:        https://github.com/zaman1100
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cart-drawer-related-products
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'CDRP_VERSION', '1.0.0' );
define( 'CDRP_URL', plugin_dir_url( __FILE__ ) );
define( 'CDRP_OPTION', 'cdrp_settings' );

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Default settings.
 *
 * @return array
 */
function cdrp_defaults() {
	return array(
		'enabled' => 'yes',
		'title'   => __( 'You may also like', 'cart-drawer-related-products' ),
		'limit'   => 3,
		'source'  => 'related',
		'hook'    => 'woocommerce_after_mini_cart',
	);
}

/**
 * Get settings merged with defaults.
 *
 * @return array
 */
function cdrp_get_settings() {
	$saved = get_option( CDRP_OPTION, array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), cdrp_defaults() );
}

/**
 * Allowed output positions inside the mini cart.
 *
 * @return array hook => label
 */
function cdrp_hook_choices() {
	return array(
		'woocommerce_after_mini_cart'                  => __( 'After the mini cart (default)', 'cart-drawer-related-products' ),
		'woocommerce_widget_shopping_cart_before_buttons' => __( 'Before the View cart / Checkout buttons', 'cart-drawer-related-products' ),
		'woocommerce_widget_shopping_cart_after_buttons'  => __( 'After the View cart / Checkout buttons', 'cart-drawer-related-products' ),
	);
}

/**
 * Register the output hook chosen in settings.
 */
function cdrp_register_output_hook() {
	$settings = cdrp_get_settings();
	if ( 'yes' !== $settings['enabled'] ) {
		return;
	}
	$hook = array_key_exists( $settings['hook'], cdrp_hook_choices() ) ? $settings['hook'] : 'woocommerce_after_mini_cart';
	add_action( $hook, 'cdrp_render' );
}
add_action( 'init', 'cdrp_register_output_hook' );

/**
 * Load the stylesheet.
 */
function cdrp_enqueue_assets() {
	if ( 'yes' !== cdrp_get_settings()['enabled'] ) {
		return;
	}
	wp_enqueue_style( 'cdrp', CDRP_URL . 'assets/style.css', array(), CDRP_VERSION );
}
add_action( 'wp_enqueue_scripts', 'cdrp_enqueue_assets' );

/**
 * Collect product IDs to suggest for the current cart.
 *
 * @param array $settings Plugin settings.
 * @return int[]
 */
function cdrp_get_product_ids( $settings ) {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
		return array();
	}

	$limit   = max( 1, absint( $settings['limit'] ) );
	$in_cart = array();
	$found   = array();

	foreach ( WC()->cart->get_cart() as $item ) {
		$in_cart[ (int) $item['product_id'] ] = true;
		if ( ! empty( $item['variation_id'] ) ) {
			$in_cart[ (int) $item['variation_id'] ] = true;
		}
	}

	foreach ( WC()->cart->get_cart() as $item ) {
		$product = wc_get_product( $item['product_id'] );
		if ( ! $product ) {
			continue;
		}

		switch ( $settings['source'] ) {
			case 'upsells':
				$ids = $product->get_upsell_ids();
				break;
			case 'cross_sells':
				$ids = $product->get_cross_sell_ids();
				break;
			default:
				$ids = wc_get_related_products( $product->get_id(), $limit + count( $in_cart ) );
		}

		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( isset( $in_cart[ $id ] ) || isset( $found[ $id ] ) ) {
				continue;
			}
			$candidate = wc_get_product( $id );
			if ( ! $candidate || ! $candidate->is_visible() || ! $candidate->is_in_stock() ) {
				continue;
			}
			$found[ $id ] = true;
			if ( count( $found ) >= $limit ) {
				break 2;
			}
		}
	}

	return array_keys( $found );
}

/**
 * Print the suggestions block.
 */
function cdrp_render() {
	$settings = cdrp_get_settings();
	$ids      = apply_filters( 'cdrp_product_ids', cdrp_get_product_ids( $settings ), $settings );

	if ( empty( $ids ) ) {
		return;
	}

	echo '<div class="cdrp-related">';
	if ( '' !== trim( $settings['title'] ) ) {
		echo '<p class="cdrp-related__title">' . esc_html( $settings['title'] ) . '</p>';
	}
	echo '<ul class="cdrp-related__list">';

	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product ) {
			continue;
		}

		$link = $product->get_permalink();

		echo '<li class="cdrp-item">';
		echo '<a class="cdrp-item__thumb" href="' . esc_url( $link ) . '">' . wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail' ) ) . '</a>';
		echo '<div class="cdrp-item__info">';
		echo '<a class="cdrp-item__name" href="' . esc_url( $link ) . '">' . esc_html( $product->get_name() ) . '</a>';
		echo '<span class="cdrp-item__price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
		echo '</div>';

		if ( $product->is_purchasable() && $product->supports( 'ajax_add_to_cart' ) ) {
			printf(
				'<a href="%1$s" data-quantity="1" class="cdrp-item__button button add_to_cart_button ajax_add_to_cart" data-product_id="%2$d" data-product_sku="%3$s" aria-label="%4$s" rel="nofollow">%5$s</a>',
				esc_url( $product->add_to_cart_url() ),
				(int) $product->get_id(),
				esc_attr( $product->get_sku() ),
				/* translators: %s: product name */
				esc_attr( sprintf( __( 'Add "%s" to your cart', 'cart-drawer-related-products' ), $product->get_name() ) ),
				esc_html__( 'Add', 'cart-drawer-related-products' )
			);
		} else {
			printf(
				'<a href="%1$s" class="cdrp-item__button button">%2$s</a>',
				esc_url( $link ),
				esc_html__( 'Options', 'cart-drawer-related-products' )
			);
		}

		echo '</li>';
	}

	echo '</ul></div>';
}

/* ------------------------------------------------------------------------
 * Settings page: WooCommerce > Cart Drawer Related
 * --------------------------------------------------------------------- */

function cdrp_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Cart Drawer Related Products', 'cart-drawer-related-products' ),
		__( 'Cart Drawer Related', 'cart-drawer-related-products' ),
		'manage_woocommerce',
		'cdrp-settings',
		'cdrp_settings_page'
	);
}
add_action( 'admin_menu', 'cdrp_admin_menu' );

function cdrp_register_settings() {
	register_setting(
		'cdrp_group',
		CDRP_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'cdrp_sanitize_settings',
			'default'           => cdrp_defaults(),
		)
	);
}
add_action( 'admin_init', 'cdrp_register_settings' );

/**
 * Sanitize submitted settings.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function cdrp_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = cdrp_defaults();

	return array(
		'enabled' => ( isset( $input['enabled'] ) && 'yes' === $input['enabled'] ) ? 'yes' : 'no',
		'title'   => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $defaults['title'],
		'limit'   => isset( $input['limit'] ) ? min( 12, max( 1, absint( $input['limit'] ) ) ) : $defaults['limit'],
		'source'  => ( isset( $input['source'] ) && in_array( $input['source'], array( 'related', 'upsells', 'cross_sells' ), true ) ) ? $input['source'] : $defaults['source'],
		'hook'    => ( isset( $input['hook'] ) && array_key_exists( $input['hook'], cdrp_hook_choices() ) ) ? $input['hook'] : $defaults['hook'],
	);
}

function cdrp_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$s = cdrp_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cart Drawer Related Products', 'cart-drawer-related-products' ); ?></h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'cdrp_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Enable', 'cart-drawer-related-products' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( CDRP_OPTION ); ?>[enabled]" value="yes" <?php checked( $s['enabled'], 'yes' ); ?> /> <?php esc_html_e( 'Show suggestions in the mini cart', 'cart-drawer-related-products' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="cdrp-title"><?php esc_html_e( 'Heading', 'cart-drawer-related-products' ); ?></label></th>
					<td><input id="cdrp-title" type="text" class="regular-text" name="<?php echo esc_attr( CDRP_OPTION ); ?>[title]" value="<?php echo esc_attr( $s['title'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="cdrp-limit"><?php esc_html_e( 'Number of products', 'cart-drawer-related-products' ); ?></label></th>
					<td><input id="cdrp-limit" type="number" min="1" max="12" name="<?php echo esc_attr( CDRP_OPTION ); ?>[limit]" value="<?php echo esc_attr( $s['limit'] ); ?>" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="cdrp-source"><?php esc_html_e( 'Suggest', 'cart-drawer-related-products' ); ?></label></th>
					<td>
						<select id="cdrp-source" name="<?php echo esc_attr( CDRP_OPTION ); ?>[source]">
							<option value="related" <?php selected( $s['source'], 'related' ); ?>><?php esc_html_e( 'Related products (same category/tags)', 'cart-drawer-related-products' ); ?></option>
							<option value="upsells" <?php selected( $s['source'], 'upsells' ); ?>><?php esc_html_e( 'Upsells (set on each product)', 'cart-drawer-related-products' ); ?></option>
							<option value="cross_sells" <?php selected( $s['source'], 'cross_sells' ); ?>><?php esc_html_e( 'Cross-sells (set on each product)', 'cart-drawer-related-products' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cdrp-hook"><?php esc_html_e( 'Position', 'cart-drawer-related-products' ); ?></label></th>
					<td>
						<select id="cdrp-hook" name="<?php echo esc_attr( CDRP_OPTION ); ?>[hook]">
							<?php foreach ( cdrp_hook_choices() as $hook => $label ) : ?>
								<option value="<?php echo esc_attr( $hook ); ?>" <?php selected( $s['hook'], $hook ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'If nothing shows in your theme drawer, try another position.', 'cart-drawer-related-products' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
