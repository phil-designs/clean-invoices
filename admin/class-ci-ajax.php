<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Ajax {

	public function init(): void {
		add_action( 'wp_ajax_ci_send_invoice',    [ $this, 'send_invoice' ] );
		add_action( 'wp_ajax_ci_send_test_email', [ $this, 'send_test_email' ] );
		add_action( 'wp_ajax_ci_mark_paid',       [ $this, 'mark_paid' ] );
		add_action( 'wp_ajax_ci_add_payment',     [ $this, 'add_payment' ] );
		add_action( 'wp_ajax_ci_remove_payment',  [ $this, 'remove_payment' ] );
		add_action( 'wp_ajax_ci_send_receipt',    [ $this, 'send_receipt' ] );
	}

	public function send_invoice(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$post_id = intval( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$sent = ( new CI_Email() )->send( $post_id );
		if ( $sent ) {
			wp_send_json_success( 'Invoice sent successfully.' );
		} else {
			wp_send_json_error( 'Failed to send. Check client email and plugin email settings.' );
		}
	}

	public function send_test_email(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$post_id = intval( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$to         = get_option( 'ci_bcc_email', get_option( 'admin_email' ) );
		$num        = get_post_meta( $post_id, '_ci_invoice_number', true ) ?: 'Draft';
		$from_name  = get_option( 'ci_company_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'ci_email', get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];

		$subject = '[TEST] Invoice ' . $num . ' from ' . $from_name;
		$body    = "This is a test email for invoice {$num}.\n\nThe PDF is attached. This was sent only to you — the client was not contacted.";

		$attachments = [];
		try {
			$pdf_bytes = ( new CI_PDF() )->generate( $post_id );
			$tmp       = wp_tempnam( 'invoice' ) . '.pdf';
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $tmp, $pdf_bytes );
			$attachments[] = $tmp;
		} catch ( Exception $e ) {
			wp_send_json_error( 'PDF generation failed: ' . $e->getMessage() );
		}

		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		foreach ( $attachments as $f ) {
			if ( file_exists( $f ) ) wp_delete_file( $f );
		}

		if ( $sent ) {
			wp_send_json_success( 'Test email sent to ' . $to );
		} else {
			wp_send_json_error( 'wp_mail() returned false. Check your server email configuration.' );
		}
	}

	// Records a payment and auto-updates invoice status.
	public function add_payment(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$post_id = intval( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$amount = floatval( $_POST['amount'] ?? 0 );
		if ( $amount <= 0 ) {
			wp_send_json_error( 'Amount must be greater than zero.' );
		}

		$date   = sanitize_text_field( wp_unslash( $_POST['paid_date']      ?? current_time( 'Y-m-d' ) ) );
		$method = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );
		$type   = sanitize_text_field( wp_unslash( $_POST['payment_type']   ?? 'payment' ) );
		$notes  = sanitize_text_field( wp_unslash( $_POST['notes']          ?? '' ) );

		$valid_types = [ 'deposit', 'installment', 'payment' ];
		if ( ! in_array( $type, $valid_types, true ) ) $type = 'payment';

		$payments   = json_decode( get_post_meta( $post_id, '_ci_payments', true ) ?: '[]', true );
		$payments[] = [
			'id'     => uniqid( 'pmt_' ),
			'date'   => $date,
			'amount' => $amount,
			'method' => $method,
			'type'   => $type,
			'notes'  => $notes,
		];
		update_post_meta( $post_id, '_ci_payments', wp_json_encode( $payments ) );

		[ $new_status, $amount_paid, $balance ] = $this->recalc_status( $post_id, $payments, $date, $method );

		wp_send_json_success( [
			'message'     => 'Payment recorded.',
			'new_status'  => $new_status,
			'amount_paid' => number_format( $amount_paid, 2 ),
			'balance_due' => number_format( $balance, 2 ),
		] );
	}

	// Removes a single payment record by its id and recalculates status.
	public function remove_payment(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$post_id    = intval( $_POST['post_id'] ?? 0 );
		$payment_id = sanitize_text_field( wp_unslash( $_POST['payment_id'] ?? '' ) );

		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$payments = json_decode( get_post_meta( $post_id, '_ci_payments', true ) ?: '[]', true );
		$payments = array_values( array_filter( $payments, fn( $p ) => ( $p['id'] ?? '' ) !== $payment_id ) );
		update_post_meta( $post_id, '_ci_payments', wp_json_encode( $payments ) );

		if ( empty( $payments ) ) {
			$current = get_post_meta( $post_id, '_ci_status', true );
			if ( in_array( $current, [ 'paid', 'partial' ], true ) ) {
				update_post_meta( $post_id, '_ci_status', 'sent' );
			}
			[ $new_status, $amount_paid, $balance ] = [
				get_post_meta( $post_id, '_ci_status', true ),
				0.0,
				(float) get_post_meta( $post_id, '_ci_total', true ),
			];
		} else {
			[ $new_status, $amount_paid, $balance ] = $this->recalc_status( $post_id, $payments );
		}

		wp_send_json_success( [
			'message'     => 'Payment removed.',
			'new_status'  => $new_status,
			'amount_paid' => number_format( $amount_paid, 2 ),
			'balance_due' => number_format( $balance, 2 ),
		] );
	}

	// Legacy full-payment shortcut — adds remaining balance as a payment record.
	public function mark_paid(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$post_id = intval( $_POST['post_id'] ?? 0 );
		$date    = sanitize_text_field( wp_unslash( $_POST['paid_date']      ?? current_time( 'Y-m-d' ) ) );
		$method  = sanitize_text_field( wp_unslash( $_POST['payment_method'] ?? '' ) );

		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$invoice_total = (float) get_post_meta( $post_id, '_ci_total', true );
		$payments      = json_decode( get_post_meta( $post_id, '_ci_payments', true ) ?: '[]', true );
		$amount_paid   = array_sum( array_column( $payments, 'amount' ) );
		$balance       = max( 0.0, $invoice_total - $amount_paid );

		if ( $balance > 0 ) {
			$payments[] = [
				'id'     => uniqid( 'pmt_' ),
				'date'   => $date,
				'amount' => $balance,
				'method' => $method,
				'type'   => 'payment',
				'notes'  => '',
			];
			update_post_meta( $post_id, '_ci_payments', wp_json_encode( $payments ) );
		}

		update_post_meta( $post_id, '_ci_status',         'paid' );
		update_post_meta( $post_id, '_ci_paid_date',      $date );
		update_post_meta( $post_id, '_ci_payment_method', $method );

		wp_send_json_success( [ 'message' => 'Marked as paid.', 'date' => $date ] );
	}

	public function send_receipt(): void {
		check_ajax_referer( 'ci_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden.' );

		$post_id = intval( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || get_post_type( $post_id ) !== 'ci_invoice' ) {
			wp_send_json_error( 'Invalid invoice.' );
		}

		$sent = ( new CI_Email() )->send_receipt( $post_id );
		if ( $sent ) {
			wp_send_json_success( 'Receipt sent successfully.' );
		} else {
			wp_send_json_error( 'Failed to send. Check the client email and email settings.' );
		}
	}

	// Updates invoice status based on payments made vs total owed.
	private function recalc_status( int $post_id, array $payments, string $date = '', string $method = '' ): array {
		$invoice_total = (float) get_post_meta( $post_id, '_ci_total', true );
		$amount_paid   = array_sum( array_column( $payments, 'amount' ) );
		$balance       = max( 0.0, $invoice_total - $amount_paid );

		if ( $amount_paid >= $invoice_total ) {
			$new_status = 'paid';
			update_post_meta( $post_id, '_ci_status', 'paid' );
			if ( $date )   update_post_meta( $post_id, '_ci_paid_date',      $date );
			if ( $method ) update_post_meta( $post_id, '_ci_payment_method', $method );
		} else {
			$new_status = 'partial';
			update_post_meta( $post_id, '_ci_status', 'partial' );
		}

		return [ $new_status, $amount_paid, $balance ];
	}
}
