<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Settings {

	public function init(): void {
		add_action( 'admin_init', [ $this, 'register' ] );
	}

	public function register(): void {
		$opts = [
			'ci_logo_id', 'ci_company_name', 'ci_address', 'ci_city_state_zip',
			'ci_phone', 'ci_email', 'ci_website',
			'ci_venmo', 'ci_zelle',
			'ci_invoice_prefix', 'ci_next_invoice_number', 'ci_default_tax_rate',
			'ci_payment_terms', 'ci_show_shipping', 'ci_thank_you_message',
			'ci_bcc_email', 'ci_email_subject', 'ci_email_body',
			'ci_receipt_subject', 'ci_receipt_body',
		];
		foreach ( $opts as $opt ) {
			register_setting( 'ci_settings_group', $opt, [
				'sanitize_callback' => [ $this, 'sanitize' ],
			] );
		}

		// Registered separately — allows limited HTML from the visual editor.
		register_setting( 'ci_settings_group', 'ci_check_payment_note', [
			'sanitize_callback' => [ $this, 'sanitize_html' ],
		] );
	}

	public function sanitize( $value ) {
		if ( is_array( $value ) ) return array_map( 'sanitize_text_field', $value );
		if ( strpos( $value, "\n" ) !== false ) return sanitize_textarea_field( $value );
		return sanitize_text_field( $value );
	}

	public function sanitize_html( $value ): string {
		return wp_kses( wp_unslash( (string) $value ), [
			'p'      => [ 'style' => true ],
			'strong' => [],
			'b'      => [],
			'em'     => [],
			'i'      => [],
			'br'     => [],
			'span'   => [ 'style' => true ],
		] );
	}
}
