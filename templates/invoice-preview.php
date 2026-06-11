<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// $post_id is set by the loader before including this file
$client_id = (int) get_post_meta( $post_id, '_ci_client_id', true );
$items     = json_decode( get_post_meta( $post_id, '_ci_line_items', true ) ?: '[]', true );

$inv = [
	'logo_url'        => ( $lid = (int) get_option( 'ci_logo_id' ) ) ? wp_get_attachment_image_url( $lid, 'medium' ) : '',
	'company_name'    => get_option( 'ci_company_name', '' ),
	'company_address' => get_option( 'ci_address', '' ),
	'company_csz'     => get_option( 'ci_city_state_zip', '' ),
	'company_phone'   => get_option( 'ci_phone', '' ),
	'company_email'   => get_option( 'ci_email', '' ),
	'company_website' => get_option( 'ci_website', '' ),
	'venmo'           => ltrim( get_option( 'ci_venmo', '' ), '@' ),
	'zelle'           => get_option( 'ci_zelle', '' ),
	'thank_you'       => get_option( 'ci_thank_you_message', 'Thank you for your business!' ),
	'check_note'      => get_option( 'ci_check_payment_note', '' ),
	'invoice_number'  => get_post_meta( $post_id, '_ci_invoice_number', true ),
	'invoice_date'    => get_post_meta( $post_id, '_ci_invoice_date', true ),
	'due_date'        => get_post_meta( $post_id, '_ci_due_date', true ),
	'notes'           => get_post_meta( $post_id, '_ci_notes', true ),
	'subtotal'        => (float) get_post_meta( $post_id, '_ci_subtotal', true ),
	'tax_rate'        => (float) get_post_meta( $post_id, '_ci_tax_rate', true ),
	'tax_amount'      => (float) get_post_meta( $post_id, '_ci_tax_amount', true ),
	'shipping'        => (float) get_post_meta( $post_id, '_ci_shipping', true ),
	'total'           => (float) get_post_meta( $post_id, '_ci_total', true ),
	'client_name'     => $client_id ? get_the_title( $client_id ) : '',
	'client_contact'  => $client_id ? get_post_meta( $client_id, '_ci_contact_name', true ) : '',
	'client_company'  => $client_id ? get_post_meta( $client_id, '_ci_company', true ) : '',
	'client_address1' => $client_id ? get_post_meta( $client_id, '_ci_address_1', true ) : '',
	'client_address2' => $client_id ? get_post_meta( $client_id, '_ci_address_2', true ) : '',
	'client_csz'      => $client_id ? trim( implode( ', ', array_filter( [
		get_post_meta( $client_id, '_ci_city', true ),
		get_post_meta( $client_id, '_ci_state', true ),
		get_post_meta( $client_id, '_ci_zip', true ),
	] ) ) ) : '',
	'client_phone'    => $client_id ? get_post_meta( $client_id, '_ci_phone', true ) : '',
	'client_email'    => $client_id ? get_post_meta( $client_id, '_ci_email', true ) : '',
];

$ci_payments    = json_decode( get_post_meta( $post_id, '_ci_payments', true ) ?: '[]', true );
$ci_amount_paid = array_sum( array_column( $ci_payments, 'amount' ) );
$ci_inv_status  = get_post_meta( $post_id, '_ci_status', true );
$ci_balance_due = ( $ci_inv_status === 'paid' && empty( $ci_payments ) ) ? 0.0 : max( 0.0, $inv['total'] - $ci_amount_paid );

