<?php
/**
 * Plugin Name: Clean Invoices
 * Plugin URI:  https://phildesigns.com
 * Description: Create, send, and track client invoices from your WordPress dashboard.
 * Version:     1.0.0
 * Author:      Phillip De Vita
 * License:     GPL-2.0-or-later
 * Text Domain: clean-invoices
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'CI_VERSION', '1.0.0' );
define( 'CI_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CI_URL',     plugin_dir_url( __FILE__ ) );
define( 'CI_FILE',    __FILE__ );

foreach ( [
	'includes/class-ci-cpt.php',
	'includes/class-ci-pdf.php',
	'includes/class-ci-email.php',
	'includes/class-ci-exporter.php',
	'admin/class-ci-settings.php',
	'admin/class-ci-invoice-editor.php',
	'admin/class-ci-client-editor.php',
	'admin/class-ci-ajax.php',
	'admin/class-ci-time-tracker.php',
	'includes/class-ci-loader.php',
] as $f ) {
	require_once CI_DIR . $f;
}

add_action( 'plugins_loaded', fn() => ( new CI_Loader() )->init() );

register_activation_hook( CI_FILE, 'ci_activate' );
function ci_activate(): void {
	$defaults = [
		'ci_invoice_prefix'      => 'INV',
		'ci_next_invoice_number' => 1,
		'ci_default_tax_rate'    => '0',
		'ci_payment_terms'       => 'Due on receipt',
		'ci_thank_you_message'   => 'Thank you for your business!',
		'ci_email_subject'       => 'Invoice {invoice_number} from {company_name}',
		'ci_email_body'          => "Hi {client_name},\n\nPlease find invoice {invoice_number} for {total} attached.\n\nPayment terms: {payment_terms}.\n\nThank you,\n{company_name}",
		'ci_receipt_subject'     => 'Payment Receipt — Invoice {invoice_number}',
		'ci_receipt_body'        => "Hi {client_name},\n\nThank you! We've received your payment of {amount_paid} for invoice {invoice_number}.\n\nPayment Date: {paid_date}\nPayment Method: {payment_method}\n\nWe appreciate your business!\n{company_name}",
		'ci_show_shipping'       => '0',
		'ci_bcc_email'           => get_option( 'admin_email' ),
	];
	foreach ( $defaults as $key => $val ) {
		if ( false === get_option( $key ) ) update_option( $key, $val );
	}
	ci_download_fpdf();
	flush_rewrite_rules();
}

register_deactivation_hook( CI_FILE, 'flush_rewrite_rules' );

function ci_download_fpdf(): bool {
	$base      = CI_DIR . 'lib/fpdf/';
	$raw       = 'https://raw.githubusercontent.com/Setasign/FPDF/master/';
	$files     = [
		'fpdf.php',
		'font/helvetica.php',
		'font/helveticab.php',
		'font/helveticabi.php',
		'font/helveticai.php',
		'font/courier.php',
		'font/courierb.php',
		'font/courierbi.php',
		'font/courieri.php',
		'font/times.php',
		'font/timesb.php',
		'font/timesbi.php',
		'font/timesi.php',
	];

	wp_mkdir_p( $base . 'font' );

	foreach ( $files as $file ) {
		if ( file_exists( $base . $file ) ) continue;
		$r = wp_remote_get( $raw . $file, [ 'timeout' => 15 ] );
		if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) return false;
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $base . $file, wp_remote_retrieve_body( $r ) );
	}

	return file_exists( $base . 'fpdf.php' ) && file_exists( $base . 'font/helveticab.php' );
}
