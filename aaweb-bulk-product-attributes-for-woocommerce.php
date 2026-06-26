<?php
/**
 * Plugin Name: AAWEB Bulk Product Attributes for WooCommerce
 * Plugin URI: https://antoapweb.gr/aaweb-bulk-product-attributes-for-woocommerce
 * Description: Bulk assign WooCommerce global product attributes to multiple products using advanced filters, bulk actions and a modern management interface.
 * Version: 1.0.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Author: AAWEB - Apostolou Antonios
 * Author URI: https://antoapweb.gr
 * Text Domain: aaweb-bulk-product-attributes-for-woocommerce
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package AAWEB_Bulk_Product_Attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'AAWEB_BPAFW_VERSION' ) ) {
	define( 'AAWEB_BPAFW_VERSION', '1.0.0' );
}

if ( ! defined( 'AAWEB_BPAFW_TEXT_DOMAIN' ) ) {
	define( 'AAWEB_BPAFW_TEXT_DOMAIN', 'aaweb-bulk-product-attributes-for-woocommerce' );
}

final class AAWEB_Bulk_Product_Attributes_For_WooCommerce {

	private $per_page = 200;

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ], 99 );
		add_action( 'admin_init', [ $this, 'handle_bulk_apply' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function register_menu() {
		add_submenu_page(
			'edit.php?post_type=product',
			esc_html__( 'AAWEB Bulk Product Attributes', 'aaweb-bulk-product-attributes-for-woocommerce' ),
			esc_html__( 'AAWEB Bulk Product Attributes', 'aaweb-bulk-product-attributes-for-woocommerce' ),
			'manage_woocommerce',
			'aaweb-bulk-product-attributes-for-woocommerce',
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'product_page_aaweb-bulk-product-attributes-for-woocommerce' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'aaweb-bpafw-admin',
			plugin_dir_url( __FILE__ ) . 'assets/admin.css',
			[],
			AAWEB_BPAFW_VERSION
		);

		wp_enqueue_script(
			'aaweb-bpafw-admin',
			plugin_dir_url( __FILE__ ) . 'assets/admin.js',
			[],
			AAWEB_BPAFW_VERSION,
			true
		);
	}

	private function get_current_filters() {
		return [
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only admin view parameters.
			's'            => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only admin view parameters.
			'aaweb_keyword'  => isset( $_GET['aaweb_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['aaweb_keyword'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only admin view parameters.
			'product_cat'  => isset( $_GET['product_cat'] ) ? absint( $_GET['product_cat'] ) : 0,
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only admin view parameters.
			'stock_status' => isset( $_GET['stock_status'] ) ? sanitize_text_field( wp_unslash( $_GET['stock_status'] ) ) : '',
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filters are read-only admin view parameters.
			'paged'        => isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1,
		];
	}

	private function build_query_args( $filters ) {
		$args = [
			'post_type'              => 'product',
			'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
			'posts_per_page'         => $this->per_page,
			'paged'                  => $filters['paged'],
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
			'suppress_filters'       => false,
			'aaweb_keyword'            => $filters['aaweb_keyword'],
			'aaweb_query'        => 1,
		];

		if ( $filters['s'] !== '' ) {
			$args['aaweb_title_search'] = $filters['s'];
		}

		if ( $filters['product_cat'] > 0 ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Required admin filter for WooCommerce product categories.
			$args['tax_query'] = [
				[
					'taxonomy' => 'product_cat',
					'field'    => 'term_id',
					'terms'    => [ $filters['product_cat'] ],
				],
			];
		}

		if ( $filters['stock_status'] !== '' ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required admin filter for WooCommerce stock status.
			$args['meta_query'] = [
				[
					'key'   => '_stock_status',
					'value' => $filters['stock_status'],
				],
			];
		}

		return $args;
	}

	private function get_store_attribute_taxonomies() {
		$result = [];

		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return $result;
		}

		$attributes = wc_get_attribute_taxonomies();

		if ( empty( $attributes ) ) {
			return $result;
		}

		foreach ( $attributes as $attribute ) {
			if ( empty( $attribute->attribute_name ) ) {
				continue;
			}

			$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );

			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$label = ! empty( $attribute->attribute_label ) ? $attribute->attribute_label : $attribute->attribute_name;

			$result[ $taxonomy ] = [
				'id'    => (int) $attribute->attribute_id,
				'name'  => $attribute->attribute_name,
				'label' => $label,
			];
		}

		return $result;
	}

	private function get_attribute_terms( $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		return $terms;
	}

	private function ensure_term_exists( $taxonomy, $term_name ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		$term_name = trim( (string) $term_name );

		if ( $term_name === '' ) {
			return 0;
		}

		$term = get_term_by( 'name', $term_name, $taxonomy );

		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$created = wp_insert_term( $term_name, $taxonomy );

		if ( is_wp_error( $created ) || empty( $created['term_id'] ) ) {
			return 0;
		}

		return (int) $created['term_id'];
	}

	private function ensure_product_attribute_meta( $product_id, $taxonomy ) {
		$product_attributes = get_post_meta( $product_id, '_product_attributes', true );

		if ( ! is_array( $product_attributes ) ) {
			$product_attributes = [];
		}

		$existing_position = isset( $product_attributes[ $taxonomy ]['position'] )
			? (int) $product_attributes[ $taxonomy ]['position']
			: count( $product_attributes );

		$product_attributes[ $taxonomy ] = [
			'name'         => $taxonomy,
			'value'        => '',
			'position'     => $existing_position,
			'is_visible'   => 1,
			'is_variation' => 0,
			'is_taxonomy'  => 1,
		];

		update_post_meta( $product_id, '_product_attributes', $product_attributes );
	}

	private function apply_attribute_terms_to_product( $product_id, $taxonomy, array $term_names, $mode = 'append' ) {
		$product_id = absint( $product_id );

		if ( ! $product_id || get_post_type( $product_id ) !== 'product' ) {
			return;
		}

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$term_ids = [];

		foreach ( $term_names as $term_name ) {
			$term_name = trim( wp_unslash( $term_name ) );

			if ( $term_name === '' ) {
				continue;
			}

			$term_id = $this->ensure_term_exists( $taxonomy, $term_name );

			if ( $term_id ) {
				$term_ids[] = $term_id;
			}
		}

		$term_ids = array_values( array_unique( array_map( 'intval', $term_ids ) ) );

		if ( empty( $term_ids ) ) {
			return;
		}

		if ( 'replace' === $mode ) {
			wp_set_object_terms( $product_id, $term_ids, $taxonomy, false );
		} else {
			$current_ids = wp_get_object_terms( $product_id, $taxonomy, [ 'fields' => 'ids' ] );

			if ( is_wp_error( $current_ids ) ) {
				$current_ids = [];
			}

			$all_ids = array_values( array_unique( array_map( 'intval', array_merge( $current_ids, $term_ids ) ) ) );
			wp_set_object_terms( $product_id, $all_ids, $taxonomy, false );
		}

		$this->ensure_product_attribute_meta( $product_id, $taxonomy );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}

		clean_post_cache( $product_id );
	}

	public function handle_bulk_apply() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_POST['aaweb_bulk_apply_submit'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		check_admin_referer( 'aaweb_bulk_apply_action', 'aaweb_bulk_apply_nonce' );

		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : [];
		$product_ids = array_values( array_filter( $product_ids ) );

		$mode = isset( $_POST['aaweb_assign_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['aaweb_assign_mode'] ) ) : 'append';

		if ( ! in_array( $mode, [ 'append', 'replace' ], true ) ) {
			$mode = 'append';
		}

		$allowed_taxonomies = $this->get_store_attribute_taxonomies();

		$posted_attributes = isset( $_POST['aaweb_attrs'] ) && is_array( $_POST['aaweb_attrs'] )
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nested values are sanitized below after taxonomy validation.
			? wp_unslash( $_POST['aaweb_attrs'] )
			: [];

		$sanitized_attributes = [];

		foreach ( $posted_attributes as $taxonomy => $values ) {
			$taxonomy = sanitize_key( $taxonomy );

			if ( ! isset( $allowed_taxonomies[ $taxonomy ] ) ) {
				continue;
			}

			$values = is_array( $values ) ? array_map( 'sanitize_text_field', $values ) : [];
			$values = array_values( array_filter( array_map( 'trim', $values ) ) );

			if ( ! empty( $values ) ) {
				$sanitized_attributes[ $taxonomy ] = $values;
			}
		}

		$updated = 0;

		if ( ! empty( $product_ids ) && ! empty( $sanitized_attributes ) ) {
			foreach ( $product_ids as $product_id ) {
				foreach ( $sanitized_attributes as $taxonomy => $term_names ) {
					$this->apply_attribute_terms_to_product( $product_id, $taxonomy, $term_names, $mode );
				}
				$updated++;
			}
		}

		$redirect_url = add_query_arg(
			[
				'post_type'    => 'product',
				'page'         => 'aaweb-bulk-product-attributes-for-woocommerce',
				's'            => isset( $_POST['current_s'] ) ? sanitize_text_field( wp_unslash( $_POST['current_s'] ) ) : '',
				'aaweb_keyword'  => isset( $_POST['current_aaweb_keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['current_aaweb_keyword'] ) ) : '',
				'product_cat'  => isset( $_POST['current_product_cat'] ) ? absint( $_POST['current_product_cat'] ) : 0,
				'stock_status' => isset( $_POST['current_stock_status'] ) ? sanitize_text_field( wp_unslash( $_POST['current_stock_status'] ) ) : '',
				'paged'        => isset( $_POST['current_paged'] ) ? max( 1, absint( $_POST['current_paged'] ) ) : 1,
				'aaweb_updated'  => $updated,
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $redirect_url );
		exit;
	}

	private function get_product_term_names( $product_id, $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '—';
		}

		$names = wp_list_pluck( $terms, 'name' );

		return implode( ', ', $names );
	}

	private function get_product_term_badges( $product_id, $taxonomy ) {
		$names = $this->get_product_term_names( $product_id, $taxonomy );

		if ( '—' === $names ) {
			return '<span class="aaweb-empty-value">—</span>';
		}

		$parts = array_filter( array_map( 'trim', explode( ',', $names ) ) );
		$html  = '';

		foreach ( $parts as $part ) {
			$html .= '<span class="aaweb-term-badge">' . esc_html( $part ) . '</span>';
		}

		return $html ? $html : '<span class="aaweb-empty-value">—</span>';
	}

	private function get_stock_label( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return '—';
		}

		$status = $product->get_stock_status();

		switch ( $status ) {
			case 'instock':
				return esc_html__( 'In stock', 'aaweb-bulk-product-attributes-for-woocommerce' );
			case 'outofstock':
				return esc_html__( 'Out of stock', 'aaweb-bulk-product-attributes-for-woocommerce' );
			case 'onbackorder':
				return esc_html__( 'On backorder', 'aaweb-bulk-product-attributes-for-woocommerce' );
			default:
				return $status ? $status : '—';
		}
	}

	private function get_stock_class( $product ) {
		if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
			return 'unknown';
		}

		$status = $product->get_stock_status();

		return $status ? sanitize_html_class( $status ) : 'unknown';
	}

	private function render_admin_styles() {
		// Admin styles are enqueued via enqueue_admin_assets().
	}

	private function render_filters_form( $filters ) {
		?>
		<form method="get" class="aaweb-card">
			<div class="aaweb-card-inner">
				<div class="aaweb-card-title">
					<span><?php esc_html_e( 'Product filters', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></span>
				</div>

				<input type="hidden" name="post_type" value="product">
				<input type="hidden" name="page" value="aaweb-bulk-product-attributes-for-woocommerce">

				<div class="aaweb-filters-grid">
					<div class="aaweb-field">
						<label for="aaweb-search-input"><?php esc_html_e( 'Title', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></label>
						<input type="search" id="aaweb-search-input" name="s" value="<?php echo esc_attr( $filters['s'] ); ?>" placeholder="<?php echo esc_attr__( 'Search by title only', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?>">
					</div>

					<div class="aaweb-field">
						<label for="aaweb-keyword-input"><?php esc_html_e( 'Keyword / SKU', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></label>
						<input type="text" id="aaweb-keyword-input" name="aaweb_keyword" value="<?php echo esc_attr( $filters['aaweb_keyword'] ); ?>" placeholder="<?php echo esc_attr__( 'Keyword, SKU or content', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?>">
					</div>

					<div class="aaweb-field">
						<label for="product_cat"><?php esc_html_e( 'Category', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></label>
						<?php
						wp_dropdown_categories(
							[
								'show_option_all' => esc_html__( 'All categories', 'aaweb-bulk-product-attributes-for-woocommerce' ),
								'taxonomy'        => 'product_cat',
								'name'            => 'product_cat',
								'id'              => 'product_cat',
								'orderby'         => 'name',
								'selected'        => $filters['product_cat'],
								'hierarchical'    => true,
								'hide_empty'      => false,
								'value_field'     => 'term_id',
							]
						);
						?>
					</div>

					<div class="aaweb-field">
						<label for="stock_status"><?php esc_html_e( 'Stock status', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></label>
						<select id="stock_status" name="stock_status">
							<option value=""><?php esc_html_e( 'All stock', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></option>
							<option value="instock" <?php selected( $filters['stock_status'], 'instock' ); ?>><?php esc_html_e( 'In stock', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></option>
							<option value="outofstock" <?php selected( $filters['stock_status'], 'outofstock' ); ?>><?php esc_html_e( 'Out of stock', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></option>
							<option value="onbackorder" <?php selected( $filters['stock_status'], 'onbackorder' ); ?>><?php esc_html_e( 'On backorder', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></option>
						</select>
					</div>

					<div class="aaweb-field">
						<label>&nbsp;</label>
						<div class="aaweb-button-row">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></button>
							<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=aaweb-bulk-product-attributes-for-woocommerce' ) ); ?>"><?php esc_html_e( 'Reset', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></a>
						</div>
					</div>
				</div>
			</div>
		</form>
		<?php
	}

	private function render_bulk_box() {
		$attributes = $this->get_store_attribute_taxonomies();
		?>
		<div class="aaweb-card">
			<div class="aaweb-card-inner">
				<div class="aaweb-card-title">
					<span><?php esc_html_e( 'Bulk attribute assignment', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></span>
					<span class="aaweb-muted"><?php esc_html_e( 'Select products below and choose attribute terms here.', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></span>
				</div>

				<?php if ( empty( $attributes ) ) : ?>
					<p class="aaweb-muted aaweb-no-margin"><?php esc_html_e( 'No WooCommerce global attributes found.', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></p>
				<?php else : ?>
					<div class="aaweb-attributes-grid">
						<?php foreach ( $attributes as $taxonomy => $attribute ) : ?>
							<?php $terms = $this->get_attribute_terms( $taxonomy ); ?>
							<details class="aaweb-attribute-panel" open>
								<summary class="aaweb-attribute-summary">
									<span><?php echo esc_html( $attribute['label'] ); ?></span>
									<span class="aaweb-term-count"><?php echo esc_html( count( $terms ) ); ?> <?php esc_html_e( 'terms', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></span>
								</summary>

								<div class="aaweb-attribute-body" data-aaweb-attr-panel>
									<?php if ( empty( $terms ) ) : ?>
										<div class="aaweb-muted"><?php esc_html_e( 'No terms', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></div>
									<?php else : ?>
										<input type="search" class="aaweb-attr-search" placeholder="<?php echo esc_attr( sprintf( /* translators: %s: attribute label. */ __( 'Search in %s', 'aaweb-bulk-product-attributes-for-woocommerce' ), $attribute['label'] ) ); ?>">
										<div class="aaweb-term-tools">
											<button type="button" data-aaweb-select-visible><?php esc_html_e( 'Select visible', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></button>
											<button type="button" data-aaweb-clear-panel><?php esc_html_e( 'Clear', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></button>
										</div>
										<div class="aaweb-term-list">
											<?php foreach ( $terms as $term ) : ?>
												<label class="aaweb-term-option">
													<input type="checkbox" name="aaweb_attrs[<?php echo esc_attr( $taxonomy ); ?>][]" value="<?php echo esc_attr( $term->name ); ?>">
													<span><?php echo esc_html( $term->name ); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
									<?php endif; ?>
								</div>
							</details>
						<?php endforeach; ?>
					</div>

					<div class="aaweb-mode-card">
						<label>
							<input type="radio" name="aaweb_assign_mode" value="append" checked>
							<span><strong><?php esc_html_e( 'Append to existing', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></strong><span><?php esc_html_e( 'Keep existing terms and add the selected ones.', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></span></span>
						</label>

						<label>
							<input type="radio" name="aaweb_assign_mode" value="replace">
							<span><strong><?php esc_html_e( 'Replace existing', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></strong><span><?php esc_html_e( 'Remove the existing terms for each attribute and assign only the selected ones.', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></span></span>
						</label>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function run_products_query( $filters ) {
		$args = $this->build_query_args( $filters );

		add_filter( 'posts_where', [ $this, 'title_exact_phrase_where_clause' ], 9, 2 );

		add_filter( 'posts_join', [ $this, 'keyword_join_sku_meta' ], 10, 2 );
		add_filter( 'posts_where', [ $this, 'keyword_where_clause' ], 10, 2 );
		add_filter( 'posts_groupby', [ $this, 'keyword_groupby_clause' ], 10, 2 );

		$query = new WP_Query( $args );

		remove_filter( 'posts_where', [ $this, 'title_exact_phrase_where_clause' ], 9 );

		remove_filter( 'posts_join', [ $this, 'keyword_join_sku_meta' ], 10 );
		remove_filter( 'posts_where', [ $this, 'keyword_where_clause' ], 10 );
		remove_filter( 'posts_groupby', [ $this, 'keyword_groupby_clause' ], 10 );

		return $query;
	}

	public function title_exact_phrase_where_clause( $where, $query ) {
		global $wpdb;

		if ( ! $query->get( 'aaweb_query' ) || ! $query->get( 'aaweb_title_search' ) ) {
			return $where;
		}

		$search = trim( (string) $query->get( 'aaweb_title_search' ) );

		if ( '' === $search ) {
			return $where;
		}

		$search = preg_replace( '/\s+/u', ' ', $search );

		$like = '%' . $wpdb->esc_like( $search ) . '%';

		$where .= $wpdb->prepare(
			" AND {$wpdb->posts}.post_title LIKE %s",
			$like
		);

		return $where;
	}

	public function keyword_join_sku_meta( $join, $query ) {
		global $wpdb;

		if ( ! $query->get( 'aaweb_query' ) || ! $query->get( 'aaweb_keyword' ) ) {
			return $join;
		}

		if ( strpos( $join, 'aaweb_sku_meta' ) === false ) {
			$join .= " LEFT JOIN {$wpdb->postmeta} AS aaweb_sku_meta
					   ON ({$wpdb->posts}.ID = aaweb_sku_meta.post_id AND aaweb_sku_meta.meta_key = '_sku')";
		}

		return $join;
	}

	public function keyword_where_clause( $where, $query ) {
		global $wpdb;

		if ( ! $query->get( 'aaweb_query' ) || ! $query->get( 'aaweb_keyword' ) ) {
			return $where;
		}

		$keyword = trim( (string) $query->get( 'aaweb_keyword' ) );

		if ( '' === $keyword ) {
			return $where;
		}

		$like = '%' . $wpdb->esc_like( $keyword ) . '%';

		$where .= $wpdb->prepare(
			" AND (
				{$wpdb->posts}.post_title LIKE %s
				OR {$wpdb->posts}.post_excerpt LIKE %s
				OR {$wpdb->posts}.post_content LIKE %s
				OR aaweb_sku_meta.meta_value LIKE %s
			)",
			$like,
			$like,
			$like,
			$like
		);

		return $where;
	}

	public function keyword_groupby_clause( $groupby, $query ) {
		global $wpdb;

		if ( ! $query->get( 'aaweb_query' ) || ! $query->get( 'aaweb_keyword' ) ) {
			return $groupby;
		}

		return "{$wpdb->posts}.ID";
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'aaweb-bulk-product-attributes-for-woocommerce' ) );
		}

		$filters    = $this->get_current_filters();
		$query      = $this->run_products_query( $filters );
		$attributes = $this->get_store_attribute_taxonomies();
		$total      = isset( $query->found_posts ) ? (int) $query->found_posts : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice after a verified redirect.
		$updated_notice = isset( $_GET['aaweb_updated'] ) ? absint( $_GET['aaweb_updated'] ) : null;
		?>
		<div class="wrap aaweb-bulk-wrap">

			<div class="aaweb-hero">
				<div class="aaweb-hero-top">
					<div>
						<h1><?php esc_html_e( 'AAWEB Bulk Product Attributes', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></h1>
						<p><?php esc_html_e( 'Fast bulk assignment of WooCommerce global attributes with filters, product selection and safe saving.', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></p>
					</div>
					<div class="aaweb-hero-badge"><?php echo esc_html( $total ); ?> <?php esc_html_e( 'products', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></div>
				</div>
			</div>

			<?php if ( null !== $updated_notice ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php echo esc_html( sprintf( /* translators: %d: number of updated products. */ _n( '%d product updated.', '%d products updated.', $updated_notice, 'aaweb-bulk-product-attributes-for-woocommerce' ), $updated_notice ) ); ?></p>
				</div>
			<?php endif; ?>

			<?php $this->render_filters_form( $filters ); ?>

			<form method="post">
				<?php wp_nonce_field( 'aaweb_bulk_apply_action', 'aaweb_bulk_apply_nonce' ); ?>

				<input type="hidden" name="current_s" value="<?php echo esc_attr( $filters['s'] ); ?>">
				<input type="hidden" name="current_aaweb_keyword" value="<?php echo esc_attr( $filters['aaweb_keyword'] ); ?>">
				<input type="hidden" name="current_product_cat" value="<?php echo esc_attr( $filters['product_cat'] ); ?>">
				<input type="hidden" name="current_stock_status" value="<?php echo esc_attr( $filters['stock_status'] ); ?>">
				<input type="hidden" name="current_paged" value="<?php echo esc_attr( $filters['paged'] ); ?>">

				<?php $this->render_bulk_box(); ?>

				<div class="aaweb-selected-bar">
					<strong><span id="aaweb-selected-count">0</span> <?php esc_html_e( 'products selected', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></strong>
					<div class="aaweb-selected-actions">
						<label><input type="radio" name="aaweb_mode_shadow" value="append" checked> <?php esc_html_e( 'Append', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></label>
						<label><input type="radio" name="aaweb_mode_shadow" value="replace"> <?php esc_html_e( 'Replace', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></label>
						<button type="submit" name="aaweb_bulk_apply_submit" class="button button-primary"><?php esc_html_e( 'Apply to selected', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></button>
					</div>
				</div>

				<div class="aaweb-card aaweb-table-card">
					<table class="wp-list-table widefat fixed striped table-view-list posts aaweb-products-table">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column">
									<input type="checkbox" id="aaweb-check-all">
								</td>
								<th class="aaweb-col-image"><?php esc_html_e( 'Image', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></th>
								<th class="aaweb-col-name"><?php esc_html_e( 'Name', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></th>
								<th class="aaweb-col-sku">SKU</th>
								<th class="aaweb-col-price"><?php esc_html_e( 'Price', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></th>
								<th class="aaweb-col-stock"><?php esc_html_e( 'Stock', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></th>
								<th class="aaweb-col-categories"><?php esc_html_e( 'Categories', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?></th>

								<?php foreach ( $attributes as $taxonomy => $attribute ) : ?>
									<th class="aaweb-col-attribute"><?php echo esc_html( $attribute['label'] ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>

						<tbody>
							<?php if ( $query->have_posts() ) : ?>
								<?php while ( $query->have_posts() ) : $query->the_post(); ?>
									<?php
									$product_id = get_the_ID();
									$product    = wc_get_product( $product_id );

									if ( ! $product ) {
										continue;
									}

									$thumbnail = get_the_post_thumbnail( $product_id, [ 54, 54 ] );

									if ( ! $thumbnail && function_exists( 'wc_placeholder_img' ) ) {
										$thumbnail = wc_placeholder_img( [ 54, 54 ] );
									}
									?>

									<tr>
										<th scope="row" class="check-column">
											<input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( $product_id ); ?>">
										</th>

										<td class="aaweb-thumb"><?php echo $thumbnail ? wp_kses_post( $thumbnail ) : '<span class="aaweb-empty-value">—</span>'; ?></td>

										<td class="aaweb-product-title">
											<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>">
												<?php echo esc_html( get_the_title( $product_id ) ); ?>
											</a>
										</td>

										<td><?php echo esc_html( $product->get_sku() ?: '—' ); ?></td>

										<td><?php echo wp_kses_post( $product->get_price_html() ?: '—' ); ?></td>

										<td><span class="aaweb-stock <?php echo esc_attr( $this->get_stock_class( $product ) ); ?>"><?php echo esc_html( $this->get_stock_label( $product ) ); ?></span></td>

										<td><?php echo wp_kses_post( wc_get_product_category_list( $product_id, ', ' ) ?: '<span class="aaweb-empty-value">—</span>' ); ?></td>

										<?php foreach ( $attributes as $taxonomy => $attribute ) : ?>
											<td><?php echo wp_kses_post( $this->get_product_term_badges( $product_id, $taxonomy ) ); ?></td>
										<?php endforeach; ?>
									</tr>
								<?php endwhile; ?>

								<?php wp_reset_postdata(); ?>
							<?php else : ?>
								<tr>
									<td colspan="<?php echo esc_attr( 7 + count( $attributes ) ); ?>" class="aaweb-empty-row">
										<?php esc_html_e( 'No products found.', 'aaweb-bulk-product-attributes-for-woocommerce' ); ?>
									</td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>

					<?php
					$total_pages = (int) $query->max_num_pages;

					if ( $total_pages > 1 ) {
						$base_url = add_query_arg(
							[
								'post_type'    => 'product',
								'page'         => 'aaweb-bulk-product-attributes-for-woocommerce',
								's'            => $filters['s'],
								'aaweb_keyword'  => $filters['aaweb_keyword'],
								'product_cat'  => $filters['product_cat'],
								'stock_status' => $filters['stock_status'],
								'paged'        => '%#%',
							],
							admin_url( 'edit.php' )
						);

						echo '<div class="aaweb-pagination"><div class="tablenav-pages">';

						echo wp_kses_post(
							paginate_links(
								[
									'base'      => $base_url,
									'format'    => '',
									'current'   => max( 1, $filters['paged'] ),
									'total'     => $total_pages,
									'prev_text' => '&laquo;',
									'next_text' => '&raquo;',
								]
							)
						);

						echo '</div></div>';
					}
					?>
				</div>
			</form>
		</div>

		<?php
	}
}

add_action( 'plugins_loaded', function() {
	if ( class_exists( 'WooCommerce' ) ) {
		new AAWEB_Bulk_Product_Attributes_For_WooCommerce();
	}
}, 20 );
