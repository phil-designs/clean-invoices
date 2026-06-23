<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Invoice_Editor {

	public function init(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_boxes' ] );
		add_action( 'save_post_ci_invoice', [ $this, 'save' ], 10, 2 );
		add_filter( 'enter_title_here', [ $this, 'title_placeholder' ] );
		add_action( 'admin_head', [ $this, 'hide_title_box' ] );
	}

	public function add_boxes(): void {
		add_meta_box( 'ci-invoice-details', 'Invoice Details', [ $this, 'box_details' ], 'ci_invoice', 'normal', 'high' );
		add_meta_box( 'ci-line-items',      'Line Items',       [ $this, 'box_items' ],   'ci_invoice', 'normal', 'high' );
		add_meta_box( 'ci-invoice-actions', 'Send & Actions',   [ $this, 'box_actions' ], 'ci_invoice', 'side',   'high' );
	}

	public function title_placeholder( string $text ): string {
		return get_post_type() === 'ci_invoice' ? 'Invoice number (auto-assigned on save)' : $text;
	}

	public function hide_title_box(): void {
		$screen = get_current_screen();
		if ( $screen && $screen->post_type === 'ci_invoice' ) {
			echo '<style>#titlediv label { display:none; } #title { background:#f6f7f7; font-size:14px; }</style>';
		}
	}

	// -------------------------------------------------------
	// Meta boxes
	// -------------------------------------------------------

	public function box_details( WP_Post $post ): void {
		wp_nonce_field( 'ci_invoice_save', 'ci_invoice_nonce' );

		$id           = $post->ID;
		$client_id    = get_post_meta( $id, '_ci_client_id', true );
		$inv_date     = get_post_meta( $id, '_ci_invoice_date', true ) ?: wp_date( 'Y-m-d' );
		$due_date     = get_post_meta( $id, '_ci_due_date', true );
		$notes        = get_post_meta( $id, '_ci_notes', true );
		$line_items   = json_decode( get_post_meta( $id, '_ci_line_items', true ) ?: '[]', true );
		$total_hours  = array_sum( array_column( $line_items, 'quantity' ) );
		$status       = get_post_meta( $id, '_ci_status', true ) ?: 'draft';
		$paid_date    = get_post_meta( $id, '_ci_paid_date', true );
		$paid_method  = get_post_meta( $id, '_ci_payment_method', true );
		$clients      = get_posts( [ 'post_type' => 'ci_client', 'posts_per_page' => -1, 'post_status' => 'any', 'orderby' => 'title', 'order' => 'ASC' ] );
		?>
		<table class="form-table ci-details-table" role="presentation">
			<tr>
				<th><label for="ci_client_id">Client</label></th>
				<td>
					<select id="ci_client_id" name="ci_client_id" class="regular-text">
						<option value="">— Select client —</option>
						<?php foreach ( $clients as $c ) : ?>
							<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $client_id, $c->ID ); ?>>
								<?php echo esc_html( $c->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=ci_client' ) ); ?>" class="ci-small-link">+ Add new client</a>
				</td>
			</tr>
			<tr>
				<th><label for="ci_invoice_date">Invoice Date</label></th>
				<td><input type="date" id="ci_invoice_date" name="ci_invoice_date" value="<?php echo esc_attr( $inv_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ci_due_date">Due Date</label></th>
				<td><input type="date" id="ci_due_date" name="ci_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
			<tr>
				<th>Total Hours</th>
				<td><?php echo $total_hours > 0 ? esc_html( rtrim( rtrim( number_format( $total_hours, 2 ), '0' ), '.' ) ) : '—'; ?></td>
			</tr>
			<tr>
				<th><label for="ci_status">Status</label></th>
				<td>
					<select id="ci_status" name="ci_status">
						<?php foreach ( [ 'draft' => 'Draft', 'sent' => 'Sent', 'partial' => 'Partial', 'paid' => 'Paid', 'overdue' => 'Overdue' ] as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $status, $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr id="ci-paid-fields" <?php echo $status !== 'paid' ? 'style="display:none;"' : ''; ?>>
				<th><label for="ci_paid_date">Paid Date</label></th>
				<td>
					<input type="date" id="ci_paid_date" name="ci_paid_date" value="<?php echo esc_attr( $paid_date ); ?>">
					<select name="ci_payment_method" style="margin-left:8px;">
						<option value="">— Method —</option>
						<?php foreach ( [ 'Venmo', 'Zelle', 'Check', 'Credit Card', 'Bank Transfer', 'Cash', 'Other' ] as $m ) : ?>
							<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $paid_method, $m ); ?>><?php echo esc_html( $m ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ci_notes">Notes</label></th>
				<td><textarea id="ci_notes" name="ci_notes" rows="3" class="large-text"><?php echo esc_textarea( $notes ); ?></textarea></td>
			</tr>
		</table>

		<?php
		$payments      = json_decode( get_post_meta( $id, '_ci_payments', true ) ?: '[]', true );
		$invoice_total = (float) get_post_meta( $id, '_ci_total', true );
		$amount_paid   = array_sum( array_column( $payments, 'amount' ) );
		$balance_due   = ( $status === 'paid' && empty( $payments ) ) ? 0.0 : max( 0.0, $invoice_total - $amount_paid );
		?>
		<input type="hidden" id="ci-invoice-balance" value="<?php echo esc_attr( number_format( $balance_due, 2 ) ); ?>">

		<?php if ( ! empty( $payments ) ) : ?>
		<div class="ci-payment-history">
			<h4>Payments Received</h4>
			<table class="ci-payment-history-table">
				<thead>
					<tr>
						<th>Date</th>
						<th>Type</th>
						<th>Amount</th>
						<th>Method</th>
						<th>Notes</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$type_labels = [ 'deposit' => 'Deposit', 'installment' => 'Installment', 'payment' => 'Payment' ];
					foreach ( $payments as $pmt ) :
					?>
					<tr>
						<td><?php echo esc_html( ! empty( $pmt['date'] ) ? (string) wp_date( 'M j, Y', strtotime( $pmt['date'] ) ) : '—' ); ?></td>
						<td><?php echo esc_html( $type_labels[ $pmt['type'] ?? 'payment' ] ?? 'Payment' ); ?></td>
						<td><?php echo esc_html( '$' . number_format( (float) ( $pmt['amount'] ?? 0 ), 2 ) ); ?></td>
						<td><?php echo esc_html( $pmt['method'] ?? '—' ); ?></td>
						<td><?php echo esc_html( $pmt['notes'] ?? '' ); ?></td>
						<td>
							<button type="button"
								class="ci-remove-payment button-link-delete"
								data-id="<?php echo esc_attr( $id ); ?>"
								data-payment-id="<?php echo esc_attr( $pmt['id'] ?? '' ); ?>">Remove</button>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div class="ci-payment-summary">
				<span>Total Paid: <strong><?php echo esc_html( '$' . number_format( $amount_paid, 2 ) ); ?></strong></span>
				<span>Balance Due: <strong><?php echo esc_html( '$' . number_format( $balance_due, 2 ) ); ?></strong></span>
			</div>
		</div>
		<?php endif; ?>
		<?php
	}

	public function box_items( WP_Post $post ): void {
		$id         = $post->ID;
		$items      = json_decode( get_post_meta( $id, '_ci_line_items', true ) ?: '[]', true );
		$subtotal   = get_post_meta( $id, '_ci_subtotal', true );
		$tax_rate_meta = get_post_meta( $id, '_ci_tax_rate', true );
		$tax_rate      = $tax_rate_meta !== '' ? $tax_rate_meta : get_option( 'ci_default_tax_rate', '0' );
		$tax_amount = get_post_meta( $id, '_ci_tax_amount', true );
		$shipping   = get_post_meta( $id, '_ci_shipping', true );
		$total      = get_post_meta( $id, '_ci_total', true );
		$show_ship  = get_option( 'ci_show_shipping', '0' );
		?>
		<div class="ci-items-wrap">
			<table id="ci-items-table" class="ci-items-table">
				<thead>
					<tr>
						<th class="ci-col-desc">Description</th>
						<th class="ci-col-detail">Sub-detail</th>
						<th class="ci-col-qty">Qty</th>
						<th class="ci-col-rate">Rate</th>
						<th class="ci-col-amount">Amount</th>
						<th class="ci-col-del"></th>
					</tr>
				</thead>
				<tbody id="ci-items-body">
					<?php foreach ( $items as $item ) : ?>
					<tr class="ci-item-row">
						<td><input type="text" class="ci-desc" value="<?php echo esc_attr( $item['description'] ?? '' ); ?>" placeholder="Description"></td>
						<td><input type="text" class="ci-detail" value="<?php echo esc_attr( $item['detail'] ?? '' ); ?>" placeholder="Optional sub-detail"></td>
						<td><input type="number" class="ci-qty" value="<?php echo esc_attr( $item['quantity'] ?? 1 ); ?>" min="0" step="any"></td>
						<td><input type="number" class="ci-rate" value="<?php echo esc_attr( $item['rate'] ?? '0' ); ?>" min="0" step="0.01" placeholder="0.00"></td>
						<td class="ci-amount"><?php echo esc_html( number_format( (float) ( $item['amount'] ?? 0 ), 2 ) ); ?></td>
						<td><button type="button" class="ci-remove-row button-link-delete">&#x2715;</button></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<button type="button" id="ci-add-row" class="button">+ Add Line Item</button>

			<input type="hidden" id="ci_line_items" name="ci_line_items" value="<?php echo esc_attr( wp_json_encode( $items ) ); ?>">

			<div class="ci-totals">
				<div class="ci-totals-row">
					<span>Subtotal</span>
					<span id="ci-subtotal-display">$<?php echo esc_html( number_format( (float) $subtotal, 2 ) ); ?></span>
					<input type="hidden" name="ci_subtotal" id="ci_subtotal" value="<?php echo esc_attr( $subtotal ); ?>">
				</div>
				<div class="ci-totals-row">
					<span>Tax (<input type="number" id="ci_tax_rate" name="ci_tax_rate" value="<?php echo esc_attr( $tax_rate ); ?>" min="0" max="100" step="0.01" class="ci-tax-rate-input">%)</span>
					<span id="ci-tax-display">$<?php echo esc_html( number_format( (float) $tax_amount, 2 ) ); ?></span>
					<input type="hidden" name="ci_tax_amount" id="ci_tax_amount" value="<?php echo esc_attr( $tax_amount ); ?>">
				</div>
				<?php if ( $show_ship ) : ?>
				<div class="ci-totals-row">
					<span>Shipping</span>
					<span><input type="number" name="ci_shipping" id="ci_shipping" value="<?php echo esc_attr( $shipping ); ?>" min="0" step="0.01" class="ci-shipping-input" placeholder="0.00"></span>
				</div>
				<?php else : ?>
					<input type="hidden" name="ci_shipping" id="ci_shipping" value="0">
				<?php endif; ?>
				<div class="ci-totals-row ci-totals-row--total">
					<span>Total</span>
					<span id="ci-total-display">$<?php echo esc_html( number_format( (float) $total, 2 ) ); ?></span>
					<input type="hidden" name="ci_total" id="ci_total" value="<?php echo esc_attr( $total ); ?>">
				</div>
			</div>
		</div>
		<?php
	}

	public function box_actions( WP_Post $post ): void {
		$id     = $post->ID;
		$status = get_post_meta( $id, '_ci_status', true ) ?: 'draft';
		$sent   = get_post_meta( $id, '_ci_sent_date', true );
		$num    = get_post_meta( $id, '_ci_invoice_number', true );

		if ( $num ) :
		?>
		<p class="ci-invoice-number">Invoice #: <strong><?php echo esc_html( $num ); ?></strong></p>
		<?php
		endif;

		if ( $sent ) echo '<p class="ci-sent-date">Last sent: ' . esc_html( (string) wp_date( 'M j, Y', strtotime( $sent ) ) ) . '</p>';

		if ( $id && $id > 0 && get_post_status( $id ) !== 'auto-draft' ) :
			$pdf_url     = wp_nonce_url( admin_url( 'admin-post.php?action=ci_download_pdf&post_id=' . $id ), 'ci_pdf_' . $id );
			$preview_url = wp_nonce_url( admin_url( 'admin-post.php?action=ci_preview_invoice&post_id=' . $id ), 'ci_preview_' . $id );
		?>
		<div class="ci-action-buttons">
			<button type="button" class="button button-primary ci-send-btn" data-id="<?php echo esc_attr( $id ); ?>">Send Invoice to Client</button>
			<button type="button" class="button ci-test-email-btn" data-id="<?php echo esc_attr( $id ); ?>">Send Test Email to Me</button>
			<a href="<?php echo esc_url( $preview_url ); ?>" class="button" target="_blank">Preview Invoice</a>
			<a href="<?php echo esc_url( $pdf_url ); ?>" class="button">Download PDF</a>
			<?php
			$_pmts    = json_decode( get_post_meta( $id, '_ci_payments', true ) ?: '[]', true );
			$_inv_tot = (float) get_post_meta( $id, '_ci_total', true );
			$_paid    = array_sum( array_column( $_pmts, 'amount' ) );
			$_bal     = ( $status === 'paid' && empty( $_pmts ) ) ? 0.0 : max( 0.0, $_inv_tot - $_paid );
			if ( $status !== 'paid' || $_bal > 0 ) :
			?>
			<button type="button" class="button ci-paid-btn"
				data-id="<?php echo esc_attr( $id ); ?>"
				data-balance="<?php echo esc_attr( number_format( $_bal, 2 ) ); ?>">Add Payment</button>
			<?php endif; ?>
			<?php if ( $status === 'paid' ) : ?>
			<button type="button" class="button ci-receipt-btn" data-id="<?php echo esc_attr( $id ); ?>">Send Receipt to Client</button>
			<?php endif; ?>
		</div>
		<div id="ci-action-status" class="ci-action-status"></div>
		<?php else : ?>
		<p class="ci-helper-text">Save the invoice first to enable sending and PDF download.</p>
		<?php endif;
	}

	// -------------------------------------------------------
	// Save
	// -------------------------------------------------------

	public function save( int $id, WP_Post $post ): void {
		if ( ! isset( $_POST['ci_invoice_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_key( $_POST['ci_invoice_nonce'] ), 'ci_invoice_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $id ) ) return;

		// Auto-assign invoice number on first save
		if ( ! get_post_meta( $id, '_ci_invoice_number', true ) ) {
			$prefix = get_option( 'ci_invoice_prefix', 'INV' );
			$next   = (int) get_option( 'ci_next_invoice_number', 1 );
			$number = $prefix . '-' . str_pad( $next, 4, '0', STR_PAD_LEFT );
			update_post_meta( $id, '_ci_invoice_number', $number );
			update_option( 'ci_next_invoice_number', $next + 1 );

			// Set post title to invoice number
			remove_action( 'save_post_ci_invoice', [ $this, 'save' ], 10 );
			wp_update_post( [ 'ID' => $id, 'post_title' => $number ] );
			add_action( 'save_post_ci_invoice', [ $this, 'save' ], 10, 2 );
		}

		$text_fields = [ 'ci_client_id', 'ci_invoice_date', 'ci_due_date', 'ci_status', 'ci_paid_date', 'ci_payment_method', 'ci_notes' ];
		foreach ( $text_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $id, '_' . $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		$num_fields = [ 'ci_subtotal', 'ci_tax_rate', 'ci_tax_amount', 'ci_shipping', 'ci_total' ];
		foreach ( $num_fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $id, '_' . $field, floatval( $_POST[ $field ] ) );
			}
		}

		if ( isset( $_POST['ci_line_items'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data; each field is sanitized individually in the array_map below.
			$raw   = wp_unslash( $_POST['ci_line_items'] );
			$items = json_decode( sanitize_text_field( $raw ), true );
			if ( is_array( $items ) ) {
				$clean = array_map( function ( $item ) {
					return [
						'description' => sanitize_text_field( $item['description'] ?? '' ),
						'detail'      => sanitize_text_field( $item['detail']      ?? '' ),
						'quantity'    => floatval( $item['quantity'] ?? 1 ),
						'rate'        => floatval( $item['rate']     ?? 0 ),
						'amount'      => floatval( $item['amount']   ?? 0 ),
					];
				}, $items );
				update_post_meta( $id, '_ci_line_items', wp_json_encode( $clean ) );
			}
		}
	}
}
