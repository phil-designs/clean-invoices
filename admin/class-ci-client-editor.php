<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_Client_Editor {

	public function init(): void {
		add_action( 'add_meta_boxes',      [ $this, 'add_box' ] );
		add_action( 'save_post_ci_client', [ $this, 'save' ], 10, 2 );
		add_filter( 'enter_title_here',    [ $this, 'placeholder' ] );
	}

	public function add_box(): void {
		add_meta_box( 'ci-client-details', 'Client Details', [ $this, 'box' ], 'ci_client', 'normal', 'high' );
	}

	public function placeholder( string $text ): string {
		return get_post_type() === 'ci_client' ? 'Contact name' : $text;
	}

	public function box( WP_Post $post ): void {
		wp_nonce_field( 'ci_client_save', 'ci_client_nonce' );
		$id = $post->ID;

		$fields = [
			'ci_contact_name' => [ 'Contact Name',   'text',     'e.g. Jane Smith (shown as c/o on invoice)' ],
			'ci_company'      => [ 'Company',         'text',     '' ],
			'ci_email'        => [ 'Email',           'email',    '' ],
			'ci_phone'        => [ 'Phone',           'text',     '' ],
			'ci_address_1'    => [ 'Address',         'text',     '' ],
			'ci_address_2'    => [ 'Address Line 2',  'text',     '' ],
			'ci_city'         => [ 'City',            'text',     '' ],
			'ci_state'        => [ 'State',           'text',     '' ],
			'ci_zip'          => [ 'ZIP',             'text',     '' ],
			'ci_notes'        => [ 'Notes',           'textarea', '' ],
		];
		?>
		<table class="form-table ci-details-table" role="presentation">
			<?php foreach ( $fields as $key => [ $label, $type, $placeholder ] ) :
				$val = get_post_meta( $id, '_' . $key, true );
			?>
			<tr>
				<th><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
				<td>
					<?php if ( $type === 'textarea' ) : ?>
					<textarea
						id="<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $key ); ?>"
						class="regular-text"
						rows="4"
						<?php echo $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
					><?php echo esc_textarea( $val ); ?></textarea>
					<?php else : ?>
					<input
						type="<?php echo esc_attr( $type ); ?>"
						id="<?php echo esc_attr( $key ); ?>"
						name="<?php echo esc_attr( $key ); ?>"
						value="<?php echo esc_attr( $val ); ?>"
						class="regular-text"
						<?php echo $placeholder ? 'placeholder="' . esc_attr( $placeholder ) . '"' : ''; ?>
					>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public function save( int $id, WP_Post $post ): void {
		if ( ! isset( $_POST['ci_client_nonce'] ) ) return;
		if ( ! wp_verify_nonce( sanitize_key( $_POST['ci_client_nonce'] ), 'ci_client_save' ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( ! current_user_can( 'edit_post', $id ) ) return;

		$fields = [ 'ci_contact_name', 'ci_company', 'ci_email', 'ci_phone', 'ci_address_1', 'ci_address_2', 'ci_city', 'ci_state', 'ci_zip' ];
		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $id, '_' . $field, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		if ( isset( $_POST['ci_notes'] ) ) {
			update_post_meta( $id, '_ci_notes', sanitize_textarea_field( wp_unslash( $_POST['ci_notes'] ) ) );
		}
	}
}
