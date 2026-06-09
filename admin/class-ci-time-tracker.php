<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Time_Tracker {

	public function init(): void {
		add_action( 'admin_menu',                       [ $this, 'add_menu' ] );
		add_action( 'admin_bar_menu',                   [ $this, 'admin_bar_node' ],        100 );
		add_action( 'admin_enqueue_scripts',            [ $this, 'enqueue_adminbar_assets' ] );
		add_action( 'wp_enqueue_scripts',               [ $this, 'enqueue_adminbar_assets' ] );
		add_action( 'wp_ajax_ci_tt_save_entry',         [ $this, 'save_entry' ] );
		add_action( 'wp_ajax_ci_tt_update_entry',       [ $this, 'update_entry' ] );
		add_action( 'wp_ajax_ci_tt_delete_entry',       [ $this, 'delete_entry' ] );
		add_action( 'wp_ajax_ci_tt_add_to_invoice',     [ $this, 'add_to_invoice' ] );
		add_action( 'wp_ajax_ci_tt_get_invoices',       [ $this, 'get_invoices' ] );
		add_action( 'wp_ajax_ci_tt_merge_entries',      [ $this, 'merge_entries' ] );
	}

	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=ci_invoice',
			'Time Tracker',
			'Time Tracker',
			'manage_options',
			'clean-invoices-time-tracker',
			[ $this, 'render_page' ]
		);
	}

	public function admin_bar_node( WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) return;

		$tracker_url = admin_url( 'edit.php?post_type=ci_invoice&page=clean-invoices-time-tracker' );

		$wp_admin_bar->add_node( [
			'id'    => 'ci-timer',
			'title' => '<span id="ci-ab-elapsed">Time Tracker</span>',
			'href'  => $tracker_url,
		] );

		$wp_admin_bar->add_node( [
			'parent' => 'ci-timer',
			'id'     => 'ci-timer-desc',
			'title'  => '<span id="ci-ab-desc">No timer running</span>',
			'href'   => false,
			'meta'   => [ 'class' => 'ci-ab-desc-item' ],
		] );

		$wp_admin_bar->add_node( [
			'parent' => 'ci-timer',
			'id'     => 'ci-timer-stop',
			'title'  => '&#9632; Stop &amp; Save',
			'href'   => '#',
		] );

		$wp_admin_bar->add_node( [
			'parent' => 'ci-timer',
			'id'     => 'ci-timer-open',
			'title'  => 'Open Time Tracker &rarr;',
			'href'   => $tracker_url,
		] );
	}

	public function enqueue_adminbar_assets(): void {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) return;

		wp_enqueue_style(
			'ci-adminbar-timer',
			CI_URL . 'assets/css/ci-adminbar.css',
			[],
			CI_VERSION
		);
		wp_enqueue_script(
			'ci-adminbar-timer',
			CI_URL . 'assets/js/ci-adminbar-timer.js',
			[ 'jquery' ],
			CI_VERSION,
			true
		);
		wp_localize_script( 'ci-adminbar-timer', 'ciAdminbar', [
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'ci_nonce' ),
		] );
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) return;
		$entries = array_reverse( $this->get_entries() );
		?>
		<div class="wrap">
			<h1>Time Tracker</h1>

			<div class="ci-tt-panel">
				<div class="ci-tt-panel-header">Active Timer</div>
				<div class="ci-tt-timer-row">
					<input type="text" id="ci-tt-description" placeholder="What are you working on?" class="regular-text">
					<span class="ci-tt-rate-wrap">
						<span class="ci-tt-dollar">$</span>
						<input type="number" id="ci-tt-rate" placeholder="0.00" min="0" step="0.01" class="small-text">
						<span class="ci-tt-per-hour">/hr</span>
					</span>
				</div>
				<div class="ci-tt-clock-row">
					<span id="ci-tt-clock">00:00:00</span>
				</div>
				<div class="ci-tt-btn-row">
					<button type="button" id="ci-tt-start" class="button button-primary button-large">&#9654; Start Timer</button>
					<button type="button" id="ci-tt-stop" class="button button-large ci-tt-stop-btn" style="display:none;">&#9632; Stop &amp; Save</button>
				</div>
				<div id="ci-tt-status" class="ci-tt-status"></div>
			</div>

			<h2 style="margin-top:24px;">Time Log</h2>

			<?php if ( empty( $entries ) ) : ?>
				<p style="color:#646970;">No time entries yet. Start your first timer above.</p>
			<?php else : ?>
			<div id="ci-tt-bulk-bar" style="display:none;margin-bottom:8px;">
				<button type="button" id="ci-tt-merge-btn" class="button">Merge Selected</button>
			</div>
			<table class="wp-list-table widefat fixed striped ci-tt-log-table">
				<thead>
					<tr>
						<th style="width:28px;text-align:center;"><input type="checkbox" id="ci-tt-select-all" title="Select all"></th>
						<th style="width:110px;">Date</th>
						<th>Description</th>
						<th style="width:70px;text-align:right;">Hours</th>
						<th style="width:80px;text-align:right;">Rate</th>
						<th style="width:90px;text-align:right;">Amount</th>
						<th style="width:240px;">Invoice</th>
						<th style="width:100px;"></th>
					</tr>
				</thead>
				<tbody id="ci-tt-log-body">
				<?php foreach ( $entries as $entry ) : ?>
					<tr data-id="<?php echo esc_attr( $entry['id'] ); ?>">
						<td style="text-align:center;">
							<?php if ( empty( $entry['invoice_id'] ) ) : ?>
								<input type="checkbox" class="ci-tt-check" data-id="<?php echo esc_attr( $entry['id'] ); ?>">
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( gmdate( 'M j, Y', strtotime( $entry['date'] ) ) ); ?></td>
						<td class="ci-tt-cell-desc"><?php echo esc_html( $entry['description'] ); ?></td>
						<td class="ci-tt-cell-hours" style="text-align:right;"><?php echo esc_html( number_format( (float) $entry['hours'], 2 ) ); ?></td>
						<td class="ci-tt-cell-rate" style="text-align:right;">$<?php echo esc_html( number_format( (float) $entry['rate'], 2 ) ); ?></td>
						<td class="ci-tt-cell-amount" style="text-align:right;">$<?php echo esc_html( number_format( (float) $entry['amount'], 2 ) ); ?></td>
						<td class="ci-tt-invoice-cell">
							<?php if ( ! empty( $entry['invoice_id'] ) ) : ?>
								<?php $inv_num = get_post_meta( (int) $entry['invoice_id'], '_ci_invoice_number', true ); ?>
								<a href="<?php echo esc_url( (string) get_edit_post_link( (int) $entry['invoice_id'] ) ); ?>">
									<?php echo esc_html( $inv_num ?: 'Invoice #' . $entry['invoice_id'] ); ?>
								</a>
							<?php else : ?>
								<button type="button" class="button button-small ci-tt-add-invoice-btn" data-id="<?php echo esc_attr( $entry['id'] ); ?>">Add to Invoice</button>
								<span class="ci-tt-invoice-select" style="display:none;">
									<select class="ci-tt-invoice-dropdown"></select>
									<span class="ci-tt-invoice-btns">
										<button type="button" class="button button-small button-primary ci-tt-confirm-invoice">Add</button>
										<button type="button" class="button button-small ci-tt-cancel-invoice">Cancel</button>
									</span>
								</span>
							<?php endif; ?>
						</td>
						<td class="ci-tt-action-cell">
							<button type="button" class="button button-small ci-tt-edit-btn" data-id="<?php echo esc_attr( $entry['id'] ); ?>">Edit</button>
							<button type="button" class="button-link-delete ci-tt-delete-btn" data-id="<?php echo esc_attr( $entry['id'] ); ?>">Delete</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save_entry(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
		$rate        = floatval( $_POST['rate'] ?? 0 );
		$start       = intval( $_POST['start'] ?? 0 );
		$end         = intval( $_POST['end'] ?? time() );
		$duration    = max( 1, $end - $start );
		$hours       = round( $duration / 3600, 2 );
		$amount      = round( $hours * $rate, 2 );

		$entry = [
			'id'          => uniqid( 'tt_' ),
			'description' => $description,
			'rate'        => $rate,
			'start'       => $start,
			'end'         => $end,
			'duration'    => $duration,
			'hours'       => $hours,
			'amount'      => $amount,
			'date'        => gmdate( 'Y-m-d', $end ),
			'invoice_id'  => null,
		];

		$entries   = $this->get_entries();
		$entries[] = $entry;
		update_option( 'ci_time_entries', wp_json_encode( $entries ), false );

		wp_send_json_success( $entry );
	}

	public function update_entry(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$id          = sanitize_text_field( wp_unslash( $_POST['entry_id']    ?? '' ) );
		$description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
		$hours       = round( floatval( $_POST['hours'] ?? 0 ), 2 );
		$rate        = round( floatval( $_POST['rate']  ?? 0 ), 2 );
		$amount      = round( $hours * $rate, 2 );

		if ( $hours <= 0 ) wp_send_json_error( 'Hours must be greater than zero.' );

		$entries = $this->get_entries();
		$found   = false;
		$entries = array_map( function ( $e ) use ( $id, $description, $hours, $rate, $amount, &$found ) {
			if ( ( $e['id'] ?? '' ) === $id ) {
				$found            = true;
				$e['description'] = $description;
				$e['hours']       = $hours;
				$e['rate']        = $rate;
				$e['amount']      = $amount;
			}
			return $e;
		}, $entries );

		if ( ! $found ) wp_send_json_error( 'Entry not found.' );

		update_option( 'ci_time_entries', wp_json_encode( $entries ), false );

		wp_send_json_success( [
			'description' => $description,
			'hours'       => $hours,
			'rate'        => $rate,
			'amount'      => $amount,
		] );
	}

	public function delete_entry(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$id      = sanitize_text_field( wp_unslash( $_POST['entry_id'] ?? '' ) );
		$entries = $this->get_entries();
		$entries = array_values( array_filter( $entries, fn( $e ) => ( $e['id'] ?? '' ) !== $id ) );
		update_option( 'ci_time_entries', wp_json_encode( $entries ), false );

		wp_send_json_success( 'Deleted.' );
	}

	public function add_to_invoice(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$entry_id   = sanitize_text_field( wp_unslash( $_POST['entry_id']   ?? '' ) );
		$invoice_id = intval( $_POST['invoice_id'] ?? 0 );

		if ( ! $invoice_id || get_post_type( $invoice_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$entries = $this->get_entries();
		$entry   = null;
		foreach ( $entries as $e ) {
			if ( ( $e['id'] ?? '' ) === $entry_id ) {
				$entry = $e;
				break;
			}
		}

		if ( ! $entry ) wp_send_json_error( 'Entry not found.' );

		$items   = json_decode( get_post_meta( $invoice_id, '_ci_line_items', true ) ?: '[]', true );
		$items[] = [
			'description' => $entry['description'],
			'detail'      => number_format( (float) $entry['hours'], 2 ) . ' hrs @ $' . number_format( (float) $entry['rate'], 2 ) . '/hr — ' . gmdate( 'M j, Y', strtotime( $entry['date'] ) ),
			'quantity'    => (float) $entry['hours'],
			'rate'        => (float) $entry['rate'],
			'amount'      => (float) $entry['amount'],
		];
		update_post_meta( $invoice_id, '_ci_line_items', wp_json_encode( $items ) );

		$subtotal = array_sum( array_column( $items, 'amount' ) );
		$tax_rate = (float) get_post_meta( $invoice_id, '_ci_tax_rate', true );
		$shipping = (float) get_post_meta( $invoice_id, '_ci_shipping', true );
		$tax      = $subtotal * ( $tax_rate / 100 );
		$total    = $subtotal + $tax + $shipping;
		update_post_meta( $invoice_id, '_ci_subtotal',   $subtotal );
		update_post_meta( $invoice_id, '_ci_tax_amount', $tax );
		update_post_meta( $invoice_id, '_ci_total',      $total );

		$entries = array_map( function ( $e ) use ( $entry_id, $invoice_id ) {
			if ( ( $e['id'] ?? '' ) === $entry_id ) $e['invoice_id'] = $invoice_id;
			return $e;
		}, $entries );
		update_option( 'ci_time_entries', wp_json_encode( $entries ), false );

		$inv_num  = get_post_meta( $invoice_id, '_ci_invoice_number', true );
		$edit_url = get_edit_post_link( $invoice_id );

		wp_send_json_success( [
			'inv_num'  => $inv_num ?: 'Invoice #' . $invoice_id,
			'edit_url' => $edit_url,
		] );
	}

	public function get_invoices(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$posts = get_posts( [
			'post_type'      => 'ci_invoice',
			'posts_per_page' => -1,
			'post_status'    => [ 'publish', 'draft' ],
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$invoices = array_map( function ( $p ) {
			$num    = get_post_meta( $p->ID, '_ci_invoice_number', true );
			$status = get_post_meta( $p->ID, '_ci_status', true ) ?: 'draft';
			$client = (int) get_post_meta( $p->ID, '_ci_client_id', true );
			$label  = ( $num ?: 'Draft' )
				. ( $client ? ' — ' . get_the_title( $client ) : '' )
				. ' (' . ucfirst( $status ) . ')';
			return [ 'id' => $p->ID, 'label' => $label ];
		}, $posts );

		wp_send_json_success( $invoices );
	}

	public function merge_entries(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$ids_raw     = sanitize_text_field( wp_unslash( $_POST['entry_ids']   ?? '[]' ) );
		$description = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
		$hours       = round( floatval( $_POST['hours'] ?? 0 ), 2 );
		$rate        = round( floatval( $_POST['rate']  ?? 0 ), 2 );
		$amount      = round( $hours * $rate, 2 );

		$ids = json_decode( $ids_raw, true );
		if ( ! is_array( $ids ) || count( $ids ) < 2 ) wp_send_json_error( 'Select at least 2 entries to merge.' );
		if ( $hours <= 0 ) wp_send_json_error( 'Hours must be greater than zero.' );

		$entries   = $this->get_entries();
		$to_merge  = [];
		$remaining = [];

		foreach ( $entries as $e ) {
			if ( in_array( $e['id'] ?? '', $ids, true ) ) {
				if ( ! empty( $e['invoice_id'] ) ) wp_send_json_error( 'Cannot merge entries already added to an invoice.' );
				$to_merge[] = $e;
			} else {
				$remaining[] = $e;
			}
		}

		if ( count( $to_merge ) < 2 ) wp_send_json_error( 'Could not find the selected entries.' );

		// Use the most recent entry's date
		usort( $to_merge, fn( $a, $b ) => strtotime( $b['date'] ) <=> strtotime( $a['date'] ) );
		$date = $to_merge[0]['date'];

		$merged = [
			'id'          => uniqid( 'tt_' ),
			'description' => $description,
			'rate'        => $rate,
			'start'       => 0,
			'end'         => (int) strtotime( $date ),
			'duration'    => (int) round( $hours * 3600 ),
			'hours'       => $hours,
			'amount'      => $amount,
			'date'        => $date,
			'invoice_id'  => null,
		];

		$remaining[] = $merged;
		update_option( 'ci_time_entries', wp_json_encode( $remaining ), false );

		wp_send_json_success( $merged );
	}

	private function get_entries(): array {
		return json_decode( get_option( 'ci_time_entries', '[]' ), true ) ?: [];
	}
}
