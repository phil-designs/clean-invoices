<?php
if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! current_user_can( 'manage_options' ) ) return;

$logo_id  = get_option( 'ci_logo_id' );
$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
?>
<div class="wrap">
	<h1>Clean Invoices — Settings</h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'ci_settings_group' ); ?>

		<!-- Branding -->
		<div class="ci-sett-section">
			<h2>Branding</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th>Logo</th>
					<td>
						<div id="ci-logo-preview" style="margin-bottom:8px;">
							<?php if ( $logo_url ) : ?>
								<img src="<?php echo esc_url( $logo_url ); ?>" style="max-height:80px; display:block; margin-bottom:6px;">
							<?php endif; ?>
						</div>
						<input type="hidden" id="ci_logo_id" name="ci_logo_id" value="<?php echo esc_attr( $logo_id ); ?>">
						<button type="button" id="ci-upload-logo" class="button"><?php echo $logo_id ? 'Change Logo' : 'Upload Logo'; ?></button>
						<?php if ( $logo_id ) : ?>
							<button type="button" id="ci-remove-logo" class="button" style="margin-left:6px;">Remove</button>
						<?php endif; ?>
						<p class="description">Appears in the top-left of your invoices.</p>
					</td>
				</tr>
				<tr>
					<th><label for="ci_company_name">Company / Your Name</label></th>
					<td><input type="text" id="ci_company_name" name="ci_company_name" value="<?php echo esc_attr( get_option( 'ci_company_name' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="ci_address">Street Address</label></th>
					<td><input type="text" id="ci_address" name="ci_address" value="<?php echo esc_attr( get_option( 'ci_address' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="ci_city_state_zip">City, State ZIP</label></th>
					<td><input type="text" id="ci_city_state_zip" name="ci_city_state_zip" value="<?php echo esc_attr( get_option( 'ci_city_state_zip' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="ci_phone">Phone</label></th>
					<td><input type="text" id="ci_phone" name="ci_phone" value="<?php echo esc_attr( get_option( 'ci_phone' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="ci_email">Email</label></th>
					<td><input type="email" id="ci_email" name="ci_email" value="<?php echo esc_attr( get_option( 'ci_email' ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="ci_website">Website</label></th>
					<td><input type="text" id="ci_website" name="ci_website" value="<?php echo esc_attr( get_option( 'ci_website' ) ); ?>" class="regular-text" placeholder="www.example.com"></td>
				</tr>
			</table>
		</div>

		<!-- Payment -->
		<div class="ci-sett-section">
			<h2>Payment Options</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ci_venmo">Venmo Handle</label></th>
					<td>
						<input type="text" id="ci_venmo" name="ci_venmo" value="<?php echo esc_attr( get_option( 'ci_venmo' ) ); ?>" class="regular-text" placeholder="@Your-Handle">
						<p class="description">Printed on invoices as "Venmo: @Handle"</p>
						<?php $venmo_val = ltrim( get_option( 'ci_venmo', '' ), '@' ); ?>
						<div id="ci-venmo-qr" style="margin-top:8px;<?php echo $venmo_val ? '' : 'display:none;'; ?>">
							<img id="ci-venmo-qr-img" src="<?php echo $venmo_val ? esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&format=png&data=' . rawurlencode( 'https://venmo.com/u/' . $venmo_val ) ) : ''; ?>" width="80" height="80" alt="Venmo QR Code">
							<p class="description" style="margin-top:4px;">QR code for Venmo payments</p>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="ci_zelle">Zelle (phone or email)</label></th>
					<td>
						<input type="text" id="ci_zelle" name="ci_zelle" value="<?php echo esc_attr( get_option( 'ci_zelle' ) ); ?>" class="regular-text" placeholder="555-555-5555 or you@email.com">
						<p class="description">Printed on invoices as "Zelle: [your info]"</p>
						<?php $zelle_val = get_option( 'ci_zelle', '' ); ?>
						<div id="ci-zelle-qr" style="margin-top:8px;<?php echo $zelle_val ? '' : 'display:none;'; ?>">
							<img id="ci-zelle-qr-img" src="<?php echo $zelle_val ? esc_url( 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&format=png&data=' . rawurlencode( $zelle_val ) ) : ''; ?>" width="80" height="80" alt="Zelle QR Code">
							<p class="description" style="margin-top:4px;">QR code for Zelle payments</p>
						</div>
					</td>
				</tr>
				<tr>
					<th><label for="ci_check_payment_note">Check Payment Note</label></th>
					<td>
						<?php
						wp_editor(
							get_option( 'ci_check_payment_note', '' ),
							'ci_check_payment_note',
							[
								'textarea_name'  => 'ci_check_payment_note',
								'media_buttons'  => false,
								'textarea_rows'  => 4,
								'tinymce'        => [
									'toolbar1'        => 'bold italic | alignleft aligncenter alignright | fontsizeselect',
									'toolbar2'        => '',
									'statusbar'       => false,
									'resize'          => false,
									'fontsize_formats' => '10pt 11pt 12pt 14pt 16pt 18pt',
								],
								'quicktags'      => false,
							]
						);
						?>
						<p class="description" style="margin-top:6px;">Displays as a highlighted box to the right of the payment QR codes on invoices. Leave blank to hide.</p>
					</td>
				</tr>
			</table>
		</div>

		<!-- Invoice defaults -->
		<div class="ci-sett-section">
			<h2>Invoice Defaults</h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ci_invoice_prefix">Invoice Prefix</label></th>
					<td>
						<input type="text" id="ci_invoice_prefix" name="ci_invoice_prefix" value="<?php echo esc_attr( get_option( 'ci_invoice_prefix', 'INV' ) ); ?>" class="small-text">
						<p class="description">Numbers will be formatted as <code>PREFIX-0001</code></p>
					</td>
				</tr>
				<tr>
					<th><label for="ci_next_invoice_number">Next Invoice Number</label></th>
					<td>
						<input type="number" id="ci_next_invoice_number" name="ci_next_invoice_number" value="<?php echo esc_attr( get_option( 'ci_next_invoice_number', 1 ) ); ?>" min="1" class="small-text">
						<p class="description">The sequence number used for the next new invoice.</p>
					</td>
				</tr>
				<tr>
					<th><label for="ci_default_tax_rate">Default Tax Rate (%)</label></th>
					<td><input type="number" id="ci_default_tax_rate" name="ci_default_tax_rate" value="<?php echo esc_attr( get_option( 'ci_default_tax_rate', '0' ) ); ?>" min="0" max="100" step="0.01" class="small-text"></td>
				</tr>
				<tr>
					<th><label for="ci_payment_terms">Default Payment Terms</label></th>
					<td><input type="text" id="ci_payment_terms" name="ci_payment_terms" value="<?php echo esc_attr( get_option( 'ci_payment_terms', 'Due on receipt' ) ); ?>" class="regular-text" placeholder="Due on receipt"></td>
				</tr>
				<tr>
					<th><label for="ci_show_shipping">Show Shipping Field</label></th>
					<td>
						<label>
							<input type="checkbox" id="ci_show_shipping" name="ci_show_shipping" value="1" <?php checked( get_option( 'ci_show_shipping' ) ); ?>>
							Show a shipping line item on invoices
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="ci_thank_you_message">Thank You Message</label></th>
					<td><input type="text" id="ci_thank_you_message" name="ci_thank_you_message" value="<?php echo esc_attr( get_option( 'ci_thank_you_message', 'Thank you for your business!' ) ); ?>" class="large-text"></td>
				</tr>
			</table>
		</div>

		<!-- Invoice Email -->
		<div class="ci-sett-section">
			<h2>Invoice Email</h2>
			<p class="description" style="margin-bottom:12px;">
				Available tokens: <code>{invoice_number}</code> <code>{invoice_date}</code> <code>{due_date}</code>
				<code>{total}</code> <code>{client_name}</code> <code>{company_name}</code> <code>{payment_terms}</code>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ci_bcc_email">BCC (copy to yourself)</label></th>
					<td><input type="email" id="ci_bcc_email" name="ci_bcc_email" value="<?php echo esc_attr( get_option( 'ci_bcc_email', get_option( 'admin_email' ) ) ); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="ci_email_subject">Subject</label></th>
					<td><input type="text" id="ci_email_subject" name="ci_email_subject" value="<?php echo esc_attr( get_option( 'ci_email_subject' ) ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="ci_email_body">Body</label></th>
					<td><textarea id="ci_email_body" name="ci_email_body" rows="8" class="large-text"><?php echo esc_textarea( get_option( 'ci_email_body' ) ); ?></textarea></td>
				</tr>
			</table>
		</div>

		<!-- Receipt Email -->
		<div class="ci-sett-section">
			<h2>Receipt Email</h2>
			<p class="description" style="margin-bottom:12px;">
				Sent to the client when you click <strong>Send Receipt</strong> on a paid invoice.<br>
				Available tokens: <code>{invoice_number}</code> <code>{total}</code> <code>{amount_paid}</code>
				<code>{balance_due}</code> <code>{paid_date}</code> <code>{payment_method}</code>
				<code>{client_name}</code> <code>{company_name}</code>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="ci_receipt_subject">Subject</label></th>
					<td><input type="text" id="ci_receipt_subject" name="ci_receipt_subject" value="<?php echo esc_attr( get_option( 'ci_receipt_subject', 'Payment Receipt — Invoice {invoice_number}' ) ); ?>" class="large-text"></td>
				</tr>
				<tr>
					<th><label for="ci_receipt_body">Body</label></th>
					<td><textarea id="ci_receipt_body" name="ci_receipt_body" rows="10" class="large-text"><?php echo esc_textarea( get_option( 'ci_receipt_body' ) ); ?></textarea></td>
				</tr>
			</table>
		</div>

		<?php submit_button(); ?>
	</form>
</div>
