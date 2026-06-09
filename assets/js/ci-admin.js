jQuery( function ( $ ) {
	if ( typeof ci === 'undefined' ) {
		console.error( 'Clean Invoices: ci vars not localised — scripts may not be loading correctly.' );
		return;
	}

	// -------------------------------------------------------
	// QR code live preview (settings page)
	// -------------------------------------------------------
	function updateQr( val, $img, $wrap, type ) {
		val = $.trim( val ).replace( /^@+/, '' );
		if ( ! val ) { $wrap.hide(); return; }
		var data = type === 'venmo' ? 'https://venmo.com/u/' + val : val;
		$img.attr( 'src', 'https://api.qrserver.com/v1/create-qr-code/?size=100x100&format=png&data=' + encodeURIComponent( data ) );
		$wrap.show();
	}

	$( '#ci_venmo' ).on( 'input', function () {
		updateQr( $( this ).val(), $( '#ci-venmo-qr-img' ), $( '#ci-venmo-qr' ), 'venmo' );
	} );

	$( '#ci_zelle' ).on( 'input', function () {
		updateQr( $( this ).val(), $( '#ci-zelle-qr-img' ), $( '#ci-zelle-qr' ), 'zelle' );
	} );

	// -------------------------------------------------------
	// Logo uploader (settings page)
	// -------------------------------------------------------
	var mediaFrame;
	$( '#ci-upload-logo' ).on( 'click', function ( e ) {
		e.preventDefault();
		if ( mediaFrame ) { mediaFrame.open(); return; }
		mediaFrame = wp.media( { title: 'Select Logo', button: { text: 'Use this image' }, multiple: false } );
		mediaFrame.on( 'select', function () {
			var att = mediaFrame.state().get( 'selection' ).first().toJSON();
			$( '#ci_logo_id' ).val( att.id );
			$( '#ci-logo-preview' ).html( '<img src="' + att.url + '" style="max-height:80px;display:block;margin-bottom:6px;">' );
			$( '#ci-upload-logo' ).text( 'Change Logo' );
		} );
		mediaFrame.open();
	} );

	$( '#ci-remove-logo' ).on( 'click', function () {
		$( '#ci_logo_id' ).val( '' );
		$( '#ci-logo-preview' ).html( '' );
		$( '#ci-upload-logo' ).text( 'Upload Logo' );
		$( this ).hide();
	} );

	// -------------------------------------------------------
	// Status field — show/hide paid fields
	// -------------------------------------------------------
	$( '#ci_status' ).on( 'change', function () {
		$( '#ci-paid-fields' ).toggle( $( this ).val() === 'paid' );
	} );

	// -------------------------------------------------------
	// Line items
	// -------------------------------------------------------
	function rowTpl() {
		return '<tr class="ci-item-row">' +
			'<td><input type="text" class="ci-desc" placeholder="Description"></td>' +
			'<td><input type="text" class="ci-detail" placeholder="Optional sub-detail"></td>' +
			'<td><input type="number" class="ci-qty" value="1" min="0" step="any"></td>' +
			'<td><input type="number" class="ci-rate" value="0" min="0" step="0.01" placeholder="0.00"></td>' +
			'<td class="ci-amount">$0.00</td>' +
			'<td><button type="button" class="ci-remove-row button-link-delete">&#x2715;</button></td>' +
		'</tr>';
	}

	$( '#ci-add-row' ).on( 'click', function () {
		$( '#ci-items-body' ).append( rowTpl() );
	} );

	$( '#ci-items-body' ).on( 'click', '.ci-remove-row', function () {
		$( this ).closest( 'tr' ).remove();
		recalc();
	} );

	$( '#ci-items-body' ).on( 'input', '.ci-qty, .ci-rate', function () {
		var $row  = $( this ).closest( 'tr' );
		var qty   = parseFloat( $row.find( '.ci-qty' ).val() )  || 0;
		var rate  = parseFloat( $row.find( '.ci-rate' ).val() ) || 0;
		var amt   = qty * rate;
		$row.find( '.ci-amount' ).text( '$' + amt.toFixed( 2 ) );
		recalc();
	} );

	$( '#ci_tax_rate, #ci_shipping' ).on( 'input', recalc );

	function recalc() {
		var subtotal = 0;
		$( '#ci-items-body .ci-item-row' ).each( function () {
			var qty  = parseFloat( $( this ).find( '.ci-qty' ).val() )  || 0;
			var rate = parseFloat( $( this ).find( '.ci-rate' ).val() ) || 0;
			subtotal += qty * rate;
		} );

		var taxRate  = parseFloat( $( '#ci_tax_rate' ).val() )  || 0;
		var shipping = parseFloat( $( '#ci_shipping' ).val() ) || 0;
		var tax      = subtotal * ( taxRate / 100 );
		var total    = subtotal + tax + shipping;

		$( '#ci-subtotal-display' ).text( '$' + subtotal.toFixed( 2 ) );
		$( '#ci-tax-display' ).text( '$' + tax.toFixed( 2 ) );
		$( '#ci-total-display' ).text( '$' + total.toFixed( 2 ) );

		$( '#ci_subtotal' ).val( subtotal.toFixed( 2 ) );
		$( '#ci_tax_amount' ).val( tax.toFixed( 2 ) );
		$( '#ci_total' ).val( total.toFixed( 2 ) );

		serializeItems();
	}

	function serializeItems() {
		var items = [];
		$( '#ci-items-body .ci-item-row' ).each( function () {
			var qty  = parseFloat( $( this ).find( '.ci-qty' ).val() )  || 0;
			var rate = parseFloat( $( this ).find( '.ci-rate' ).val() ) || 0;
			items.push( {
				description: $( this ).find( '.ci-desc' ).val(),
				detail:      $( this ).find( '.ci-detail' ).val(),
				quantity:    qty,
				rate:        rate,
				amount:      parseFloat( ( qty * rate ).toFixed( 2 ) ),
			} );
		} );
		$( '#ci_line_items' ).val( JSON.stringify( items ) );
	}

	// Serialize on form submit
	$( 'form#post' ).on( 'submit', serializeItems );

	// -------------------------------------------------------
	// Send invoice (list + edit screens)
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-send-btn', function () {
		var $btn    = $( this );
		var post_id = $btn.data( 'id' ) || ci.post_id;
		var $status = $( '#ci-action-status' );

		if ( ! post_id ) return;

		$btn.prop( 'disabled', true ).text( 'Sending…' );
		if ( $status.length ) $status.html( '<span class="ci-info">Sending invoice…</span>' );

		$.post( ci.ajax_url, {
			action:  'ci_send_invoice',
			nonce:   ci.nonce,
			post_id: post_id,
		}, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Send Invoice' );
			if ( res.success ) {
				if ( $status.length ) $status.html( '<span class="ci-ok">&#10003; ' + res.data + '</span>' );
				else alert( res.data );
			} else {
				if ( $status.length ) $status.html( '<span class="ci-error">&#10007; ' + res.data + '</span>' );
				else alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Send Invoice' );
			if ( $status.length ) $status.html( '<span class="ci-error">Request failed. Try again.</span>' );
		} );
	} );

	// -------------------------------------------------------
	// Send test email
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-test-email-btn', function () {
		var $btn    = $( this );
		var post_id = $btn.data( 'id' ) || ci.post_id;
		var $status = $( '#ci-action-status' );
		console.log( 'Clean Invoices: test email clicked, post_id=' + post_id );

		$btn.prop( 'disabled', true ).text( 'Sending…' );
		$status.html( '<span class="ci-info">Sending test email…</span>' );

		$.post( ci.ajax_url, {
			action:  'ci_send_test_email',
			nonce:   ci.nonce,
			post_id: post_id,
		}, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Send Test Email to Me' );
			if ( res.success ) {
				$status.html( '<span class="ci-ok">&#10003; ' + res.data + '</span>' );
			} else {
				$status.html( '<span class="ci-error">&#10007; ' + res.data + '</span>' );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Send Test Email to Me' );
			$status.html( '<span class="ci-error">Request failed. Try again.</span>' );
		} );
	} );

	// -------------------------------------------------------
	// Record Payment modal
	// -------------------------------------------------------
	var $modal = $( [
		'<div class="ci-modal-overlay" id="ci-paid-modal">',
		'  <div class="ci-modal">',
		'    <h3>Record Payment</h3>',
		'    <label>Amount</label>',
		'    <input type="number" id="ci-modal-amount" min="0.01" step="0.01" placeholder="0.00">',
		'    <label>Payment Type</label>',
		'    <select id="ci-modal-type">',
		'      <option value="payment">Payment</option>',
		'      <option value="deposit">Deposit</option>',
		'      <option value="installment">Installment</option>',
		'    </select>',
		'    <label>Date</label>',
		'    <input type="date" id="ci-modal-date">',
		'    <label>Method</label>',
		'    <select id="ci-modal-method">',
		'      <option value="">— Select —</option>',
		'      <option>Venmo</option><option>Zelle</option><option>Check</option>',
		'      <option>Credit Card</option><option>Bank Transfer</option><option>Cash</option><option>Other</option>',
		'    </select>',
		'    <label>Notes <span style="font-weight:normal;color:#777;">(optional)</span></label>',
		'    <input type="text" id="ci-modal-notes" placeholder="e.g. Initial deposit">',
		'    <div class="ci-modal-footer">',
		'      <button type="button" id="ci-modal-cancel" class="button">Cancel</button>',
		'      <button type="button" id="ci-modal-confirm" class="button button-primary">Record Payment</button>',
		'    </div>',
		'  </div>',
		'</div>',
	].join( '' ) );
	$( 'body' ).append( $modal );

	var today = new Date().toISOString().split( 'T' )[0];
	$( '#ci-modal-date' ).val( today );

	var activePaidId = 0;

	$( document ).on( 'click', '.ci-paid-btn', function () {
		var $btn  = $( this );
		activePaidId = $btn.data( 'id' ) || ci.post_id;

		// Pre-fill amount with balance due
		var balance = $btn.data( 'balance' );
		if ( ! balance && $( '#ci-invoice-balance' ).length ) {
			balance = $( '#ci-invoice-balance' ).val();
		}
		$( '#ci-modal-amount' ).val( balance ? parseFloat( balance ).toFixed( 2 ) : '' );
		$( '#ci-modal-notes' ).val( '' );

		$( '#ci-paid-modal' ).addClass( 'active' );
	} );

	$( '#ci-modal-cancel' ).on( 'click', function () {
		$( '#ci-paid-modal' ).removeClass( 'active' );
	} );

	$( '#ci-modal-confirm' ).on( 'click', function () {
		var $btn = $( this );
		$btn.prop( 'disabled', true ).text( 'Saving…' );

		$.post( ci.ajax_url, {
			action:         'ci_add_payment',
			nonce:          ci.nonce,
			post_id:        activePaidId,
			amount:         $( '#ci-modal-amount' ).val(),
			payment_type:   $( '#ci-modal-type' ).val(),
			paid_date:      $( '#ci-modal-date' ).val(),
			payment_method: $( '#ci-modal-method' ).val(),
			notes:          $( '#ci-modal-notes' ).val(),
		}, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Record Payment' );
			$( '#ci-paid-modal' ).removeClass( 'active' );

			if ( res.success ) {
				// Edit screen: reload to refresh payment history table
				if ( $( '#ci-invoice-balance' ).length ) {
					location.reload();
					return;
				}
				// List view: update badge and button state
				var status      = res.data.new_status;
				var statusLabels = { paid: 'Paid', partial: 'Partial', sent: 'Sent', overdue: 'Overdue', draft: 'Draft' };
				var $row = $( '.ci-paid-btn[data-id="' + activePaidId + '"]' ).closest( 'tr' );
				$row.find( '.ci-badge' ).attr( 'class', 'ci-badge ci-badge--' + status ).text( statusLabels[ status ] || status );
				if ( status === 'paid' ) {
					$row.find( '.ci-paid-btn' ).remove();
				} else {
					$row.find( '.ci-paid-btn' ).data( 'balance', res.data.balance_due );
				}
			} else {
				alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Record Payment' );
		} );
	} );

	// -------------------------------------------------------
	// Send receipt
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-receipt-btn', function () {
		var $btn    = $( this );
		var post_id = $btn.data( 'id' ) || ci.post_id;
		var $status = $( '#ci-action-status' );

		$btn.prop( 'disabled', true ).text( 'Sending…' );
		if ( $status.length ) $status.html( '<span class="ci-info">Sending receipt…</span>' );

		$.post( ci.ajax_url, {
			action:  'ci_send_receipt',
			nonce:   ci.nonce,
			post_id: post_id,
		}, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Send Receipt to Client' );
			if ( res.success ) {
				if ( $status.length ) $status.html( '<span class="ci-ok">&#10003; ' + res.data + '</span>' );
				else alert( res.data );
			} else {
				if ( $status.length ) $status.html( '<span class="ci-error">&#10007; ' + res.data + '</span>' );
				else alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Send Receipt to Client' );
			if ( $status.length ) $status.html( '<span class="ci-error">Request failed. Try again.</span>' );
		} );
	} );

	// -------------------------------------------------------
	// Remove payment (edit screen)
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-remove-payment', function () {
		if ( ! confirm( 'Remove this payment record?' ) ) return;
		var $btn       = $( this );
		var post_id    = $btn.data( 'id' );
		var payment_id = $btn.data( 'payment-id' );

		$btn.prop( 'disabled', true ).text( 'Removing…' );

		$.post( ci.ajax_url, {
			action:     'ci_remove_payment',
			nonce:      ci.nonce,
			post_id:    post_id,
			payment_id: payment_id,
		}, function ( res ) {
			if ( res.success ) {
				location.reload();
			} else {
				$btn.prop( 'disabled', false ).text( 'Remove' );
				alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Remove' );
		} );
	} );

} );
