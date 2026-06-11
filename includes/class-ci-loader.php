<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Loader {

	public function init(): void {
		$this->maybe_seed_new_options();

		( new CI_CPT() )->init();
		( new CI_Settings() )->init();
		( new CI_Invoice_Editor() )->init();
		( new CI_Client_Editor() )->init();
		( new CI_Ajax() )->init();
		( new CI_Time_Tracker() )->init();

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_notices',         [ $this, 'notices' ] );
		add_action( 'wp_dashboard_setup',    [ $this, 'register_dashboard_widget' ] );
		add_action( 'admin_post_ci_download_pdf',      [ $this, 'handle_pdf_download' ] );
		add_action( 'admin_post_ci_export_csv',        [ $this, 'handle_csv_export' ] );
		add_action( 'admin_post_ci_install_fpdf',      [ $this, 'handle_install_fpdf' ] );
		add_action( 'admin_post_ci_preview_invoice',   [ $this, 'handle_preview' ] );
	}

	// Seeds options added after initial activation so existing installs get defaults.
	private function maybe_seed_new_options(): void {
		$new_opts = [
			'ci_receipt_subject' => 'Payment Receipt — Invoice {invoice_number}',
			'ci_receipt_body'    => "Hi {client_name},\n\nThank you! We've received your payment of {amount_paid} for invoice {invoice_number}.\n\nPayment Date: {paid_date}\nPayment Method: {payment_method}\n\nWe appreciate your business!\n{company_name}",
		];
		foreach ( $new_opts as $key => $default ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $default );
			}
		}
	}

	public function enqueue( string $hook ): void {
		$screen    = get_current_screen();
		$post_type = $screen->post_type ?? '';
		$is_ci     = in_array( $post_type, [ 'ci_invoice', 'ci_client' ], true );
		$is_post   = in_array( $hook, [ 'post.php', 'post-new.php' ], true );
		$is_list   = ( $hook === 'edit.php' );
		$is_sett   = str_contains( $hook, 'clean-invoices-settings' );
		$is_report = str_contains( $hook, 'clean-invoices-reports' );
		$is_tracker = str_contains( $hook, 'clean-invoices-time-tracker' );

		if ( ! ( ( $is_post || $is_list ) && $is_ci ) && ! $is_sett && ! $is_report && ! $is_tracker ) return;

		if ( $is_tracker ) {
			wp_enqueue_style( 'ci-admin', CI_URL . 'assets/css/ci-admin.css', [], CI_VERSION );
			wp_enqueue_script( 'ci-time-tracker', CI_URL . 'assets/js/ci-time-tracker.js', [ 'jquery' ], CI_VERSION, true );
			wp_localize_script( 'ci-time-tracker', 'ci', [
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'ci_nonce' ),
				'post_id'  => 0,
			] );
			return;
		}

		if ( $is_sett ) wp_enqueue_media();

		wp_enqueue_style( 'ci-admin', CI_URL . 'assets/css/ci-admin.css', [], CI_VERSION );
		wp_enqueue_script( 'ci-admin', CI_URL . 'assets/js/ci-admin.js', [ 'jquery' ], CI_VERSION, true );
		wp_localize_script( 'ci-admin', 'ci', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ci_nonce' ),
			'post_id'  => get_the_ID() ?: 0,
		] );
	}

	public function notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( ! file_exists( CI_DIR . 'lib/fpdf/fpdf.php' ) || ! file_exists( CI_DIR . 'lib/fpdf/font/helveticab.php' ) ) {
			$url = wp_nonce_url( admin_url( 'admin-post.php?action=ci_install_fpdf' ), 'ci_install_fpdf' );
			echo '<div class="notice notice-warning"><p><strong>Clean Invoices:</strong> PDF library not installed — PDF and email features unavailable. <a href="' . esc_url( $url ) . '">Install automatically</a>.</p></div>';
		}

		$status = sanitize_key( $_GET['ci_notice'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice flag for display only.
		if ( $status === 'sent' )      echo '<div class="notice notice-success is-dismissible"><p>Invoice sent successfully.</p></div>';
		if ( $status === 'send_fail' ) echo '<div class="notice notice-error is-dismissible"><p>Failed to send invoice. Check your email settings.</p></div>';
		if ( $status === 'paid' )      echo '<div class="notice notice-success is-dismissible"><p>Invoice marked as paid.</p></div>';
		if ( $status === 'fpdf_ok' )   echo '<div class="notice notice-success is-dismissible"><p>PDF library installed successfully.</p></div>';
		if ( $status === 'fpdf_fail' ) echo '<div class="notice notice-error is-dismissible"><p>Could not download PDF library. Check server connectivity.</p></div>';
	}

	public function handle_pdf_download(): void {
		$post_id = intval( $_GET['post_id'] ?? 0 );
		check_admin_referer( 'ci_pdf_' . $post_id );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );

		try {
			$bytes    = ( new CI_PDF() )->generate( $post_id );
			$num      = get_post_meta( $post_id, '_ci_invoice_number', true );
			$filename = sanitize_file_name( ( $num ?: 'invoice' ) . '.pdf' );
			header( 'Content-Type: application/pdf' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Cache-Control: private, max-age=0, must-revalidate' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $bytes;
		} catch ( Exception $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
		exit;
	}

	public function handle_csv_export(): void {
		check_admin_referer( 'ci_export_csv' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		$year = intval( $_GET['year'] ?? gmdate( 'Y' ) );
		( new CI_Exporter() )->output( $year );
		exit;
	}

	public function handle_preview(): void {
		$post_id = intval( $_GET['post_id'] ?? 0 );
		check_admin_referer( 'ci_preview_' . $post_id );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) wp_die( 'Invalid invoice.' );
		include CI_DIR . 'templates/invoice-preview.php';
		exit;
	}

	public function handle_install_fpdf(): void {
		check_admin_referer( 'ci_install_fpdf' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
		$ok = ci_download_fpdf();
		wp_safe_redirect( admin_url( 'edit.php?post_type=ci_invoice&ci_notice=' . ( $ok ? 'fpdf_ok' : 'fpdf_fail' ) ) );
		exit;
	}

	public function register_dashboard_widget(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		wp_add_dashboard_widget(
			'ci_revenue_widget',
			'Invoices — ' . gmdate( 'Y' ) . ' Revenue',
			[ $this, 'render_dashboard_widget' ]
		);
	}

	public function render_dashboard_widget(): void {
		$year = (int) gmdate( 'Y' );

		$invoices = get_posts( [
			'post_type'      => 'ci_invoice',
			'posts_per_page' => -1,
			'post_status'    => [ 'publish', 'draft' ],
			'meta_query'     => [ [
				'key'     => '_ci_invoice_date',
				'value'   => [ $year . '-01-01', $year . '-12-31' ],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			] ],
		] );

		$total_invoiced  = 0.0;
		$total_collected = 0.0;
		$unpaid          = [];

		foreach ( $invoices as $inv ) {
			$id       = $inv->ID;
			$total    = (float) get_post_meta( $id, '_ci_total',    true );
			$status   = get_post_meta( $id, '_ci_status',           true ) ?: 'draft';
			$payments = json_decode( get_post_meta( $id, '_ci_payments', true ) ?: '[]', true );
			$amt_paid = array_sum( array_column( $payments, 'amount' ) );
			$balance  = ( $status === 'paid' && empty( $payments ) ) ? 0.0 : max( 0.0, $total - $amt_paid );

			$total_invoiced  += $total;
			$total_collected += ( $total - $balance );

			if ( $balance > 0 ) {
				$due      = get_post_meta( $id, '_ci_due_date', true );
				$overdue  = ( $due && strtotime( $due ) < strtotime( 'today' ) )
					? (int) floor( ( strtotime( 'today' ) - strtotime( $due ) ) / DAY_IN_SECONDS )
					: 0;
				$client   = (int) get_post_meta( $id, '_ci_client_id', true );
				$unpaid[] = [
					'id'      => $id,
					'number'  => get_post_meta( $id, '_ci_invoice_number', true ),
					'client'  => $client ? get_the_title( $client ) : '—',
					'balance' => $balance,
					'status'  => $status,
					'overdue' => $overdue,
				];
			}
		}

		usort( $unpaid, fn( $a, $b ) => $b['overdue'] <=> $a['overdue'] );

		$outstanding  = $total_invoiced - $total_collected;
		$reports_url  = admin_url( 'edit.php?post_type=ci_invoice&page=clean-invoices-reports' );
		$new_inv_url  = admin_url( 'post-new.php?post_type=ci_invoice' );

		$fmt = fn( float $n ): string => '$' . number_format( $n, 2 );
		?>
		<style>
		#ci_revenue_widget .ci-w-stats {
			display: grid;
			grid-template-columns: repeat(3,1fr);
			gap: 1px;
			background: #e0e0e0;
			border: 1px solid #e0e0e0;
			border-radius: 4px;
			overflow: hidden;
			margin-bottom: 16px;
		}
		#ci_revenue_widget .ci-w-stat {
			background: #fff;
			padding: 12px 10px;
			text-align: center;
		}
		#ci_revenue_widget .ci-w-stat-label {
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: .05em;
			color: #646970;
			margin-bottom: 4px;
		}
		#ci_revenue_widget .ci-w-stat-value {
			font-size: 16px;
			font-weight: 700;
			color: #1e1e1e;
		}
		#ci_revenue_widget .ci-w-stat-value.green { color: #00a32a; }
		#ci_revenue_widget .ci-w-stat-value.amber { color: #b45309; }
		#ci_revenue_widget .ci-w-unpaid-title {
			font-size: 11px;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: .05em;
			color: #646970;
			margin-bottom: 8px;
		}
		#ci_revenue_widget .ci-w-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 5px 0;
			border-bottom: 1px solid #f0f0f0;
			font-size: 12px;
			gap: 8px;
		}
		#ci_revenue_widget .ci-w-row:last-child { border-bottom: none; }
		#ci_revenue_widget .ci-w-inv { font-weight: 600; white-space: nowrap; }
		#ci_revenue_widget .ci-w-client {
			flex: 1;
			color: #50575e;
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}
		#ci_revenue_widget .ci-w-balance { font-weight: 700; white-space: nowrap; color: #b45309; }
		#ci_revenue_widget .ci-w-overdue {
			font-size: 10px;
			font-weight: 700;
			background: #fee2e2;
			color: #991b1b;
			padding: 1px 5px;
			border-radius: 8px;
			white-space: nowrap;
		}
		#ci_revenue_widget .ci-w-badge {
			font-size: 10px;
			font-weight: 700;
			text-transform: uppercase;
			padding: 1px 6px;
			border-radius: 8px;
			white-space: nowrap;
		}
		#ci_revenue_widget .ci-w-badge-partial { background:#fef3c7; color:#92400e; }
		#ci_revenue_widget .ci-w-badge-sent    { background:#dbeafe; color:#1e40af; }
		#ci_revenue_widget .ci-w-badge-draft   { background:#e0e0e0; color:#50575e; }
		#ci_revenue_widget .ci-w-footer {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-top: 14px;
			padding-top: 12px;
			border-top: 1px solid #e0e0e0;
			font-size: 12px;
		}
		#ci_revenue_widget .ci-w-empty {
			color: #646970;
			font-size: 13px;
			padding: 8px 0 4px;
		}
		</style>

		<!-- Stats -->
		<div class="ci-w-stats">
			<div class="ci-w-stat">
				<div class="ci-w-stat-label">Invoiced</div>
				<div class="ci-w-stat-value"><?php echo esc_html( $fmt( $total_invoiced ) ); ?></div>
			</div>
			<div class="ci-w-stat">
				<div class="ci-w-stat-label">Collected</div>
				<div class="ci-w-stat-value green"><?php echo esc_html( $fmt( $total_collected ) ); ?></div>
			</div>
			<div class="ci-w-stat">
				<div class="ci-w-stat-label">Outstanding</div>
				<div class="ci-w-stat-value <?php echo $outstanding > 0 ? 'amber' : ''; ?>"><?php echo esc_html( $fmt( $outstanding ) ); ?></div>
			</div>
		</div>

		<?php if ( empty( $unpaid ) ) : ?>
			<p class="ci-w-empty">All invoices paid. Great work!</p>
		<?php else : ?>
			<div class="ci-w-unpaid-title">Outstanding Invoices</div>
			<?php
			$badge_map = [ 'partial' => 'partial', 'sent' => 'sent', 'draft' => 'draft', 'overdue' => 'overdue' ];
			foreach ( array_slice( $unpaid, 0, 5 ) as $row ) :
				$badge_class = 'ci-w-badge-' . ( $badge_map[ $row['status'] ] ?? 'draft' );
			?>
			<div class="ci-w-row">
				<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>" class="ci-w-inv"><?php echo esc_html( $row['number'] ); ?></a>
				<span class="ci-w-client"><?php echo esc_html( $row['client'] ); ?></span>
				<span class="ci-w-balance"><?php echo esc_html( $fmt( $row['balance'] ) ); ?></span>
				<?php if ( $row['overdue'] > 0 ) : ?>
					<span class="ci-w-overdue"><?php echo esc_html( $row['overdue'] ); ?>d</span>
				<?php else : ?>
					<span class="ci-w-badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( ucfirst( $row['status'] ) ); ?></span>
				<?php endif; ?>
			</div>
			<?php endforeach; ?>
			<?php if ( count( $unpaid ) > 5 ) : ?>
				<p style="font-size:11px;color:#646970;margin:8px 0 0;text-align:center;">
					+<?php echo esc_html( count( $unpaid ) - 5 ); ?> more —
					<a href="<?php echo esc_url( $reports_url ); ?>">view all</a>
				</p>
			<?php endif; ?>
		<?php endif; ?>

		<div class="ci-w-footer">
			<a href="<?php echo esc_url( $new_inv_url ); ?>" class="button button-small">+ New Invoice</a>
			<a href="<?php echo esc_url( $reports_url ); ?>">View full report →</a>
		</div>
		<?php
	}
}
