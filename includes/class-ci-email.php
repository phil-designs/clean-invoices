<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Email {

	public function send( int $post_id ): bool {
		$client_id = (int) get_post_meta( $post_id, '_ci_client_id', true );
		if ( ! $client_id ) return false;

		$to = get_post_meta( $client_id, '_ci_email', true );
		if ( ! $to ) return false;

		$tokens = $this->tokens( $post_id, $client_id );

		$subject    = $this->replace( get_option( 'ci_email_subject', 'Invoice {invoice_number}' ), $tokens );
		$body       = $this->replace( get_option( 'ci_email_body', '' ), $tokens );
		$bcc        = get_option( 'ci_bcc_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'ci_company_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'ci_email', get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];
		if ( $bcc ) $headers[] = 'Bcc: ' . $bcc;

		$attachments = [];
		try {
			$pdf_bytes = ( new CI_PDF() )->generate( $post_id );
			$tmp       = wp_tempnam( 'invoice' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $tmp . '.pdf', $pdf_bytes );
			$attachments[] = $tmp . '.pdf';
		} catch ( Exception $e ) {
			// Send without attachment if PDF fails
		}

		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		// Clean up temp file
		foreach ( $attachments as $f ) {
			if ( file_exists( $f ) ) wp_delete_file( $f );
		}

		if ( $sent ) {
			update_post_meta( $post_id, '_ci_status',    'sent' );
			update_post_meta( $post_id, '_ci_sent_date', current_time( 'Y-m-d' ) );
		}

		return $sent;
	}

	public function send_receipt( int $post_id ): bool {
		$client_id = (int) get_post_meta( $post_id, '_ci_client_id', true );
		if ( ! $client_id ) return false;

		$to = get_post_meta( $client_id, '_ci_email', true );
		if ( ! $to ) return false;

		$tokens = $this->receipt_tokens( $post_id, $client_id );

		$subject    = $this->replace( get_option( 'ci_receipt_subject', 'Payment Receipt — Invoice {invoice_number}' ), $tokens );
		$body       = $this->replace( get_option( 'ci_receipt_body', '' ), $tokens );
		$bcc        = get_option( 'ci_bcc_email', get_option( 'admin_email' ) );
		$from_name  = get_option( 'ci_company_name', get_bloginfo( 'name' ) );
		$from_email = get_option( 'ci_email', get_option( 'admin_email' ) );

		$headers = [
			'Content-Type: text/plain; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
		];
		if ( $bcc ) $headers[] = 'Bcc: ' . $bcc;

		$attachments = [];
		try {
			$pdf_bytes = ( new CI_PDF() )->generate( $post_id );
			$tmp       = wp_tempnam( 'receipt' );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $tmp . '.pdf', $pdf_bytes );
			$attachments[] = $tmp . '.pdf';
		} catch ( Exception $e ) {
			// Send without attachment if PDF fails
		}

		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );

		foreach ( $attachments as $f ) {
			if ( file_exists( $f ) ) wp_delete_file( $f );
		}

		if ( $sent ) {
			update_post_meta( $post_id, '_ci_receipt_sent_date', current_time( 'Y-m-d' ) );
		}

		return $sent;
	}

	private function tokens( int $post_id, int $client_id ): array {
		return [
			'{invoice_number}' => get_post_meta( $post_id, '_ci_invoice_number', true ),
			'{invoice_date}'   => get_post_meta( $post_id, '_ci_invoice_date', true ),
			'{due_date}'       => get_post_meta( $post_id, '_ci_due_date', true ),
			'{total}'          => '$' . number_format( (float) get_post_meta( $post_id, '_ci_total', true ), 2 ),
			'{client_name}'    => get_the_title( $client_id ),
			'{company_name}'   => get_option( 'ci_company_name', get_bloginfo( 'name' ) ),
			'{payment_terms}'  => get_option( 'ci_payment_terms', 'Due on receipt' ),
		];
	}

	private function receipt_tokens( int $post_id, int $client_id ): array {
		$payments   = json_decode( get_post_meta( $post_id, '_ci_payments', true ) ?: '[]', true );
		$amt_paid   = array_sum( array_column( $payments, 'amount' ) );
		$total      = (float) get_post_meta( $post_id, '_ci_total', true );
		$balance    = max( 0.0, $total - $amt_paid );
		$paid_date  = get_post_meta( $post_id, '_ci_paid_date', true );
		$method     = get_post_meta( $post_id, '_ci_payment_method', true );

		// Use the most recent payment record if available
		if ( ! empty( $payments ) ) {
			$last      = end( $payments );
			$paid_date = $paid_date ?: ( $last['date']   ?? '' );
			$method    = $method    ?: ( $last['method'] ?? '' );
		}

		return [
			'{invoice_number}'  => get_post_meta( $post_id, '_ci_invoice_number', true ),
			'{invoice_date}'    => get_post_meta( $post_id, '_ci_invoice_date', true ),
			'{total}'           => '$' . number_format( $total, 2 ),
			'{amount_paid}'     => '$' . number_format( $amt_paid ?: $total, 2 ),
			'{balance_due}'     => '$' . number_format( $balance, 2 ),
			'{paid_date}'       => $paid_date ? date( 'F j, Y', strtotime( $paid_date ) ) : '',
			'{payment_method}'  => $method,
			'{client_name}'     => get_the_title( $client_id ),
			'{company_name}'    => get_option( 'ci_company_name', get_bloginfo( 'name' ) ),
		];
	}

	private function replace( string $template, array $tokens ): string {
		return str_replace( array_keys( $tokens ), array_values( $tokens ), $template );
	}
}
