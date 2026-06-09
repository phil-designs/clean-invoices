<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

$options = [
	'ci_logo_id', 'ci_company_name', 'ci_address', 'ci_city_state_zip',
	'ci_phone', 'ci_email', 'ci_website', 'ci_venmo', 'ci_zelle',
	'ci_invoice_prefix', 'ci_next_invoice_number', 'ci_default_tax_rate',
	'ci_payment_terms', 'ci_thank_you_message', 'ci_email_subject',
	'ci_email_body', 'ci_bcc_email', 'ci_show_shipping',
];
foreach ( $options as $opt ) delete_option( $opt );

$posts = get_posts( [ 'post_type' => [ 'ci_invoice', 'ci_client' ], 'numberposts' => -1, 'post_status' => 'any' ] );
foreach ( $posts as $post ) wp_delete_post( $post->ID, true );
