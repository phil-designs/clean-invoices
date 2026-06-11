<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class CI_PDF {

	private array $temp_files = [];

	public function generate( int $post_id ): string {
		$fpdf = CI_DIR . 'lib/fpdf/fpdf.php';
		if ( ! file_exists( $fpdf ) || ! file_exists( CI_DIR . 'lib/fpdf/font/helveticab.php' ) ) {
			if ( ! ci_download_fpdf() ) throw new Exception( 'PDF library not available. Install it from the admin notice.' );
		}
		require_once $fpdf;

		$inv = $this->get_data( $post_id );

		$pdf = new FPDF( 'P', 'mm', 'Letter' );
		$pdf->SetMargins( 15, 15, 15 );
		$pdf->SetAutoPageBreak( true, 20 );
		$pdf->AddPage();

		$this->draw_header( $pdf, $inv );
		$this->draw_bill_to( $pdf, $inv );
		$this->draw_items( $pdf, $inv );
		$this->draw_totals( $pdf, $inv );
		$this->draw_footer( $pdf, $inv );

		$output = $pdf->Output( 'S' );

		foreach ( $this->temp_files as $f ) {
			if ( file_exists( $f ) ) wp_delete_file( $f );
		}

		return $output;
	}

	// -------------------------------------------------------
	// Sanitize text for FPDF (decodes HTML entities, converts
	// to Latin-1 so core fonts render correctly)
	// -------------------------------------------------------
	private function t( string $s ): string {
		$s = html_entity_decode( $s, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		// Transliterate to Latin-1 (FPDF core fonts don't support full UTF-8)
		$s = iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s );
		return (string) $s;
	}

	// -------------------------------------------------------
	// Data
	// -------------------------------------------------------

	private function get_data( int $post_id ): array {
		$client_id  = (int) get_post_meta( $post_id, '_ci_client_id', true );
		$line_items = json_decode( get_post_meta( $post_id, '_ci_line_items', true ) ?: '[]', true );

		$venmo = get_option( 'ci_venmo', '' );
		$venmo = ltrim( $venmo, '@' ); // normalise — we add @ ourselves

		$total    = (float) get_post_meta( $post_id, '_ci_total', true );
		$payments = json_decode( get_post_meta( $post_id, '_ci_payments', true ) ?: '[]', true );
		$amt_paid = array_sum( array_column( $payments, 'amount' ) );
		$p_status = get_post_meta( $post_id, '_ci_status', true );

		return [
			'logo_path'       => $this->logo_path(),
			'company_name'    => get_option( 'ci_company_name', '' ),
			'company_address' => get_option( 'ci_address', '' ),
			'company_csz'     => get_option( 'ci_city_state_zip', '' ),
			'company_phone'   => get_option( 'ci_phone', '' ),
			'company_email'   => get_option( 'ci_email', '' ),
			'company_website' => get_option( 'ci_website', '' ),
			'venmo'           => $venmo,
			'zelle'           => get_option( 'ci_zelle', '' ),
			'check_note'      => get_option( 'ci_check_payment_note', '' ),
			'thank_you'       => get_option( 'ci_thank_you_message', 'Thank you for your business!' ),
			'invoice_number'  => get_post_meta( $post_id, '_ci_invoice_number', true ),
			'invoice_date'    => get_post_meta( $post_id, '_ci_invoice_date', true ),
			'due_date'        => get_post_meta( $post_id, '_ci_due_date', true ),
			'notes'           => get_post_meta( $post_id, '_ci_notes', true ),
			'line_items'      => $line_items,
			'subtotal'        => (float) get_post_meta( $post_id, '_ci_subtotal', true ),
			'tax_rate'        => (float) get_post_meta( $post_id, '_ci_tax_rate', true ),
			'tax_amount'      => (float) get_post_meta( $post_id, '_ci_tax_amount', true ),
			'shipping'        => (float) get_post_meta( $post_id, '_ci_shipping', true ),
			'total'           => $total,
			'payments'        => $payments,
			'amount_paid'     => $amt_paid,
			'balance_due'     => ( $p_status === 'paid' && empty( $payments ) ) ? 0.0 : max( 0.0, $total - $amt_paid ),
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
	}

	private function logo_path(): string {
		$id = (int) get_option( 'ci_logo_id' );
		if ( ! $id ) return '';
		$path = get_attached_file( $id );
		return ( $path && file_exists( $path ) ) ? $path : '';
	}

	// -------------------------------------------------------
	// Header
	// -------------------------------------------------------

	private function draw_header( FPDF $pdf, array $inv ): void {
		$w           = 186;
		$logo_bottom = 15; // Y position where company info starts

		// --- Logo (top left) ---
		if ( $inv['logo_path'] ) {
			$size = wp_getimagesize( $inv['logo_path'] );
			if ( $size && $size[0] > 0 && $size[1] > 0 ) {
				$max_w = 50; // mm
				$max_h = 28; // mm
				$ratio = $size[0] / $size[1];
				if ( $ratio >= ( $max_w / $max_h ) ) {
					$logo_w = $max_w;
					$logo_h = $max_w / $ratio;
				} else {
					$logo_h = $max_h;
					$logo_w = $max_h * $ratio;
				}
			} else {
				$logo_w = 50;
				$logo_h = 0;
			}
			$pdf->Image( $inv['logo_path'], 15, 15, $logo_w, $logo_h ?: 0 );
			$logo_bottom = 15 + ( $logo_h ?: 28 ) + 5;
		}

		// --- "INVOICE" heading (top right) ---
		$pdf->SetFont( 'Helvetica', 'B', 26 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->SetXY( 15, 12 );
		$pdf->Cell( $w, 12, 'INVOICE', 0, 0, 'R' );

		// --- Invoice details (right column, below heading) ---
		$details = array_filter( [
			'Invoice #' => $inv['invoice_number'],
			'Date'      => $inv['invoice_date'] ? (string) wp_date( 'F j, Y', strtotime( $inv['invoice_date'] ) ) : '',
			'Due'       => $inv['due_date'] ? (string) wp_date( 'F j, Y', strtotime( $inv['due_date'] ) ) : '',
		] );
		$ry = 28;
		foreach ( $details as $label => $value ) {
			$pdf->SetFont( 'Helvetica', 'B', 9 );
			$pdf->SetXY( 120, $ry );
			$pdf->Cell( 30, 5, $this->t( $label ) . ':', 0, 0, 'R' );
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->SetTextColor( 60, 60, 60 );
			$pdf->Cell( 51, 5, $this->t( $value ), 0, 0, 'R' );
			$pdf->SetTextColor( 30, 30, 30 );
			$ry += 5;
		}

		// --- Company info (left, BELOW the logo) ---
		$pdf->SetFont( 'Helvetica', 'B', 10 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->SetXY( 15, $logo_bottom );
		$pdf->Cell( 90, 5, $this->t( $inv['company_name'] ), 0, 1 );

		$pdf->SetFont( 'Helvetica', '', 9 );
		$pdf->SetTextColor( 80, 80, 80 );
		foreach ( array_filter( [
			$inv['company_address'],
			$inv['company_csz'],
			$inv['company_phone'],
			$inv['company_email'],
			$inv['company_website'],
		] ) as $line ) {
			$pdf->SetX( 15 );
			$pdf->Cell( 90, 4.5, $this->t( $line ), 0, 1 );
		}

		// --- Separator ---
		$bottom = max( $pdf->GetY() + 3, $ry + 3 );
		$pdf->SetDrawColor( 200, 200, 200 );
		$pdf->Line( 15, $bottom, 201, $bottom );
		$pdf->SetY( $bottom + 4 );
	}

	// -------------------------------------------------------
	// Bill To
	// -------------------------------------------------------

	private function draw_bill_to( FPDF $pdf, array $inv ): void {
		$y = $pdf->GetY();

		// Bill To (left)
		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$pdf->SetTextColor( 120, 120, 120 );
		$pdf->SetXY( 15, $y );
		$pdf->Cell( 80, 5, 'BILL TO', 0, 1 );

		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->SetFont( 'Helvetica', 'B', 11 );
		$pdf->SetX( 15 );
		$pdf->Cell( 80, 6, $this->t( $inv['client_name'] ), 0, 1 );

		if ( ! empty( $inv['client_contact'] ) ) {
			$pdf->SetFont( 'Helvetica', 'I', 9 );
			$pdf->SetTextColor( 60, 60, 60 );
			$pdf->SetX( 15 );
			$pdf->Cell( 80, 4.5, $this->t( 'c/o: ' . $inv['client_contact'] ), 0, 1 );
		}

		$pdf->SetFont( 'Helvetica', '', 9 );
		$pdf->SetTextColor( 60, 60, 60 );
		foreach ( array_filter( [
			$inv['client_company'],
			$inv['client_address1'],
			$inv['client_address2'],
			$inv['client_csz'],
			$inv['client_phone'],
			$inv['client_email'],
		] ) as $line ) {
			$pdf->SetX( 15 );
			$pdf->Cell( 80, 4.5, $this->t( $line ), 0, 1 );
		}

		// Amount / Balance due (right)
		$has_payments  = ! empty( $inv['payments'] );
		$display_label = $has_payments ? 'BALANCE DUE' : 'AMOUNT DUE';
		$display_value = $has_payments ? $inv['balance_due'] : $inv['total'];

		$pdf->SetXY( 120, $y );
		$pdf->SetFont( 'Helvetica', 'B', 8 );
		$pdf->SetTextColor( 120, 120, 120 );
		$pdf->Cell( 81, 5, $display_label, 0, 0, 'R' );
		$pdf->SetXY( 120, $y + 6 );
		$pdf->SetFont( 'Helvetica', 'B', 22 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->Cell( 81, 10, '$' . number_format( $display_value, 2 ), 0, 1, 'R' );

		// Separator
		$bottom = max( $pdf->GetY(), $y + 36 );
		$pdf->SetDrawColor( 200, 200, 200 );
		$pdf->Line( 15, $bottom, 201, $bottom );
		$pdf->SetY( $bottom + 4 );
	}

	// -------------------------------------------------------
	// Line items
	// -------------------------------------------------------

	private function draw_items( FPDF $pdf, array $inv ): void {
		$dw = 100;
		$qw = 20;
		$rw = 33;
		$aw = 33;

		// Header row
		$pdf->SetFillColor( 240, 240, 240 );
		$pdf->SetFont( 'Helvetica', 'B', 9 );
		$pdf->SetTextColor( 60, 60, 60 );
		$pdf->SetX( 15 );
		$pdf->Cell( $dw, 7, 'DESCRIPTION', 0, 0, 'L', true );
		$pdf->Cell( $qw, 7, 'QTY',         0, 0, 'C', true );
		$pdf->Cell( $rw, 7, 'RATE',         0, 0, 'R', true );
		$pdf->Cell( $aw, 7, 'AMOUNT',       0, 1, 'R', true );

		$pdf->SetTextColor( 30, 30, 30 );
		$fill = false;

		foreach ( (array) $inv['line_items'] as $item ) {
			$desc   = $this->t( $item['description'] ?? '' );
			$detail = $this->t( $item['detail']      ?? '' );
			$qty    = $item['quantity'] ?? 1;
			$rate   = (float) ( $item['rate']   ?? 0 );
			$amount = (float) ( $item['amount'] ?? 0 );

			// Pre-calculate detail text line count so we can size the row correctly.
			$detail_lh    = 4.0;
			$detail_lines = 0;
			if ( $detail ) {
				$pdf->SetFont( 'Helvetica', 'I', 8 );
				$detail_lines = $this->count_text_lines( $pdf, $detail, $dw );
			}

			// Row height: 2 top pad + 6 desc + (detail lines × lh + 2 gap) + 2 bottom pad
			$row_h = 2 + 6 + ( $detail ? ( $detail_lines * $detail_lh + 2 ) : 0 ) + 2;
			$row_h = max( $row_h, 11.0 );

			if ( $pdf->GetY() + $row_h > 260 ) $pdf->AddPage();

			$row_y = $pdf->GetY();

			// Background fill
			if ( $fill ) {
				$pdf->SetFillColor( 250, 250, 250 );
				$pdf->Rect( 15, $row_y, $dw + $qw + $rw + $aw, $row_h, 'F' );
			}

			// Description (bold)
			$pdf->SetXY( 15, $row_y + 2 );
			$pdf->SetFont( 'Helvetica', 'B', 9 );
			$pdf->SetTextColor( 30, 30, 30 );
			$pdf->Cell( $dw, 6, $desc, 0, 0, 'L' );

			// Detail text (italic, wraps to multiple lines)
			if ( $detail ) {
				$pdf->SetXY( 15, $row_y + 2 + 6 );
				$pdf->SetFont( 'Helvetica', 'I', 8 );
				$pdf->SetTextColor( 100, 100, 100 );
				$pdf->MultiCell( $dw, $detail_lh, $detail, 0, 'L' );
				$pdf->SetTextColor( 30, 30, 30 );
			}

			// Numeric columns — vertically centred within the row
			$num_y = $row_y + ( $row_h - 6 ) / 2;
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->SetTextColor( 30, 30, 30 );
			$pdf->SetXY( 15 + $dw, $num_y );
			$pdf->Cell( $qw, 6, (string) $qty,                     0, 0, 'C' );
			$pdf->Cell( $rw, 6, '$' . number_format( $rate, 2 ),   0, 0, 'R' );
			$pdf->Cell( $aw, 6, '$' . number_format( $amount, 2 ), 0, 0, 'R' );

			// Advance Y to next row
			$pdf->SetXY( 15, $row_y + $row_h );

			$fill = ! $fill;
		}

		$pdf->SetDrawColor( 200, 200, 200 );
		$pdf->Line( 15, $pdf->GetY(), 201, $pdf->GetY() );
		$pdf->Ln( 3 );
	}

	// Count the number of lines FPDF will need to render $text in a MultiCell of given $width.
	private function count_text_lines( FPDF $pdf, string $text, float $width ): int {
		$total = 0;
		foreach ( explode( "\n", $text ) as $para ) {
			$words  = preg_split( '/\s+/', trim( $para ) );
			$line_w = 0.0;
			$lines  = 1; // every paragraph has at least one line
			foreach ( $words as $word ) {
				if ( $word === '' ) continue;
				$ww = $pdf->GetStringWidth( $word );
				$sw = $pdf->GetStringWidth( ' ' );
				if ( $line_w > 0 && $line_w + $sw + $ww > $width ) {
					$lines++;
					$line_w = $ww;
				} else {
					$line_w += ( $line_w > 0 ? $sw : 0.0 ) + $ww;
				}
			}
			$total += $lines;
		}
		return max( 1, $total );
	}

	// -------------------------------------------------------
	// Totals
	// -------------------------------------------------------

	private function draw_totals( FPDF $pdf, array $inv ): void {
		$lw = 50;
		$vw = 36;
		$lx = 201 - $lw - $vw;

		$rows = [ [ 'Subtotal', number_format( $inv['subtotal'], 2 ) ] ];
		if ( $inv['tax_rate'] > 0 ) {
			$rows[] = [ 'Tax (' . $inv['tax_rate'] . '%)', number_format( $inv['tax_amount'], 2 ) ];
		}
		if ( $inv['shipping'] > 0 ) {
			$rows[] = [ 'Shipping', number_format( $inv['shipping'], 2 ) ];
		}

		$pdf->SetTextColor( 60, 60, 60 );
		foreach ( $rows as $row ) {
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->SetXY( $lx, $pdf->GetY() );
			$pdf->Cell( $lw, 6, $row[0], 0, 0, 'R' );
			$pdf->Cell( $vw, 6, '$' . $row[1], 0, 1, 'R' );
		}

		// Divider line above Total (matches the preview's border-top)
		$pdf->SetDrawColor( 30, 30, 30 );
		$pdf->Line( $lx, $pdf->GetY() + 2, 201, $pdf->GetY() + 2 );
		$pdf->SetDrawColor( 200, 200, 200 );

		$pdf->SetFont( 'Helvetica', 'B', 11 );
		$pdf->SetTextColor( 30, 30, 30 );
		$pdf->SetXY( $lx, $pdf->GetY() + 4 );
		$pdf->Cell( $lw, 7, 'Total', 0, 0, 'R' );
		$pdf->Cell( $vw, 7, '$' . number_format( $inv['total'], 2 ), 0, 1, 'R' );

		// Payment history rows
		if ( ! empty( $inv['payments'] ) ) {
			$type_labels = [ 'deposit' => 'Deposit', 'installment' => 'Installment', 'payment' => 'Payment' ];
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->SetTextColor( 50, 120, 50 );
			foreach ( $inv['payments'] as $pmt ) {
				$label = $type_labels[ $pmt['type'] ?? 'payment' ] ?? 'Payment';
				$parts = array_filter( [
					! empty( $pmt['date'] )   ? (string) wp_date( 'M j, Y', strtotime( $pmt['date'] ) ) : '',
					! empty( $pmt['method'] ) ? $pmt['method'] : '',
				] );
				$label .= $parts ? ' (' . implode( ' / ', $parts ) . ')' : '';
				$pdf->SetXY( $lx, $pdf->GetY() );
				$pdf->Cell( $lw, 5.5, $this->t( $label ) . ':', 0, 0, 'R' );
				$pdf->Cell( $vw, 5.5, '-$' . number_format( (float) ( $pmt['amount'] ?? 0 ), 2 ), 0, 1, 'R' );
			}

			// Balance due
			$pdf->SetFont( 'Helvetica', 'B', 11 );
			$pdf->SetTextColor( 30, 30, 30 );
			$pdf->SetXY( $lx, $pdf->GetY() + 2 );
			$pdf->Cell( $lw, 7, 'BALANCE DUE:', 0, 0, 'R' );
			$pdf->Cell( $vw, 7, '$' . number_format( $inv['balance_due'], 2 ), 0, 1, 'R' );
		}

		$pdf->Ln( 4 );

		if ( $inv['notes'] ) {
			$pdf->SetFont( 'Helvetica', 'B', 8 );
			$pdf->SetTextColor( 100, 100, 100 );
			$pdf->SetX( 15 );
			$pdf->Cell( 100, 5, 'NOTES', 0, 1 );
			$pdf->SetFont( 'Helvetica', '', 9 );
			$pdf->SetTextColor( 60, 60, 60 );
			$pdf->SetX( 15 );
			$pdf->MultiCell( 120, 5, $this->t( $inv['notes'] ) );
		}
	}

	// -------------------------------------------------------
	// Footer
	// -------------------------------------------------------

	private function draw_footer( FPDF $pdf, array $inv ): void {
		$pdf->Ln( 6 );

		$has_venmo = ! empty( $inv['venmo'] );
		$has_zelle = ! empty( $inv['zelle'] );
		$has_check = ! empty( $inv['check_note'] );

		if ( $has_venmo || $has_zelle || $has_check ) {
			$pdf->SetFont( 'Helvetica', 'B', 8 );
			$pdf->SetTextColor( 100, 100, 100 );
			$pdf->SetX( 15 );
			$pdf->Cell( 0, 5, 'PAYMENT OPTIONS', 0, 1 );
			$pdf->Ln( 2 );

			$y = $pdf->GetY();

			if ( $has_check ) {
				$qr_size = 18;
				$pad     = 4.0;
				$line_h  = 5.0;

				// Measure actual label widths so we can position the Zelle QR far enough
				// right that the Venmo label won't run into it — matching the preview layout
				// where each label sits directly under its own QR code on the same row.
				$venmo_label = $has_venmo ? 'Venmo: @' . $this->t( $inv['venmo'] ) : '';
				$zelle_label = $has_zelle ? 'Zelle: '  . $this->t( $inv['zelle'] ) : '';
				$pdf->SetFont( 'Helvetica', '', 8 );
				$venmo_lw = $venmo_label ? $pdf->GetStringWidth( $venmo_label ) : 0.0;
				$zelle_lw = $zelle_label ? $pdf->GetStringWidth( $zelle_label ) : 0.0;

				// Zelle QR starts after whichever is wider: the Venmo QR+gap or the Venmo label+gap
				$zelle_x = $has_venmo ? max( 15 + $qr_size + 5, 15 + $venmo_lw + 3 ) : 15.0;

				// right_x derives from the actual right edge of both QRs and their labels
				if ( $has_venmo && $has_zelle ) {
					$left_end = max( $zelle_x + $qr_size, $zelle_x + $zelle_lw );
				} elseif ( $has_venmo ) {
					$left_end = max( 15 + $qr_size, 15 + $venmo_lw );
				} elseif ( $has_zelle ) {
					$left_end = max( 15 + $qr_size, 15 + $zelle_lw );
				} else {
					$left_end = 15.0;
				}
				$right_x = min( 125.0, $left_end + 5.0 ); // ensure check box is ≥76mm wide
				$right_w = 201.0 - $right_x;

				// Left column: QR codes
				if ( $has_venmo ) {
					$venmo_qr = $this->fetch_qr( 'https://venmo.com/u/' . $inv['venmo'] );
					if ( $venmo_qr ) $pdf->Image( $venmo_qr, 15, $y, $qr_size, $qr_size );
				}
				if ( $has_zelle ) {
					$zelle_qr = $this->fetch_qr( $inv['zelle'] );
					$qr_x = $has_venmo ? $zelle_x : 15.0;
					if ( $zelle_qr ) $pdf->Image( $zelle_qr, $qr_x, $y, $qr_size, $qr_size );
				}

				// Labels on the same row, each directly under its own QR code
				$label_y = $y + $qr_size + 2;
				$pdf->SetFont( 'Helvetica', '', 8 );
				$pdf->SetTextColor( 60, 60, 60 );
				if ( $has_venmo ) {
					$pdf->SetXY( 15, $label_y );
					$pdf->Cell( $venmo_lw + 1, 4, $venmo_label, 0, 0 );
				}
				if ( $has_zelle ) {
					$pdf->SetXY( $has_venmo ? $zelle_x : 15.0, $label_y );
					$pdf->Cell( $zelle_lw + 1, 4, $zelle_label, 0, 0 );
				}

				// Right column: check note box, text centered with bold support
				$segs     = $this->parse_bold_html( $inv['check_note'] );
				$usable_w = $right_w - 2 * $pad;
				$text_h   = $this->calc_bold_height( $pdf, $segs, $usable_w, $line_h );
				$qr_total = ( $has_venmo || $has_zelle ) ? (float) ( $qr_size + 2 + 4 ) : 0.0;
				$box_h    = max( $text_h + 2 * $pad + 2, $qr_total );
				$text_y   = $y + ( $box_h - $text_h ) / 2;

				$pdf->SetFillColor( 247, 247, 247 );
				$pdf->SetDrawColor( 200, 200, 200 );
				$pdf->Rect( $right_x, $y, $right_w, $box_h, 'DF' );

				$pdf->SetTextColor( 60, 60, 60 );
				$this->render_bold_centered( $pdf, $segs, $right_x + $pad, $text_y, $usable_w, $line_h );

				$pdf->SetY( $y + $box_h + 4 );

			} else {
				// Original full-width QR layout (no check note)
				$qr_size = 22;

				if ( $has_venmo && $has_zelle ) {
					$venmo_qr = $this->fetch_qr( 'https://venmo.com/u/' . $inv['venmo'] );
					$zelle_qr = $this->fetch_qr( $inv['zelle'] );

					if ( $venmo_qr ) $pdf->Image( $venmo_qr, 15, $y, $qr_size, $qr_size );
					$pdf->SetFont( 'Helvetica', 'B', 9 );
					$pdf->SetTextColor( 30, 30, 30 );
					$pdf->SetXY( 40, $y + 3 );
					$pdf->Cell( 65, 5, 'Venmo', 0, 1 );
					$pdf->SetFont( 'Helvetica', '', 9 );
					$pdf->SetTextColor( 60, 60, 60 );
					$pdf->SetXY( 40, $y + 9 );
					$pdf->Cell( 65, 5, '@' . $this->t( $inv['venmo'] ), 0, 1 );

					if ( $zelle_qr ) $pdf->Image( $zelle_qr, 108, $y, $qr_size, $qr_size );
					$pdf->SetFont( 'Helvetica', 'B', 9 );
					$pdf->SetTextColor( 30, 30, 30 );
					$pdf->SetXY( 133, $y + 3 );
					$pdf->Cell( 65, 5, 'Zelle', 0, 1 );
					$pdf->SetFont( 'Helvetica', '', 9 );
					$pdf->SetTextColor( 60, 60, 60 );
					$pdf->SetXY( 133, $y + 9 );
					$pdf->Cell( 65, 5, $this->t( $inv['zelle'] ), 0, 1 );

				} elseif ( $has_venmo ) {
					$venmo_qr = $this->fetch_qr( 'https://venmo.com/u/' . $inv['venmo'] );
					if ( $venmo_qr ) $pdf->Image( $venmo_qr, 15, $y, $qr_size, $qr_size );
					$pdf->SetFont( 'Helvetica', 'B', 9 );
					$pdf->SetTextColor( 30, 30, 30 );
					$pdf->SetXY( 40, $y + 3 );
					$pdf->Cell( 100, 5, 'Venmo', 0, 1 );
					$pdf->SetFont( 'Helvetica', '', 9 );
					$pdf->SetTextColor( 60, 60, 60 );
					$pdf->SetXY( 40, $y + 9 );
					$pdf->Cell( 100, 5, '@' . $this->t( $inv['venmo'] ), 0, 1 );

				} else {
					$zelle_qr = $this->fetch_qr( $inv['zelle'] );
					if ( $zelle_qr ) $pdf->Image( $zelle_qr, 15, $y, $qr_size, $qr_size );
					$pdf->SetFont( 'Helvetica', 'B', 9 );
					$pdf->SetTextColor( 30, 30, 30 );
					$pdf->SetXY( 40, $y + 3 );
					$pdf->Cell( 100, 5, 'Zelle', 0, 1 );
					$pdf->SetFont( 'Helvetica', '', 9 );
					$pdf->SetTextColor( 60, 60, 60 );
					$pdf->SetXY( 40, $y + 9 );
					$pdf->Cell( 100, 5, $this->t( $inv['zelle'] ), 0, 1 );
				}

				$pdf->SetY( $y + $qr_size + 4 );
			}
		}

		if ( $inv['thank_you'] ) {
			$pdf->SetDrawColor( 200, 200, 200 );
			$pdf->Line( 15, $pdf->GetY(), 201, $pdf->GetY() );
			$pdf->Ln( 5 );
			$pdf->SetFont( 'Helvetica', 'B', 10 );
			$pdf->SetTextColor( 30, 30, 30 );
			$pdf->Cell( 0, 6, $this->t( $inv['thank_you'] ), 0, 1, 'C' );
		}
	}

	// Parse HTML with <strong>/<b> tags into segments with bold flags.
	// Applies Latin-1 transliteration so segments are FPDF-ready.
	private function parse_bold_html( string $html ): array {
		$html  = preg_replace( '/<\/p\s*>/i', ' ', $html );
		$html  = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$parts = preg_split( '/(<(?:strong|b)[^>]*>|<\/(?:strong|b)>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE );

		$segments = [];
		$bold     = false;
		foreach ( $parts as $part ) {
			if ( preg_match( '/^<(?:strong|b)/i', $part ) ) {
				$bold = true;
			} elseif ( preg_match( '/^<\/(?:strong|b)/i', $part ) ) {
				$bold = false;
			} else {
				$text = wp_strip_all_tags( $part );
				$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$text = (string) iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text );
				if ( trim( $text ) !== '' ) {
					$segments[] = [ 'text' => $text, 'bold' => $bold ];
				}
			}
		}
		return $segments;
	}

	// Flatten segments to a word list, greedy-pack into lines, return total height.
	// Must use the same algorithm as render_bold_centered() so the box is sized correctly.
	private function calc_bold_height( FPDF $pdf, array $segs, float $usable_w, float $line_h ): float {
		$all_words = [];
		foreach ( $segs as $seg ) {
			foreach ( preg_split( '/\s+/', trim( $seg['text'] ) ) as $word ) {
				if ( $word !== '' ) $all_words[] = [ 'text' => $word, 'bold' => $seg['bold'] ];
			}
		}

		$line_w = 0.0;
		$lines  = 1;
		foreach ( $all_words as $word ) {
			$pdf->SetFont( 'Helvetica', $word['bold'] ? 'B' : '', 9 );
			$ww = $pdf->GetStringWidth( $word['text'] );
			$sw = $pdf->GetStringWidth( ' ' );
			if ( $line_w > 0 && $line_w + $sw + $ww > $usable_w ) {
				$lines++;
				$line_w = $ww;
			} else {
				$line_w += ( $line_w > 0 ? $sw : 0.0 ) + $ww;
			}
		}
		return $lines * $line_h;
	}

	// Greedy word-wrap with per-line centering and inline bold support.
	// Each line is measured then rendered from its horizontal midpoint.
	private function render_bold_centered( FPDF $pdf, array $segs, float $x, float $y, float $usable_w, float $line_h ): void {
		// Flatten to a single word list preserving bold flag
		$all_words = [];
		foreach ( $segs as $seg ) {
			foreach ( preg_split( '/\s+/', trim( $seg['text'] ) ) as $word ) {
				if ( $word !== '' ) $all_words[] = [ 'text' => $word, 'bold' => $seg['bold'] ];
			}
		}

		if ( empty( $all_words ) ) return;

		// Greedy line-packing (mirrors calc_bold_height)
		$lines  = [];
		$line   = [];
		$line_w = 0.0;
		foreach ( $all_words as $word ) {
			$pdf->SetFont( 'Helvetica', $word['bold'] ? 'B' : '', 9 );
			$ww = $pdf->GetStringWidth( $word['text'] );
			$sw = $pdf->GetStringWidth( ' ' );
			if ( $line_w > 0 && $line_w + $sw + $ww > $usable_w ) {
				$lines[] = $line;
				$line    = [ $word ];
				$line_w  = $ww;
			} else {
				$line[]  = $word;
				$line_w += ( $line_w > 0 ? $sw : 0.0 ) + $ww;
			}
		}
		if ( ! empty( $line ) ) $lines[] = $line;

		// Render each line: measure actual width, then start from the center offset
		foreach ( $lines as $i => $ln ) {
			$lw = 0.0;
			$n  = count( $ln );
			foreach ( $ln as $j => $word ) {
				$pdf->SetFont( 'Helvetica', $word['bold'] ? 'B' : '', 9 );
				$lw += $pdf->GetStringWidth( $word['text'] );
				if ( $j < $n - 1 ) $lw += $pdf->GetStringWidth( ' ' );
			}
			$pdf->SetXY( $x + ( $usable_w - $lw ) / 2.0, $y + $i * $line_h );
			foreach ( $ln as $j => $word ) {
				$pdf->SetFont( 'Helvetica', $word['bold'] ? 'B' : '', 9 );
				$pdf->Write( $line_h, $word['text'] . ( $j < $n - 1 ? ' ' : '' ) );
			}
		}
	}

	private function fetch_qr( string $data ): string {
		if ( ! $data ) return '';
		$url  = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&format=png&data=' . rawurlencode( $data );
		$resp = wp_remote_get( $url, [ 'timeout' => 10 ] );
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) return '';
		$tmp = wp_upload_dir()['basedir'] . '/ci-qr-' . md5( $data ) . '.png';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( $tmp, wp_remote_retrieve_body( $resp ) );
		if ( ! file_exists( $tmp ) ) return '';
		$this->temp_files[] = $tmp;
		return $tmp;
	}
}
