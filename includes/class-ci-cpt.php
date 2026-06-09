<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_CPT {

	public function init(): void {
		add_action( 'init',                  [ $this, 'register' ] );
		add_action( 'admin_menu',            [ $this, 'submenus' ] );

		// Invoice list columns
		add_filter( 'manage_ci_invoice_posts_columns',       [ $this, 'invoice_columns' ] );
		add_action( 'manage_ci_invoice_posts_custom_column', [ $this, 'invoice_column_data' ], 10, 2 );
		add_filter( 'manage_edit-ci_invoice_sortable_columns', [ $this, 'invoice_sortable_columns' ] );

		// Client list columns
		add_filter( 'manage_ci_client_posts_columns',       [ $this, 'client_columns' ] );
		add_action( 'manage_ci_client_posts_custom_column', [ $this, 'client_column_data' ], 10, 2 );

		// Year + status filters for invoice list
		add_action( 'restrict_manage_posts', [ $this, 'invoice_filters' ] );
		add_filter( 'parse_query',           [ $this, 'apply_invoice_filters' ] );

		// Row actions
		add_filter( 'post_row_actions', [ $this, 'invoice_row_actions' ], 10, 2 );

		// Remove default title column and add status badges
		add_filter( 'post_class', [ $this, 'invoice_row_class' ], 10, 3 );
	}

	public function register(): void {
		register_post_type( 'ci_invoice', [
			'labels'        => [
				'name'          => 'Invoices',
				'singular_name' => 'Invoice',
				'add_new_item'  => 'New Invoice',
				'edit_item'     => 'Edit Invoice',
				'all_items'     => 'All Invoices',
				'search_items'  => 'Search Invoices',
			],
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_icon'     => 'dashicons-media-spreadsheet',
			'menu_position' => 30,
			'supports'      => [ 'title' ],
			'capability_type' => 'post',
		] );

		register_post_type( 'ci_client', [
			'labels'       => [
				'name'          => 'Clients',
				'singular_name' => 'Client',
				'add_new_item'  => 'Add Client',
				'edit_item'     => 'Edit Client',
				'all_items'     => 'All Clients',
				'search_items'  => 'Search Clients',
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=ci_invoice',
			'supports'     => [ 'title' ],
			'capability_type' => 'post',
		] );
	}

	public function submenus(): void {
		add_submenu_page(
			'edit.php?post_type=ci_invoice',
			'Sales & Revenue Report',
			'Reports',
			'manage_options',
			'clean-invoices-reports',
			fn() => include CI_DIR . 'templates/dashboard-page.php'
		);
		add_submenu_page(
			'edit.php?post_type=ci_invoice',
			'Clean Invoices Settings',
			'Settings',
			'manage_options',
			'clean-invoices-settings',
			fn() => include CI_DIR . 'templates/settings-page.php'
		);
	}

	// ---------- Invoice columns ----------

	public function invoice_columns( array $cols ): array {
		return [
			'cb'              => $cols['cb'],
			'title'           => 'Invoice #',
			'ci_client'       => 'Client',
			'ci_date'         => 'Date',
			'ci_due'          => 'Due',
			'ci_total'        => 'Total',
			'ci_status'       => 'Status',
			'ci_actions'      => 'Actions',
		];
	}

	public function invoice_column_data( string $col, int $id ): void {
		switch ( $col ) {
			case 'ci_client':
				$cid  = get_post_meta( $id, '_ci_client_id', true );
				$name = $cid ? get_the_title( $cid ) : '—';
				echo esc_html( $name );
				break;
			case 'ci_date':
				$d = get_post_meta( $id, '_ci_invoice_date', true );
				echo $d ? esc_html( date( 'M j, Y', strtotime( $d ) ) ) : '—';
				break;
			case 'ci_due':
				$d = get_post_meta( $id, '_ci_due_date', true );
				echo $d ? esc_html( date( 'M j, Y', strtotime( $d ) ) ) : '—';
				break;
			case 'ci_total':
				$t = get_post_meta( $id, '_ci_total', true );
				echo $t !== '' ? '$' . esc_html( number_format( (float) $t, 2 ) ) : '—';
				break;
			case 'ci_status':
				$s      = get_post_meta( $id, '_ci_status', true ) ?: 'draft';
				$labels = [ 'draft' => 'Draft', 'sent' => 'Sent', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue' ];
				echo '<span class="ci-badge ci-badge--' . esc_attr( $s ) . '">' . esc_html( $labels[ $s ] ?? $s ) . '</span>';
				break;
			case 'ci_actions':
				$num     = get_post_meta( $id, '_ci_invoice_number', true );
				$pdf     = wp_nonce_url( admin_url( 'admin-post.php?action=ci_download_pdf&post_id=' . $id ), 'ci_pdf_' . $id );
				$s_now   = get_post_meta( $id, '_ci_status', true ) ?: 'draft';
				$pmts    = json_decode( get_post_meta( $id, '_ci_payments', true ) ?: '[]', true );
				$inv_tot = (float) get_post_meta( $id, '_ci_total', true );
				$paid    = array_sum( array_column( $pmts, 'amount' ) );
				// Legacy invoices marked paid without payment records → treat as fully paid
				$bal     = ( $s_now === 'paid' && empty( $pmts ) ) ? 0.0 : max( 0.0, $inv_tot - $paid );
				echo '<a href="' . esc_url( $pdf ) . '" class="button button-small">PDF</a> ';
				echo '<button class="button button-small ci-send-btn" data-id="' . esc_attr( $id ) . '">Send</button> ';
				if ( $bal > 0 ) {
					echo '<button class="button button-small ci-paid-btn" data-id="' . esc_attr( $id ) . '" data-balance="' . esc_attr( number_format( $bal, 2 ) ) . '">Mark Paid</button>';
				}
				break;
		}
	}

	public function invoice_sortable_columns( array $cols ): array {
		$cols['ci_date']  = 'ci_date';
		$cols['ci_total'] = 'ci_total';
		return $cols;
	}

	// ---------- Client columns ----------

	public function client_columns( array $cols ): array {
		return [
			'cb'            => $cols['cb'],
			'title'         => 'Name',
			'ci_company'    => 'Company',
			'ci_email'      => 'Email',
			'ci_phone'      => 'Phone',
			'ci_invoices'   => 'Invoices',
		];
	}

	public function client_column_data( string $col, int $id ): void {
		switch ( $col ) {
			case 'ci_company':
				echo esc_html( get_post_meta( $id, '_ci_company', true ) ?: '—' );
				break;
			case 'ci_email':
				$e = get_post_meta( $id, '_ci_email', true );
				echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '—';
				break;
			case 'ci_phone':
				echo esc_html( get_post_meta( $id, '_ci_phone', true ) ?: '—' );
				break;
			case 'ci_invoices':
				$count = count( get_posts( [
					'post_type'      => 'ci_invoice',
					'posts_per_page' => -1,
					'post_status'    => 'any',
					'meta_key'       => '_ci_client_id',
					'meta_value'     => $id,
					'fields'         => 'ids',
				] ) );
				$url = admin_url( 'edit.php?post_type=ci_invoice&ci_client_id=' . $id );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( $count ) . '</a>';
				break;
		}
	}

	// ---------- Filters ----------

	public function invoice_filters( string $post_type ): void {
		if ( $post_type !== 'ci_invoice' ) return;

		$current_year   = intval( $_GET['ci_year'] ?? 0 );
		$current_status = sanitize_key( $_GET['ci_status'] ?? '' );

		// Year dropdown
		$years = $this->get_invoice_years();
		echo '<select name="ci_year"><option value="">All Years</option>';
		foreach ( $years as $y ) {
			echo '<option value="' . esc_attr( $y ) . '"' . selected( $current_year, $y, false ) . '>' . esc_html( $y ) . '</option>';
		}
		echo '</select>';

		// Status dropdown
		echo '<select name="ci_status"><option value="">All Statuses</option>';
		foreach ( [ 'draft' => 'Draft', 'sent' => 'Sent', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue' ] as $val => $label ) {
			echo '<option value="' . esc_attr( $val ) . '"' . selected( $current_status, $val, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';

		// CSV export button
		$export_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=ci_export_csv&year=' . ( $current_year ?: gmdate( 'Y' ) ) ),
			'ci_export_csv'
		);
		echo '<a href="' . esc_url( $export_url ) . '" class="button" style="margin-left:4px;">Export CSV (' . esc_html( $current_year ?: gmdate( 'Y' ) ) . ')</a>';
	}

	public function apply_invoice_filters( WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) return;
		if ( ( $query->query['post_type'] ?? '' ) !== 'ci_invoice' ) return;

		$meta = [];

		if ( ! empty( $_GET['ci_year'] ) ) {
			$y    = intval( $_GET['ci_year'] );
			$meta[] = [
				'key'     => '_ci_invoice_date',
				'value'   => [ $y . '-01-01', $y . '-12-31' ],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			];
		}
		if ( ! empty( $_GET['ci_status'] ) ) {
			$meta[] = [
				'key'   => '_ci_status',
				'value' => sanitize_key( $_GET['ci_status'] ),
			];
		}
		if ( ! empty( $_GET['ci_client_id'] ) ) {
			$meta[] = [
				'key'   => '_ci_client_id',
				'value' => intval( $_GET['ci_client_id'] ),
			];
		}

		if ( $meta ) {
			$query->set( 'meta_query', array_merge( [ 'relation' => 'AND' ], $meta ) );
		}
	}

	public function invoice_row_actions( array $actions, WP_Post $post ): array {
		if ( $post->post_type !== 'ci_invoice' ) return $actions;
		unset( $actions['view'], $actions['inline hide-if-no-js'] );
		return $actions;
	}

	public function invoice_row_class( array $classes, $class, int $id ): array {
		if ( get_post_type( $id ) === 'ci_invoice' ) {
			$s = get_post_meta( $id, '_ci_status', true ) ?: 'draft';
			$classes[] = 'ci-row--' . sanitize_html_class( $s );
		}
		return $classes;
	}

	private function get_invoice_years(): array {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT YEAR(meta_value) FROM {$wpdb->postmeta}
			 JOIN {$wpdb->posts} ON post_id = ID
			 WHERE meta_key = %s AND post_type = %s AND post_status != 'trash'
			 ORDER BY 1 DESC",
			'_ci_invoice_date', 'ci_invoice'
		) );
		return array_filter( $rows );
	}
}