function ci_fmt_date( string $d ): string {
	return $d ? (string) wp_date( 'F j, Y', strtotime( $d ) ) : '';
}
function ci_money( float $n ): string {
	return '$' . number_format( $n, 2 );
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice <?php echo esc_html( $inv['invoice_number'] ); ?></title>
<style>
	*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

	body {
		font-family: Helvetica, Arial, sans-serif;
		font-size: 13px;
		color: #1e1e1e;
		background: #e8e8e8;
	}

	.ci-page {
		background: #fff;
		width: 760px;
		margin: 32px auto;
		padding: 48px 52px;
		box-shadow: 0 2px 16px rgba(0,0,0,0.12);
	}

	/* Header */
	.ci-header {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 28px;
		padding-bottom: 20px;
		border-bottom: 1px solid #e0e0e0;
	}

	.ci-header-left img {
		max-height: 70px;
		max-width: 180px;
		display: block;
		margin-bottom: 10px;
	}

	.ci-company-name {
		font-size: 14px;
		font-weight: 700;
		margin-bottom: 4px;
	}

	.ci-company-detail {
		font-size: 11px;
		color: #555;
		line-height: 1.7;
	}

	.ci-header-right {
		text-align: right;
	}

	.ci-invoice-heading {
		font-size: 36px;
		font-weight: 700;
		letter-spacing: -1px;
		color: #1e1e1e;
		margin-bottom: 12px;
	}

	.ci-invoice-meta {
		font-size: 12px;
		color: #555;
		line-height: 1.9;
	}

	.ci-invoice-meta strong {
		color: #1e1e1e;
	}

	/* Bill To + Amount Due */
	.ci-billing {
		display: flex;
		justify-content: space-between;
		align-items: flex-start;
		margin-bottom: 28px;
	}

	.ci-bill-to-label {
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: #888;
		margin-bottom: 6px;
	}

	.ci-client-name {
		font-size: 15px;
		font-weight: 700;
		margin-bottom: 2px;
	}

	.ci-client-co {
		font-size: 12px;
		color: #444;
		font-style: italic;
		margin-bottom: 4px;
	}

	.ci-client-detail {
		font-size: 12px;
		color: #555;
		line-height: 1.7;
	}

	.ci-amount-due {
		text-align: right;
	}

	.ci-amount-due-label {
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.08em;
		color: #888;
		margin-bottom: 4px;
	}

	.ci-amount-due-value {
		font-size: 32px;
		font-weight: 700;
		color: #1e1e1e;
	}

	/* Items table */
	.ci-items {
		width: 100%;
		border-collapse: collapse;
		margin-bottom: 20px;
		font-size: 12px;
	}

	.ci-items thead th {
		background: #f4f4f4;
		padding: 8px 10px;
		text-align: left;
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: #555;
		border-bottom: 2px solid #e0e0e0;
	}

	.ci-items thead th.num { text-align: right; }

	.ci-items tbody td {
		padding: 9px 10px;
		border-bottom: 1px solid #f0f0f0;
		vertical-align: top;
	}

	.ci-items tbody td.num { text-align: right; }

	.ci-item-desc { font-weight: 600; }
	.ci-item-detail {
		font-size: 11px;
		color: #777;
		font-style: italic;
		margin-top: 2px;
	}

	/* Totals */
	.ci-totals-wrap {
		display: flex;
		justify-content: flex-end;
		margin-bottom: 28px;
	}

	.ci-totals {
		width: 280px;
	}

	.ci-totals-row {
		display: flex;
		justify-content: space-between;
		padding: 5px 0;
		font-size: 12px;
		color: #555;
	}

	.ci-totals-row.total {
		border-top: 2px solid #1e1e1e;
		margin-top: 6px;
		padding-top: 8px;
		font-size: 15px;
		font-weight: 700;
		color: #1e1e1e;
	}

	.ci-payment-row {
		color: #2a7a2a;
		font-size: 11px;
		padding: 3px 0;
	}

	.ci-balance-row {
		border-top: 2px solid #1e1e1e;
		margin-top: 6px;
		padding-top: 8px;
		font-size: 15px;
		font-weight: 700;
		color: #1e1e1e;
	}

	/* Notes */
	.ci-notes {
		margin-bottom: 24px;
		padding: 12px 14px;
		background: #f9f9f9;
		border-left: 3px solid #e0e0e0;
		font-size: 12px;
		color: #555;
		line-height: 1.6;
	}

	.ci-notes-label {
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: #888;
		margin-bottom: 4px;
	}

	/* Footer */
	.ci-footer {
		border-top: 1px solid #e0e0e0;
		padding-top: 20px;
	}

	.ci-payment-label {
		font-size: 10px;
		font-weight: 700;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: #888;
		margin-bottom: 6px;
	}

	.ci-payment-item {
		font-size: 12px;
		color: #333;
		line-height: 1.7;
	}

	.ci-payment-qr-row {
		display: flex;
		gap: 24px;
		margin-top: 8px;
	}

	.ci-payment-qr-item {
		display: flex;
		flex-direction: column;
		align-items: flex-start;
		gap: 4px;
	}

	.ci-payment-qr-item img {
		display: block;
	}

	.ci-thank-you {
		border-top: 1px solid #e0e0e0;
		margin-top: 18px;
		padding-top: 14px;
		font-size: 13px;
		font-weight: 700;
		color: #1e1e1e;
		text-align: center;
	}

	.ci-payment-section {
		display: flex;
		gap: 20px;
		align-items: stretch;
	}

	.ci-check-note {
		flex: 1;
		border: 1.5px solid #b0bec5;
		background: #f4f7fb;
		border-radius: 4px;
		padding: 10px 14px;
		font-size: 11.5px;
		color: #333;
		line-height: 1.65;
		display: flex;
		align-items: center;
	}

	/* Print toolbar */
	.ci-toolbar {
		width: 760px;
		margin: 0 auto 0;
		display: flex;
		gap: 8px;
		justify-content: flex-end;
	}

	.ci-toolbar button {
		padding: 7px 16px;
		font-size: 13px;
		border: 1px solid #c3c4c7;
		border-radius: 3px;
		background: #fff;
		cursor: pointer;
	}

	.ci-toolbar button.primary {
		background: #2271b1;
		color: #fff;
		border-color: #2271b1;
	}

	@media print {
		body { background: #fff; }
		.ci-page { box-shadow: none; margin: 0; padding: 24px; width: 100%; }
		.ci-toolbar { display: none; }
	}
</style>
</head>
<body>

<div class="ci-toolbar">
	<button onclick="window.close()">Close</button>
	<button class="primary" onclick="window.print()">Print / Save as PDF</button>
</div>

<div class="ci-page">

	<!-- Header -->
	<div class="ci-header">
		<div class="ci-header-left">
			<?php if ( $inv['logo_url'] ) : ?>
				<img src="<?php echo esc_url( $inv['logo_url'] ); ?>" alt="<?php echo esc_attr( $inv['company_name'] ); ?>">
			<?php endif; ?>
			<?php if ( $inv['company_name'] ) : ?>
				<div class="ci-company-name"><?php echo esc_html( $inv['company_name'] ); ?></div>
			<?php endif; ?>
			<div class="ci-company-detail">
				<?php foreach ( array_filter( [ $inv['company_address'], $inv['company_csz'], $inv['company_phone'], $inv['company_email'], $inv['company_website'] ] ) as $line ) : ?>
					<?php echo esc_html( $line ); ?><br>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="ci-header-right">
			<div class="ci-invoice-heading">INVOICE</div>
			<div class="ci-invoice-meta">
				<?php if ( $inv['invoice_number'] ) : ?>
					<strong>Invoice #:</strong> <?php echo esc_html( $inv['invoice_number'] ); ?><br>
				<?php endif; ?>
				<?php if ( $inv['invoice_date'] ) : ?>
					<strong>Date:</strong> <?php echo esc_html( ci_fmt_date( $inv['invoice_date'] ) ); ?><br>
				<?php endif; ?>
				<?php if ( $inv['due_date'] ) : ?>
					<strong>Due:</strong> <?php echo esc_html( ci_fmt_date( $inv['due_date'] ) ); ?>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- Bill To + Amount Due -->
	<div class="ci-billing">
		<div>
			<div class="ci-bill-to-label">Bill To</div>
			<?php if ( $inv['client_name'] ) : ?>
				<div class="ci-client-name"><?php echo esc_html( $inv['client_name'] ); ?></div>
			<?php endif; ?>
			<?php if ( $inv['client_contact'] ) : ?>
				<div class="ci-client-co">c/o: <?php echo esc_html( $inv['client_contact'] ); ?></div>
			<?php endif; ?>
			<div class="ci-client-detail">
				<?php foreach ( array_filter( [ $inv['client_company'], $inv['client_address1'], $inv['client_address2'], $inv['client_csz'], $inv['client_phone'], $inv['client_email'] ] ) as $line ) : ?>
					<?php echo esc_html( $line ); ?><br>
				<?php endforeach; ?>
			</div>
		</div>
		<div class="ci-amount-due">
			<div class="ci-amount-due-label"><?php echo $ci_amount_paid > 0 ? 'Balance Due' : 'Amount Due'; ?></div>
			<div class="ci-amount-due-value"><?php echo esc_html( ci_money( $ci_amount_paid > 0 ? $ci_balance_due : $inv['total'] ) ); ?></div>
		</div>
	</div>

	<!-- Line items -->
	<table class="ci-items">
		<thead>
			<tr>
				<th style="width:50%">Description</th>
				<th class="num" style="width:12%">Qty</th>
				<th class="num" style="width:19%">Rate</th>
				<th class="num" style="width:19%">Amount</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( (array) $items as $item ) : ?>
			<tr>
				<td>
					<div class="ci-item-desc"><?php echo esc_html( $item['description'] ?? '' ); ?></div>
					<?php if ( ! empty( $item['detail'] ) ) : ?>
						<div class="ci-item-detail"><?php echo esc_html( $item['detail'] ); ?></div>
					<?php endif; ?>
				</td>
				<td class="num"><?php echo esc_html( $item['quantity'] ?? 1 ); ?></td>
				<td class="num"><?php echo esc_html( ci_money( (float) ( $item['rate'] ?? 0 ) ) ); ?></td>
				<td class="num"><?php echo esc_html( ci_money( (float) ( $item['amount'] ?? 0 ) ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<!-- Totals -->
	<div class="ci-totals-wrap">
		<div class="ci-totals">
			<div class="ci-totals-row">
				<span>Subtotal</span>
				<span><?php echo esc_html( ci_money( $inv['subtotal'] ) ); ?></span>
			</div>
			<?php if ( $inv['tax_rate'] > 0 ) : ?>
			<div class="ci-totals-row">
				<span>Tax (<?php echo esc_html( $inv['tax_rate'] ); ?>%)</span>
				<span><?php echo esc_html( ci_money( $inv['tax_amount'] ) ); ?></span>
			</div>
			<?php endif; ?>
			<?php if ( $inv['shipping'] > 0 ) : ?>
			<div class="ci-totals-row">
				<span>Shipping</span>
				<span><?php echo esc_html( ci_money( $inv['shipping'] ) ); ?></span>
			</div>
			<?php endif; ?>
			<div class="ci-totals-row total">
				<span>Total</span>
				<span><?php echo esc_html( ci_money( $inv['total'] ) ); ?></span>
			</div>
			<?php if ( ! empty( $ci_payments ) ) : ?>
				<?php
				$ci_type_labels = [ 'deposit' => 'Deposit', 'installment' => 'Installment', 'payment' => 'Payment' ];
				foreach ( $ci_payments as $pmt ) :
					$pmt_label = $ci_type_labels[ $pmt['type'] ?? 'payment' ] ?? 'Payment';
					$pmt_meta  = array_filter( [
						! empty( $pmt['date'] )   ? ci_fmt_date( $pmt['date'] ) : '',
						! empty( $pmt['method'] ) ? $pmt['method'] : '',
					] );
				?>
				<div class="ci-totals-row ci-payment-row">
					<span><?php echo esc_html( $pmt_label . ( $pmt_meta ? ' (' . implode( ' · ', $pmt_meta ) . ')' : '' ) ); ?></span>
					<span>&minus;<?php echo esc_html( ci_money( (float) ( $pmt['amount'] ?? 0 ) ) ); ?></span>
				</div>
				<?php endforeach; ?>
				<div class="ci-totals-row ci-balance-row">
					<span>Balance Due</span>
					<span><?php echo esc_html( ci_money( $ci_balance_due ) ); ?></span>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Notes -->
	<?php if ( $inv['notes'] ) : ?>
	<div class="ci-notes">
		<div class="ci-notes-label">Notes</div>
		<?php echo nl2br( esc_html( $inv['notes'] ) ); ?>
	</div>
	<?php endif; ?>

	<!-- Footer -->
	<div class="ci-footer">
		<div class="ci-payment-section">
			<?php if ( $inv['venmo'] || $inv['zelle'] ) : ?>
				<div>
					<div class="ci-payment-label">Payment Options</div>
					<div class="ci-payment-qr-row">
						<?php if ( $inv['venmo'] ) : ?>
							<div class="ci-payment-qr-item">
								<img src="<?php echo esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&format=png&data=' . rawurlencode( 'https://venmo.com/u/' . $inv['venmo'] ) ); ?>" width="70" height="70" alt="Venmo QR">
								<div class="ci-payment-item">Venmo: @<?php echo esc_html( $inv['venmo'] ); ?></div>
							</div>
						<?php endif; ?>
						<?php if ( $inv['zelle'] ) : ?>
							<div class="ci-payment-qr-item">
								<img src="<?php echo esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&format=png&data=' . rawurlencode( $inv['zelle'] ) ); ?>" width="70" height="70" alt="Zelle QR">
								<div class="ci-payment-item">Zelle: <?php echo esc_html( $inv['zelle'] ); ?></div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( $inv['check_note'] ) : ?>
				<div class="ci-check-note"><?php echo wp_kses( $inv['check_note'], [
					'p'      => [ 'style' => true ],
					'strong' => [],
					'b'      => [],
					'em'     => [],
					'i'      => [],
					'br'     => [],
					'span'   => [ 'style' => true ],
				] ); ?></div>
			<?php endif; ?>
		</div>
		<?php if ( $inv['thank_you'] ) : ?>
			<div class="ci-thank-you"><?php echo esc_html( $inv['thank_you'] ); ?></div>
		<?php endif; ?>
	</div>

</div><!-- .ci-page -->

</body>
</html>
