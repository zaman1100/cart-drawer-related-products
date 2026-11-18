# Cart Drawer Related Products for WooCommerce

Show related products, upsells or cross-sells inside the WooCommerce **mini cart / cart drawer**, with one-click AJAX add to cart.

Works with any theme whose drawer uses the standard WooCommerce mini cart (WoodMart, Flatsome, Astra, Storefront and others). No theme-specific markup is touched.

## Features

- Suggestions based on the items in the cart: related products, upsells or cross-sells
- Already-in-cart, hidden and out-of-stock products are skipped
- One-click AJAX add to cart for simple products, "Options" link for variable products
- Refreshes automatically when the drawer updates (uses the normal WooCommerce cart fragments)
- Settings page: heading, number of products, source, output position
- `cdrp_product_ids` filter to customise the product list
- HPOS compatible, translation ready

## Installation

1. Download the latest zip from [Releases](../../releases) (or zip the `cart-drawer-related-products` folder).
2. In WordPress go to **Plugins > Add New > Upload Plugin** and upload the zip.
3. Activate it, then open **WooCommerce > Cart Drawer Related** to adjust the settings.

Requires WordPress 5.8+, PHP 7.4+ and WooCommerce 6.0+.

## Settings

| Option | Description |
| --- | --- |
| Enable | Turn the suggestions on or off |
| Heading | Title shown above the products |
| Number of products | 1 to 12 |
| Suggest | Related products, upsells or cross-sells |
| Position | After the mini cart, or before / after the View cart and Checkout buttons |

If nothing appears in your theme's drawer, switch the **Position** option. Some themes override the mini cart template and skip certain hooks.

## Developers

Change the suggested products:

```php
add_filter( 'cdrp_product_ids', function ( $ids, $settings ) {
	// Return an array of product IDs.
	return $ids;
}, 10, 2 );
```

## License

GPL-2.0-or-later
