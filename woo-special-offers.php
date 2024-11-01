<?php

/**
 *
 * @package   WooCommerce Special Offers
 * @author    Abdelrahman Ashour < abdelrahman.ashour38@gmail.com >
 * @license   GPL-2.0+
 * @copyright 2018 Ash0ur


 * Plugin Name: Woo Special Offers
 * Description:  A plugin that allows users to add other products as offers.
 * Version:      1.0.0
 * Author:       Abdelrahman Ashour
 * Author URI:   https://profiles.wordpress.org/ashour
 * Contributors: ashour
 * License:      GPL2
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * text domain: woo-special-offer
 */

if ( ! defined( 'ABSPATH' ) ) {
		exit;
}


if ( ! class_exists( 'Woo_special_offers' ) ) :

	class Woo_special_offers {


		public static function init() {

			$WooSpecialOffers = new self();

		}

		public function __construct() {
			 $this->settings_tabs = array( 'special_offer' => __( 'Special Offer', 'special-offer' ) );

			$this->current_active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

			$this->define_constants();

			$this->setup_actions();

			$this->globalOfferEnabled = get_option( 'special_offer_global_enabled' );

			$this->productSingleText = get_option( 'special_offer_info_single_product' );

		}



		public function define_constants() {

			define( 'WOOSPOF_BASE_URL', plugin_dir_url( __FILE__ ) );

			define( 'WOOSPOF_ASSETS_URL', plugin_dir_url( __FILE__ ) . 'assets/' );

			define( 'WOOSPOF_PATH', plugin_dir_path( __FILE__ ) );
		}

		public static function plugin_activated() {

			if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				die( 'WooCommerce plugin must be active' );

			}

			if ( get_option( 'special_offer_global_enabled' ) == false ) {
				add_option( 'special_offer_global_enabled', 'yes' );
			}

			if ( get_option( 'special_offer_info_single_product' ) == false ) {
				add_option( 'special_offer_info_single_product', 'Buy {{quantity}} of this product and you will get' );
			}

		}


		public function admin_enqueue_global() {
			$screen = get_current_screen();

			if ( ( 'post' === $screen->base ) && ( 'product' === $screen->post_type ) ) {

				wp_enqueue_style( 'woospecialoffers_admin-styles', WOOSPOF_ASSETS_URL . 'css/admin-styles.css' );

				wp_enqueue_script( 'jquery' );

				wp_enqueue_script( 'woospecialoffers_actions', WOOSPOF_ASSETS_URL . 'js/admin_actions.js', array( 'jquery' ), WC_VERSION, true );

				wp_localize_script(
					'woospecialoffers_actions',
					'woospecialoffers_ajax_data',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'woospof_wp_ajax_nonce' ),
					)
				);
			}

		}



		public function frontend_enqueue_global() {
			if ( wp_script_is( 'jquery' ) ) {
				wp_enqueue_script( 'jquery' );
			}
		}


		public function setup_actions() {

					// Enqueue Scripts  //////////////////

			add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue_global' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_global' ) );

					// Products title filter  /////////////

			add_filter( 'pre_get_posts', array( $this, 'get_posts_by_title_filter' ), 20, 2 );

						// Special Offer tab in settigns page //////

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );

			add_action( 'woocommerce_settings_tabs', array( $this, 'add_special_offer_settings_tab' ) );

			foreach ( $this->settings_tabs as $name => $label ) {
				add_action( 'woocommerce_settings_tabs_' . $name, array( $this, 'settings_tab_action' ), 10 );
				add_action( 'woocommerce_update_options_' . $name, array( $this, 'save_tab_settings' ), 10 );
			}

			// Display Offer on single product page  /////////////

			add_action( 'woocommerce_product_tabs', array( $this, 'display_offer_details_single_product_tab' ) );

				// Special Offer tab actions  //////////////////

			add_action( 'wp_ajax_get_products_of_selected_categories', array( $this, 'get_products_of_categories' ) );

			add_action( 'wp_ajax_special_offers_product_search', array( $this, 'special_offers_product_search' ) );

				// Special Offer tab contents  //////////////////

			add_filter( 'woocommerce_product_write_panel_tabs', array( $this, 'special_offer_tab' ) );

			if ( version_compare( WOOCOMMERCE_VERSION, '2.7.0' ) >= 0 ) {
				add_filter( 'woocommerce_product_data_panels', array( $this, 'special_offer_tab_content' ) );
			} else {
				add_filter( 'woocommerce_product_write_panels', array( $this, 'special_offer_tab_content' ) );
			}

			add_action( 'woocommerce_process_product_meta', array( $this, 'save_special_offer_tab_content_metas' ) );

					// Cart manipulation actions  /////////////

			add_action( 'woocommerce_add_to_cart', array( $this, 'add_offer_items_in_cart' ), PHP_INT_MAX, 6 );

			// in case the udpate cart button is clicked and quantity is changed
			add_filter( 'woocommerce_stock_amount_cart_item', array( $this, 'check_offer_on_change_quantity' ), PHP_INT_MAX, 2 );

			add_filter( 'woocommerce_update_cart_action_cart_updated', array( $this, 'double_check_to_remove_offer_items' ) );

			add_filter( 'woocommerce_cart_item_removed_title', array( $this, 'remove_offer_items_on_remove_main_item' ), PHP_INT_MAX, 2 );

			// remove remove icon for offers products
			add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'discard_remove_icon_for_offer_products' ), PHP_INT_MAX, 2 );

			// add the offer title next to product name
			add_filter( 'woocommerce_cart_item_name', array( $this, 'add_offer_title_next_to_product_name' ), PHP_INT_MAX, 3 );

			// remove the quantity input from offers products
			add_filter( 'woocommerce_cart_item_quantity', array( $this, 'remove_qty_input_for_offers_products' ), PHP_INT_MAX, 3 );

			// on undo offer main product
			add_action( 'woocommerce_cart_item_restored', array( $this, 'restore_offer_items_on_undo' ), PHP_INT_MAX );

			// change the offer items price in subtotal and total row => soft changes
			add_filter( 'woocommerce_cart_item_price', array( $this, 'change_offer_items_subtotal' ), PHP_INT_MAX, 2 );
			add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'change_offer_items_subtotal' ), PHP_INT_MAX, 2 );

			// change the offer items price in cart totals => real changes
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'change_offer_items_price' ), PHP_INT_MAX );

		}


		public function action_links( $links ) {

			$settings_slug = 'woocommerce';

			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0' ) >= 0 ) {

				$settings_slug = 'wc-settings';

			}

			$plugin_links = array(
				'<a href="' . admin_url( 'admin.php?page=' . $settings_slug . '&tab=special_offer' ) . '">' . __( 'Settings', 'woocommerce' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}


		// Special Offer Settings Tab  ///////////

		public function add_special_offer_settings_tab() {

			$settings_slug = 'woocommerce';

			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0' ) >= 0 ) {
				$settings_slug = 'wc-settings';
			}

			foreach ( $this->settings_tabs as $name => $label ) {

				$class = 'nav-tab';
				if ( $this->current_active_tab == $name ) {
					$class .= ' nav-tab-active';
				}

				echo '<a href="' . admin_url( 'admin.php?page=' . $settings_slug . '&tab=' . $name ) . '" class="' . $class . '">' . $label . '</a>';

			}

		}

		public function settings_tab_action() {

			global $woocommerce_settings;

			$current_tab = str_replace( 'woocommerce_settings_tabs_', '', current_filter() );

			$this->add_settings_fields();

			woocommerce_admin_fields( $woocommerce_settings[ $current_tab ] );

		}

		public function save_tab_settings() {

			global $woocommerce_settings;

			// $this->add_settings_fields();

			$current_tab = str_replace( 'woocommerce_update_options_', '', current_filter() );

			woocommerce_update_options( $woocommerce_settings[ $current_tab ] );
		}


		public function add_settings_fields() {

			global $woocommerce_settings;

			$this->init_form_fields();

			if ( is_array( $this->fields ) ) {
				foreach ( $this->fields as $k => $v ) {
					$woocommerce_settings[ $k ] = $v;
				}
			}

		}



		public function init_form_fields() {

			global $woocommerce;

			$this->fields['special_offer'] = array(

				array(
					'name' => __( 'Special Offer', 'special-offer' ),
					'type' => 'title',
				),

				array(
					'name'     => __( 'Enable/Disable Special Offers', 'special-offer' ),
					'id'       => 'special_offer_global_enabled',
					'type'     => 'checkbox',
					'default'  => 'yes',
					'desc-tip' => __( 'Disable this checkbox if you want to disable all offers for all products' ),

				),

				array(

					'name'    => __( 'Optionaly Add custom information on special offer tab on single product page', 'woo-special-offer' ),
					'id'      => 'special_offer_info_single_product',
					'type'    => 'textarea',
					'css'     => 'min-height:200px;',
					'default' => 'Buy {{quantity}} of this product and you will get:',
					'desc'    => __( 'keep {{quantity}} keyword to be replaced with the minimum product quantity for the offer', 'woo-special-offer' ),

				),
				array(
					'name' => '',
					'type' => 'sectionend',
					'id'   => 'special_offer_settings_end',
				),

			);
		}




		public function display_offer_details_single_product_tab( $tabs ) {

			$product_id = get_the_ID();

			$hasOffer = get_post_meta( $product_id, 'special_offer_enabled', true );

			if ( $this->globalOfferEnabled == 'yes' && $hasOffer == 'on' ) {

				$tabs['special_offers'] = array(

					'title'    => __( 'Special Offers', 'woocommerce' ),
					'priority' => 200,
					'callback' => array( $this, 'display_offer_details_on_product_page' ),

				);

			}

			return $tabs;
		}


		public function display_offer_details_on_product_page( $tabs ) {

				$product_id = get_the_ID();

			$minimum_quantity_required = get_post_meta( $product_id, 'special_offers_minimum_qty', true );

			$offerProducts = get_post_meta( $product_id, 'special_offer_products' ); ?>

			<style>
				span.offer-title{color:#FFF;font-weight:bold;padding:1px 3px; background:#555;border-radius:5px;}

				.single-product-page-quantity-for-offer {color:#4AB915;margin:20px 0px;}

				.product.offer-item-container { width: 19.75% !important; }
			</style>
			<div class="offer-single-container">
					<?php

					if ( strpos( $this->productSingleText, '{{quantity}}' ) !== false ) :

						echo '<h3>' . __( str_replace( '{{quantity}}', '<span class="single-product-page-quantity-for-offer" >&nbsp;' . $minimum_quantity_required . '&nbsp;</span> ', esc_html( $this->productSingleText ) ), 'woo-special-offer' ) . '</h3>';

			else :

				echo '<h3>' . esc_html_e( $this->productSingleText, 'woo-special-offer' ) . '</h3>';

			 endif;
			?>

				<ul class="offer-single-items products columns-3">
				<?php foreach ( $offerProducts[0] as $id => $qty ) : ?>
					<li class="product offer-item-container">
						<img src="<?php echo get_the_post_thumbnail_url( $id, array( 80, 80 ) ); ?>" alt="">
						<h4><a href="<?php echo get_the_permalink( $id ); ?>"><?php echo get_the_title( $id ); ?></a><span style="font-weight:bold;">&nbsp;&nbsp;&times;&nbsp;<?php echo $qty; ?></span></h4>

					</li>

				<?php endforeach; ?>
				</ul>

			</div>


			<?php
		}


		public function get_posts_by_title_filter( $query ) {
			if ( isset( $query->query_vars['special_offer_product_search_title'] ) ) {
				$query->set( 's', sanitize_title( $query->query_vars['special_offer_product_search_title'] ) );
				$query->set( 'post_type', 'product' );
			}

			return $query;
		}

			// Special Offers Section ////////////////////////


		public function special_offer_tab() {
			echo '<li class="special_offer special_offer_options"><a  href="#special_offer_product_data"><span>Special Offer</span></a></li>';
		}

		public function special_offer_tab_content() {
			global $post, $thepostid;
			?>

		<div id="special_offer_product_data" class="panel woocommerce_options_panel">

			<?php
			woocommerce_wp_checkbox(

				array(
					'id'      => 'special_offer_enabled',
					'value'   => get_post_meta( $thepostid, 'special_offer_enabled', true ) ? get_post_meta( $thepostid, 'special_offer_enabled', true ) : 'off',
					'label'   => __( 'Enable Special Offer', 'woo-special-offer' ),
					'class'   => 'checkbox',
					'cbvalue' => 'on',
				)
			);

				woocommerce_wp_text_input(

					array(
						'id'                => 'special_offers_minimum_qty',
						'label'             => __( 'Minimum Quantity', 'woo-special-offer' ),
						'type'              => 'number',
						'value'             => get_post_meta( $thepostid, 'special_offers_minimum_qty', true ),
						'class'             => 'short',
						'placeholder'       => '1',
						'desc_tip'          => 'yes',
						'description'       => __( 'The minimum number of this product to be bought in order to start the offer', 'woo-special-offer' ),
						'custom_attributes' => array( 'min' => '1' ),
					)
				);

			?>


			<?php $all_products_terms = get_terms( 'product_cat', array( 'hide_empty' => true ) ); ?>
			<p class="form-field special_offers_selected_categories_field">

				<label for="special_offers_selected_categories">Search By Cateogries</label>

				<select multiple name="special_offers_selected_categories" id="special_offers_selected_categories">
					<?php foreach ( $all_products_terms as $cat ) : ?>
							<option value="<?php echo $cat->term_id; ?>"><?php echo $cat->name; ?></option>
					<?php endforeach; ?>
				</select>



			</p>
			<p class="form-field">
				<label for="special_offers_product_search_input">Search By Product Title</label>
				<input type="text" name = "special_offers_product_search_input" id="special_offers_product_search_input" >

			</p>

			<div class="special-offer-products-container form-field special_offers_selected_products_field">
				<label for="special_offers_selected_products">Select Products</label>

				<!-- <button class="special_offer_restore_offers">Restore Saved Items</button> -->
				<button class="special_offer_products_search_clear">Clear Results</button>

				<ul id="special_offers_selected_products">
				  <?php
					$offerProducts = get_post_meta( $thepostid, 'special_offer_products' );
					if ( ! empty( $offerProducts ) && is_array( $offerProducts ) ) :
						foreach ( $offerProducts[0] as $product_id => $qty ) :
							$product_title = get_the_title( $product_id );
							?>
						<li class='form-field offers-product-row pinned'>
							<a class="offers-product-link" target="_blank" href="<?php echo  get_edit_post_link( $product_id ); ?>" >
								<img width="40" height="40" src="<?php echo get_the_post_thumbnail_url( $product_id ); ?>" />
							</a>
							<label for='special_offers_selected_product_quantity_<?php echo $product_id; ?>' ><?php echo $product_title; ?>
							</label>
							<input  min="1" type='number' name='special_offers_selected_product_quantity_<?php echo $product_id; ?>' value='<?php echo $qty; ?>' />
							<i role='button' class='dashicons dashicons-dismiss remove-special-offer-product-btn'></i>
							<span class="product-row-pin clicked" >pinned</span>

						</li>

							<?php
			  endforeach;

						endif;
					?>
				</ul>
			</div>
		</div>

			<?php
		}



		public function save_special_offer_tab_content_metas( $post_id ) {

					// Save offer enable ///////////////

			if ( isset( $_POST['special_offer_enabled'] ) && $_POST['special_offer_enabled'] == 'on' ) {

				update_post_meta( $post_id, 'special_offer_enabled', 'on' );

			} else {

				update_post_meta( $post_id, 'special_offer_enabled', 'off' );

			}

					// Save offer products ///////////////

			$ProductIdWithQuantity = array();

			foreach ( $_POST as $key => $value ) {

				if ( strpos( $key, 'special_offers_selected_product_quantity_' ) === 0 ) {

					$result = strrchr( $key, '_' );

					$id = explode( '_', $result )[1];

					if ( ! empty( $value ) && absint( $value ) && absint( $id ) ) {

						$ProductIdWithQuantity[ $id ] = $value;

					}
				}
			}

			if ( ! empty( $ProductIdWithQuantity ) ) {

				update_post_meta( $post_id, 'special_offer_products', $ProductIdWithQuantity );
			}

					// Save offer minimum quantity ///////////////

			if ( ! empty( $_POST['special_offers_minimum_qty'] ) ) {

				$minimum_qty = $_POST['special_offers_minimum_qty'];

				if ( ! empty( $minimum_qty ) && absint( $minimum_qty ) ) {

					update_post_meta( $post_id, 'special_offers_minimum_qty', $minimum_qty );
				}
			}

		}



			// Cart manipulation Section ////////////////

		public function add_offer_items_in_cart( $cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) {

			$cart_item = WC()->cart->cart_contents[ $cart_item_key ];

			$hasOffer = get_post_meta( $product_id, 'special_offer_enabled', true );

			$minimum_quantity_required = get_post_meta( $product_id, 'special_offers_minimum_qty', true );

			if ( $this->globalOfferEnabled == 'yes' && ( $hasOffer == 'on' ) && ( $cart_item['quantity'] >= $minimum_quantity_required ) ) {

				if ( ! array_key_exists( 'offer_applied', $cart_item ) ) {

					WC()->cart->cart_contents[ $cart_item_key ]['offer_applied'] = 'Yes';

					$offerProducts = get_post_meta( $product_id, 'special_offer_products' );

					foreach ( $offerProducts[0] as $id => $qty ) {

						$added_cart_item_key = WC()->cart->add_to_cart( $id, $qty, false, false, array( 'offer_item' => $product_id ) );

						$cart_items_keys = array_keys( WC()->cart->cart_contents );

						$current_added_item_index = array_search( $added_cart_item_key, $cart_items_keys );

						$main_product_index = array_search( $cart_item_key, $cart_items_keys );

						$this->moveElement( $cart_items_keys, $current_added_item_index, $main_product_index + 1 );

						WC()->cart->cart_contents = array_replace( array_flip( $cart_items_keys ), WC()->cart->cart_contents );

					}
				}
			}

		}


		public function moveElement( &$array, $a, $b ) {

			$out = array_splice( $array, $a, 1 );

			array_splice( $array, $b, 0, $out );

		}


		public function check_offer_on_change_quantity( $new_item_quantity, $cart_item_key ) {

			$product_id = WC()->cart->cart_contents[ $cart_item_key ]['product_id'];

			$hasOffer = get_post_meta( $product_id, 'special_offer_enabled', true );

			$minimum_quantity_required = get_post_meta( $product_id, 'special_offers_minimum_qty', true );

			if ( $this->globalOfferEnabled == 'yes' ) {

				if ( array_key_exists( 'offer_item', WC()->cart->cart_contents[ $cart_item_key ] ) ) {

					return WC()->cart->cart_contents[ $cart_item_key ]['quantity'];

				}

				if ( ( $hasOffer == 'on' ) && ( $new_item_quantity >= $minimum_quantity_required ) ) {

					if ( ! array_key_exists( 'offer_applied', WC()->cart->cart_contents[ $cart_item_key ] ) ) {

						WC()->cart->cart_contents[ $cart_item_key ]['offer_applied'] = 'Yes';

						$offerProducts = get_post_meta( $product_id, 'special_offer_products' );

						foreach ( $offerProducts[0] as $id => $qty ) {

							$added_cart_item_key = WC()->cart->add_to_cart( $id, $qty, false, false, array( 'offer_item' => $product_id ) );

							$cart_items_keys = array_keys( WC()->cart->cart_contents );

							$current_added_item_index = array_search( $added_cart_item_key, $cart_items_keys );

							$main_product_index = array_search( $cart_item_key, $cart_items_keys );

							$this->moveElement( $cart_items_keys, $current_added_item_index, $main_product_index + 1 );

							WC()->cart->cart_contents = array_replace( array_flip( $cart_items_keys ), WC()->cart->cart_contents );

						}
					}
				} elseif ( ( $hasOffer == 'on' ) && ( $new_item_quantity < $minimum_quantity_required ) ) {

					// remove offer applied so can be used again
					unset( WC()->cart->cart_contents[ $cart_item_key ]['offer_applied'] );

					// remove the offer products
					$cart_items = WC()->cart->cart_contents;

					foreach ( $cart_items as $key => $cart_item_details ) {

						if ( array_key_exists( 'offer_item', $cart_item_details ) && ( $cart_item_details['offer_item'] == $product_id ) ) {

							$GLOBALS['offer_items_to_be_removed'][] = $key;

						}
					}
				}
			}

			return $new_item_quantity;

		}


		public function double_check_to_remove_offer_items( $cart_updated ) {

			if ( ! empty( $GLOBALS['offer_items_to_be_removed'] ) ) {

				foreach ( $GLOBALS['offer_items_to_be_removed'] as $offer_item_key ) {

					WC()->cart->remove_cart_item( $offer_item_key );

				}

				unset( $GLOBALS['offer_items_to_be_removed'] );
			}
		}

		public function remove_offer_items_on_remove_main_item( $item_title, $cart_item_data ) {

			if ( array_key_exists( 'offer_applied', $cart_item_data ) ) {
				$removed_item_id = $cart_item_data['product_id'];

				$cart_items = WC()->cart->cart_contents;

				foreach ( $cart_items as $key => $cart_item_value ) {

					if ( array_key_exists( 'offer_item', $cart_item_value ) && ( $cart_item_value['offer_item'] == $removed_item_id ) ) {

							WC()->cart->remove_cart_item( $key );

					}
				}
			}
			return $item_title;

		}


		public function discard_remove_icon_for_offer_products( $remove_item_link, $cart_item_key ) {

			$cart_item_details = WC()->cart->cart_contents[ $cart_item_key ];

			if ( array_key_exists( 'offer_item', $cart_item_details ) ) {

				return '';

			}

			return $remove_item_link;

		}

		public function add_offer_title_next_to_product_name( $product_name, $cart_item, $cart_item_key ) {

			if ( array_key_exists( 'offer_item', $cart_item ) ) {

				$product_name .= '&nbsp;<span class="offer-title">Offer</span>';

			}

			return $product_name;

		}


		public function show_price_as_free_for_offers_products( $product_price, $cart_item, $cart_item_key ) {

			if ( array_key_exists( 'offer_item', $cart_item ) ) {
				// improve that to include the original price and the offer price.
					return wc_price( 0.0 );
			}

			return $product_price;

		}


		public function remove_qty_input_for_offers_products( $product_qunatity, $cart_item_key, $cart_item ) {

			if ( array_key_exists( 'offer_item', $cart_item ) ) {

					return '<span>' . $cart_item['quantity'] . '</span>';
			}

			return $product_qunatity;
		}


		public function restore_offer_items_on_undo( $cart_item_key ) {

			$cart_item = WC()->cart->cart_contents[ $cart_item_key ];

			$theproductid = $cart_item['product_id'];

			$hasOffer = get_post_meta( $theproductid, 'special_offer_enabled', true );

			$minimum_quantity_required = get_post_meta( $theproductid, 'special_offers_minimum_qty', true );

			if ( $this->globalOfferEnabled == 'yes' && ( $hasOffer == 'on' ) && ( $cart_item['quantity'] >= $minimum_quantity_required ) ) {

				if ( array_key_exists( 'offer_applied', $cart_item ) ) {

					$offerProducts = get_post_meta( $theproductid, 'special_offer_products' );

					foreach ( $offerProducts[0] as $id => $qty ) {

						$added_cart_item_key = WC()->cart->add_to_cart( $id, $qty, false, false, array( 'offer_item' => $theproductid ) );

						$cart_items_keys = array_keys( WC()->cart->cart_contents );

						$current_added_item_index = array_search( $added_cart_item_key, $cart_items_keys );

						$main_product_index = array_search( $cart_item_key, $cart_items_keys );

						$this->moveElement( $cart_items_keys, $current_added_item_index, $main_product_index + 1 );

						WC()->cart->cart_contents = array_replace( array_flip( $cart_items_keys ), WC()->cart->cart_contents );

					}
				}
			}

		}


		public function change_offer_items_subtotal( $price_html, $cart_item ) {

			if ( array_key_exists( 'offer_item', $cart_item ) ) {

				if ( current_filter() == 'woocommerce_cart_item_price' ) {
					$the_price = $cart_item['data']->get_price();
				} elseif ( current_filter() == 'woocommerce_cart_item_subtotal' ) {
					$the_price = WC()->cart->get_product_subtotal( $cart_item['data'], $cart_item['quantity'] );
				}

				$price_html = '

						<span class="discount-info">
							<span class="new-price" style="color: #4AB915; font-weight: bold;">
							<span class="woocommerce-Price-amount amount"><span class="woocommerce-Price-currencySymbol">' . get_woocommerce_currency_symbol() . '</span>0</span>
							</span>
						</span>';

			}

				return $price_html;

		}


		public function change_offer_items_price( $WC_Cart_obj ) {

			$cart_items = $WC_Cart_obj->get_cart();

			foreach ( $cart_items as $cart_item_key => $cart_item_value ) {

				if ( array_key_exists( 'offer_item', $cart_item_value ) ) {

					$cart_item_value['data']->set_price( 0.0 );

				}
			}
		}




		public function get_products_of_categories() {

			check_ajax_referer( 'woospof_wp_ajax_nonce', 'nonce' );
			if ( ! empty( $_POST['categories_ids'] ) && is_array( $_POST['categories_ids'] ) ) {

				$catIds = $_POST['categories_ids'];
				if ( $this->all( $catIds, array( $this, 'is_absint' ) ) ) {

					$theLis   = '';
					$catIds   = array_map( array( $this, 'convert_to_int' ), $catIds );
					$products = new WP_Query(
						array(
							'post_type'      => 'product',
							'posts_per_page' => -1,
							'tax_query'      => array(
								array(
									'taxonomy' => 'product_cat',
									'field'    => 'term_id',
									'terms'    => $catIds,
								),
							),
						)
					);

					if ( $products->have_posts() ) :
						while ( $products->have_posts() ) :
							$products->the_post();
							if ( wc_get_product( get_the_ID() )->get_type() != 'variable' && wc_get_product( get_the_ID() )->get_type() != 'grouped' ) {
								$theLis .= '<li class="form-field offers-product-row" >
								<a class="offers-product-link" target="_blank" href="' . get_edit_post_link( get_the_ID() ) . '" >
									<img width="40" height="40" src="' . get_the_post_thumbnail_url() . '" />
								</a>
								<label for="special_offers_selected_product_quantity_' . get_the_ID() . '" >' . get_the_title() . '</label>
								<input min="1" type="number" name="special_offers_selected_product_quantity_' . get_the_ID() . '" value="1" />
								<i role="button" class="dashicons dashicons-dismiss remove-special-offer-product-btn"></i>
								<span class="product-row-pin" >pin</span>
								</li>';							}
						endwhile;
						wp_reset_postdata();
					endif;

					echo json_encode( $theLis );

				} else {
					echo json_encode( 'bad input' );
				}
			}

			wp_die();
		}


		public function special_offers_product_search() {

			check_ajax_referer( 'woospof_wp_ajax_nonce', 'nonce' );

			$product_name = sanitize_title( $_POST['product_name'] );

			if ( ! empty( $product_name ) ) {

				$products_found = new WP_Query(
					array(
						'post_type'      => 'product',
						'posts_per_page' => '-1',
						'special_offer_product_search_title' => $product_name,
					)
				);

				$theLis = '';

				if ( $products_found->have_posts() ) :

					while ( $products_found->have_posts() ) :
						$products_found->the_post();

						if ( wc_get_product( get_the_ID() )->get_type() != 'variable' && wc_get_product( get_the_ID() )->get_type() != 'grouped' ) {
							$theLis .= '<li class="form-field offers-product-row">
								<a class="offers-product-link" target="_blank" href="' . get_edit_post_link( get_the_ID() ) . '" >
									<img width="40" height="40" src="' . get_the_post_thumbnail_url() . '" />
								</a>
								<label for="special_offers_selected_product_quantity_' . get_the_ID() . '" >' . get_the_title() . '</label>
								<input min="1" type="number" name="special_offers_selected_product_quantity_' . get_the_ID() . '" value="1" />
								<i role="button" class="dashicons dashicons-dismiss remove-special-offer-product-btn"></i>
								<span class="product-row-pin" >pin</span>
								</li>';
						}
					endwhile;
					wp_reset_postdata();

				endif;

				echo ( $theLis );
			}

			wp_die();
		}


		private function all( $array, $fun ) {
			return array_filter( $array, $fun ) === $array;
		}

		private function is_absint( $val ) {
			return ( is_numeric( $val ) && ( (int) $val >= 0 ) );
		}

		private function convert_to_int( $str ) {
			return intval( $str );
		}

	}



	add_action( 'plugins_loaded', array( 'Woo_special_offers', 'init' ), 10 );

	register_activation_hook( __FILE__, array( 'Woo_special_offers', 'plugin_activated' ) );

endif;
