<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Exporter {

	public function output( int $year ): void {
		$invoices = get_posts( [
			'post_type'      => 'ci_invoice',
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'meta_query'     => [ [
				'key'     => '_ci_invoice_date',
				'value'   => [ $year . '-01-01', $year . '-12-31' ],
				'compare' => 'BETWEEN',
				'type'    => 'DATE',
			] ],
		] );

		$filename = 'invoices-' . $year . '.csv';
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, [ 'Invoice #', 'Client', 'Company', 'Date', 'Due Date', 'Subtotal', 'Tax', 'Shipping', 'Total', 'Status', 'Paid Date', 'Payment Method' ] );

		foreach ( $invoices as $post ) {
			$id        = $post->ID;
			$client_id = (int) get_post_meta( $id, '_ci_client_id', true );
			fputcsv( $out, [
				get_post_meta( $id, '_ci_invoice_number', true ),
				$client_id ? get_the_title( $client_id ) : '',
				$client_id ? get_post_meta( $client_id, '_ci_company', true ) : '',
				get_post_meta( $id, '_ci_invoice_date', true ),
				get_post_meta( $id, '_ci_due_date', true ),
				get_post_meta( $id, '_ci_subtotal', true ),
				get_post_meta( $id, '_ci_tax_amount', true ),
				get_post_meta( $id, '_ci_shipping', true ),
				get_post_meta( $id, '_ci_total', true ),
				get_post_meta( $id, '_ci_status', true ),
				get_post_meta( $id, '_ci_paid_date', true ),
				get_post_meta( $id, '_ci_payment_method', true ),
			] );
		}

		fclose( $out );
	}
}
