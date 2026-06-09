jQuery( function ( $ ) {
	if ( typeof ci === 'undefined' ) return;

	var STORAGE_KEY = 'ci_timer_state';

	// -------------------------------------------------------
	// localStorage helpers
	// -------------------------------------------------------
	function getState() {
		try { return JSON.parse( localStorage.getItem( STORAGE_KEY ) ) || null; }
		catch ( e ) { return null; }
	}

	function setState( state ) {
		localStorage.setItem( STORAGE_KEY, JSON.stringify( state ) );
	}

	function clearState() {
		localStorage.removeItem( STORAGE_KEY );
	}

	// -------------------------------------------------------
	// Clock
	// -------------------------------------------------------
	var tickInterval = null;

	function pad( n ) { return n < 10 ? '0' + n : String( n ); }

	function formatSeconds( s ) {
		var h   = Math.floor( s / 3600 );
		var m   = Math.floor( ( s % 3600 ) / 60 );
		var sec = s % 60;
		return pad( h ) + ':' + pad( m ) + ':' + pad( sec );
	}

	function startClock( startMs ) {
		clearInterval( tickInterval );
		tick( startMs );
		tickInterval = setInterval( function () { tick( startMs ); }, 1000 );
	}

	function tick( startMs ) {
		$( '#ci-tt-clock' ).text( formatSeconds( Math.floor( ( Date.now() - startMs ) / 1000 ) ) );
	}

	function stopClock() {
		clearInterval( tickInterval );
		tickInterval = null;
		$( '#ci-tt-clock' ).text( '00:00:00' );
	}

	// -------------------------------------------------------
	// Restore state on page load
	// -------------------------------------------------------
	var state = getState();
	if ( state && state.active ) {
		$( '#ci-tt-description' ).val( state.description || '' );
		$( '#ci-tt-rate' ).val( state.rate || '' );
		$( '#ci-tt-start' ).hide();
		$( '#ci-tt-stop' ).show();
		$( '#ci-tt-clock' ).addClass( 'ci-tt-clock--running' );
		startClock( state.start );
	} else {
		var savedRate = localStorage.getItem( 'ci_default_rate' );
		if ( savedRate ) $( '#ci-tt-rate' ).val( savedRate );
	}

	// -------------------------------------------------------
	// Start
	// -------------------------------------------------------
	$( '#ci-tt-start' ).on( 'click', function () {
		var startMs = Date.now();
		var desc    = $.trim( $( '#ci-tt-description' ).val() );
		var rate    = $( '#ci-tt-rate' ).val();

		setState( { active: true, start: startMs, description: desc, rate: rate } );
		localStorage.setItem( 'ci_default_rate', rate );

		$( '#ci-tt-start' ).hide();
		$( '#ci-tt-stop' ).show();
		$( '#ci-tt-clock' ).addClass( 'ci-tt-clock--running' );
		$( '#ci-tt-status' ).html( '' );
		startClock( startMs );
	} );

	// -------------------------------------------------------
	// Stop & Save
	// -------------------------------------------------------
	$( '#ci-tt-stop' ).on( 'click', function () {
		var $btn    = $( this );
		var current = getState();
		if ( ! current || ! current.active ) return;

		var endMs = Date.now();
		var desc  = $.trim( $( '#ci-tt-description' ).val() ) || current.description || '';
		var rate  = parseFloat( $( '#ci-tt-rate' ).val() ) || parseFloat( current.rate ) || 0;

		$btn.prop( 'disabled', true ).html( 'Saving&hellip;' );

		$.post( ci.ajax_url, {
			action:      'ci_tt_save_entry',
			nonce:       ci.nonce,
			description: desc,
			rate:        rate,
			start:       Math.floor( current.start / 1000 ),
			end:         Math.floor( endMs / 1000 ),
		}, function ( res ) {
			$btn.prop( 'disabled', false ).html( '&#9632; Stop &amp; Save' );
			if ( res.success ) {
				clearState();
				stopClock();
				$( '#ci-tt-stop' ).hide();
				$( '#ci-tt-start' ).show();
				$( '#ci-tt-clock' ).removeClass( 'ci-tt-clock--running' );
				$( '#ci-tt-description' ).val( '' );
				$( '#ci-tt-status' ).html( '<span class="ci-ok">&#10003; Entry saved — ' + formatHours( res.data.hours ) + ' @ $' + parseFloat( res.data.rate ).toFixed( 2 ) + '/hr</span>' );
				prependEntry( res.data );
			} else {
				$( '#ci-tt-status' ).html( '<span class="ci-error">&#10007; ' + escHtml( res.data ) + '</span>' );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).html( '&#9632; Stop &amp; Save' );
			$( '#ci-tt-status' ).html( '<span class="ci-error">Request failed. Try again.</span>' );
		} );
	} );

	// -------------------------------------------------------
	// Helpers
	// -------------------------------------------------------
	function formatHours( h ) {
		var totalSec = Math.round( parseFloat( h ) * 3600 );
		var hh = Math.floor( totalSec / 3600 );
		var mm = Math.floor( ( totalSec % 3600 ) / 60 );
		if ( hh > 0 ) return hh + 'h ' + ( mm > 0 ? mm + 'm' : '' );
		return mm + 'm';
	}

	function escHtml( str ) {
		return String( str )
			.replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	// -------------------------------------------------------
	// Prepend saved entry row to log table
	// -------------------------------------------------------
	function prependEntry( entry ) {
		var d       = new Date( entry.end * 1000 );
		var months  = [ 'Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec' ];
		var dateStr = months[ d.getMonth() ] + ' ' + d.getDate() + ', ' + d.getFullYear();

		var invoiceCell =
			'<button type="button" class="button button-small ci-tt-add-invoice-btn" data-id="' + escHtml( entry.id ) + '">Add to Invoice</button>' +
			'<span class="ci-tt-invoice-select" style="display:none;">' +
				'<select class="ci-tt-invoice-dropdown"></select>' +
				'<span class="ci-tt-invoice-btns">' +
					'<button type="button" class="button button-small button-primary ci-tt-confirm-invoice">Add</button>' +
					'<button type="button" class="button button-small ci-tt-cancel-invoice">Cancel</button>' +
				'</span>' +
			'</span>';

		var $row = $( '<tr>' ).attr( 'data-id', entry.id ).append(
			$( '<td>' ).css( 'text-align', 'center' ).html(
				'<input type="checkbox" class="ci-tt-check" data-id="' + escHtml( entry.id ) + '">'
			),
			$( '<td>' ).text( dateStr ),
			$( '<td>' ).addClass( 'ci-tt-cell-desc' ).text( entry.description ),
			$( '<td>' ).addClass( 'ci-tt-cell-hours' ).css( 'text-align', 'right' ).text( parseFloat( entry.hours ).toFixed( 2 ) ),
			$( '<td>' ).addClass( 'ci-tt-cell-rate' ).css( 'text-align', 'right' ).text( '$' + parseFloat( entry.rate ).toFixed( 2 ) ),
			$( '<td>' ).addClass( 'ci-tt-cell-amount' ).css( 'text-align', 'right' ).text( '$' + parseFloat( entry.amount ).toFixed( 2 ) ),
			$( '<td>' ).addClass( 'ci-tt-invoice-cell' ).html( invoiceCell ),
			$( '<td>' ).addClass( 'ci-tt-action-cell' ).html(
				'<button type="button" class="button button-small ci-tt-edit-btn" data-id="' + escHtml( entry.id ) + '">Edit</button> ' +
				'<button type="button" class="button-link-delete ci-tt-delete-btn" data-id="' + escHtml( entry.id ) + '">Delete</button>'
			)
		);

		if ( $( '#ci-tt-log-body' ).length ) {
			$( '#ci-tt-log-body' ).prepend( $row );
		} else {
			location.reload();
		}
	}

	// -------------------------------------------------------
	// Checkbox selection & merge button
	// -------------------------------------------------------
	function updateMergeBar() {
		var count = $( '.ci-tt-check:checked' ).length;
		if ( count >= 2 ) {
			$( '#ci-tt-bulk-bar' ).show();
			$( '#ci-tt-merge-btn' ).text( 'Merge ' + count + ' Selected' ).prop( 'disabled', false );
		} else {
			$( '#ci-tt-bulk-bar' ).hide();
		}
	}

	$( document ).on( 'change', '.ci-tt-check', updateMergeBar );

	$( document ).on( 'change', '#ci-tt-select-all', function () {
		$( '.ci-tt-check' ).prop( 'checked', $( this ).is( ':checked' ) );
		updateMergeBar();
	} );

	// Uncheck select-all when any individual box is unchecked
	$( document ).on( 'change', '.ci-tt-check', function () {
		if ( ! $( this ).is( ':checked' ) ) $( '#ci-tt-select-all' ).prop( 'checked', false );
		updateMergeBar();
	} );

	// -------------------------------------------------------
	// Merge modal
	// -------------------------------------------------------
	var $mergeModal = $( [
		'<div class="ci-modal-overlay" id="ci-merge-modal">',
		'  <div class="ci-modal">',
		'    <h3>Merge Time Entries</h3>',
		'    <p id="ci-merge-summary" style="color:#646970;font-size:13px;margin:0 0 14px;"></p>',
		'    <label>Combined Description</label>',
		'    <input type="text" id="ci-merge-desc" class="regular-text" style="width:100%;margin-bottom:12px;">',
		'    <div style="display:flex;gap:12px;margin-bottom:4px;">',
		'      <div style="flex:1;"><label>Total Hours</label>',
		'        <input type="number" id="ci-merge-hours" min="0.01" step="0.01" style="width:100%;">',
		'      </div>',
		'      <div style="flex:1;"><label>Rate ($/hr)</label>',
		'        <input type="number" id="ci-merge-rate" min="0" step="0.01" style="width:100%;">',
		'      </div>',
		'      <div style="flex:1;"><label>Total Amount</label>',
		'        <input type="number" id="ci-merge-amount" readonly tabindex="-1" style="width:100%;background:#f6f7f7;">',
		'      </div>',
		'    </div>',
		'    <div class="ci-modal-footer">',
		'      <button type="button" id="ci-merge-cancel" class="button">Cancel</button>',
		'      <button type="button" id="ci-merge-confirm" class="button button-primary">Merge Entries</button>',
		'    </div>',
		'  </div>',
		'</div>',
	].join( '' ) );
	$( 'body' ).append( $mergeModal );

	function recalcMergeAmount() {
		var h = parseFloat( $( '#ci-merge-hours' ).val() ) || 0;
		var r = parseFloat( $( '#ci-merge-rate' ).val() )  || 0;
		$( '#ci-merge-amount' ).val( ( h * r ).toFixed( 2 ) );
	}

	$( '#ci-merge-hours, #ci-merge-rate' ).on( 'input', recalcMergeAmount );

	$( document ).on( 'click', '#ci-tt-merge-btn', function () {
		var $checked = $( '.ci-tt-check:checked' );
		if ( $checked.length < 2 ) return;

		var totalHours  = 0;
		var totalAmount = 0;
		var firstDesc   = '';
		var count       = $checked.length;

		$checked.each( function () {
			var $row = $( this ).closest( 'tr' );
			totalHours  += parseFloat( $row.find( '.ci-tt-cell-hours' ).text() )  || 0;
			totalAmount += parseFloat( $row.find( '.ci-tt-cell-amount' ).text().replace( '$', '' ) ) || 0;
			if ( ! firstDesc ) firstDesc = $.trim( $row.find( '.ci-tt-cell-desc' ).text() );
		} );

		var avgRate = totalHours > 0 ? totalAmount / totalHours : 0;

		$( '#ci-merge-summary' ).text( 'Combining ' + count + ' entries — ' + totalHours.toFixed( 2 ) + ' hrs total.' );
		$( '#ci-merge-desc' ).val( firstDesc );
		$( '#ci-merge-hours' ).val( totalHours.toFixed( 2 ) );
		$( '#ci-merge-rate' ).val( avgRate.toFixed( 2 ) );
		$( '#ci-merge-amount' ).val( totalAmount.toFixed( 2 ) );

		$( '#ci-merge-modal' ).addClass( 'active' );
	} );

	$( '#ci-merge-cancel' ).on( 'click', function () {
		$( '#ci-merge-modal' ).removeClass( 'active' );
	} );

	$( '#ci-merge-confirm' ).on( 'click', function () {
		var $btn  = $( this );
		var ids   = [];
		$( '.ci-tt-check:checked' ).each( function () { ids.push( $( this ).data( 'id' ) ); } );

		var desc  = $.trim( $( '#ci-merge-desc' ).val() );
		var hours = parseFloat( $( '#ci-merge-hours' ).val() ) || 0;
		var rate  = parseFloat( $( '#ci-merge-rate' ).val() )  || 0;

		if ( hours <= 0 ) { alert( 'Hours must be greater than zero.' ); return; }

		$btn.prop( 'disabled', true ).text( 'Merging…' );

		$.post( ci.ajax_url, {
			action:      'ci_tt_merge_entries',
			nonce:       ci.nonce,
			entry_ids:   JSON.stringify( ids ),
			description: desc,
			hours:       hours,
			rate:        rate,
		}, function ( res ) {
			$btn.prop( 'disabled', false ).text( 'Merge Entries' );
			$( '#ci-merge-modal' ).removeClass( 'active' );

			if ( res.success ) {
				// Remove merged rows
				$( '.ci-tt-check:checked' ).each( function () {
					$( this ).closest( 'tr' ).remove();
				} );
				$( '#ci-tt-select-all' ).prop( 'checked', false );
				$( '#ci-tt-bulk-bar' ).hide();

				// Add merged entry at top
				prependEntry( res.data );
			} else {
				alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Merge Entries' );
		} );
	} );

	// -------------------------------------------------------
	// Edit entry — inline (uses cell classes, not eq())
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-tt-edit-btn', function () {
		var $btn = $( this );
		var $row = $btn.closest( 'tr' );
		if ( $row.hasClass( 'ci-tt-editing' ) ) return;

		var origDesc   = $.trim( $row.find( '.ci-tt-cell-desc' ).text() );
		var origHours  = $.trim( $row.find( '.ci-tt-cell-hours' ).text() );
		var origRate   = $.trim( $row.find( '.ci-tt-cell-rate' ).text() ).replace( '$', '' ).trim();
		var origAmount = $.trim( $row.find( '.ci-tt-cell-amount' ).text() );

		$row.data( 'orig', { desc: origDesc, hours: origHours, rate: origRate, amount: origAmount } );
		$row.addClass( 'ci-tt-editing' );

		$row.find( '.ci-tt-cell-desc' ).empty().append(
			$( '<input>' ).attr( { type: 'text', class: 'regular-text ci-tt-edit-desc' } ).val( origDesc ).css( 'width', '100%' )
		);
		$row.find( '.ci-tt-cell-hours' ).empty().append(
			$( '<input>' ).attr( { type: 'number', class: 'ci-tt-edit-hours', min: '0.01', step: '0.01' } ).val( origHours ).css( { width: '68px', 'text-align': 'right' } )
		);
		$row.find( '.ci-tt-cell-rate' ).empty().append(
			$( '<input>' ).attr( { type: 'number', class: 'ci-tt-edit-rate', min: '0', step: '0.01' } ).val( origRate ).css( { width: '68px', 'text-align': 'right' } )
		);
		$row.find( '.ci-tt-cell-amount' ).empty().append(
			$( '<span>' ).addClass( 'ci-tt-edit-amount' ).text( origAmount )
		);

		$btn.hide();
		$row.find( '.ci-tt-delete-btn' ).hide();
		$row.find( '.ci-tt-check' ).prop( 'disabled', true );
		$row.find( '.ci-tt-action-cell' ).append(
			$( '<button>' ).attr( { type: 'button', class: 'button button-small button-primary ci-tt-save-edit' } ).text( 'Save' ),
			' ',
			$( '<button>' ).attr( { type: 'button', class: 'button button-small ci-tt-cancel-edit' } ).text( 'Cancel' )
		);
	} );

	$( document ).on( 'input', '.ci-tt-edit-hours, .ci-tt-edit-rate', function () {
		var $row  = $( this ).closest( 'tr' );
		var hours = parseFloat( $row.find( '.ci-tt-edit-hours' ).val() ) || 0;
		var rate  = parseFloat( $row.find( '.ci-tt-edit-rate' ).val() )  || 0;
		$row.find( '.ci-tt-edit-amount' ).text( '$' + ( hours * rate ).toFixed( 2 ) );
	} );

	$( document ).on( 'click', '.ci-tt-cancel-edit', function () {
		var $row = $( this ).closest( 'tr' );
		var orig = $row.data( 'orig' );

		$row.find( '.ci-tt-cell-desc' ).text( orig.desc );
		$row.find( '.ci-tt-cell-hours' ).text( orig.hours );
		$row.find( '.ci-tt-cell-rate' ).text( orig.rate.indexOf( '$' ) === -1 ? '$' + parseFloat( orig.rate ).toFixed( 2 ) : orig.rate );
		$row.find( '.ci-tt-cell-amount' ).text( orig.amount );

		$row.find( '.ci-tt-save-edit, .ci-tt-cancel-edit' ).remove();
		$row.find( '.ci-tt-edit-btn' ).show();
		$row.find( '.ci-tt-delete-btn' ).show();
		$row.find( '.ci-tt-check' ).prop( 'disabled', false );
		$row.removeClass( 'ci-tt-editing' ).removeData( 'orig' );
	} );

	$( document ).on( 'click', '.ci-tt-save-edit', function () {
		var $btn  = $( this );
		var $row  = $btn.closest( 'tr' );
		var id    = $row.data( 'id' );
		var desc  = $row.find( '.ci-tt-edit-desc' ).val();
		var hours = parseFloat( $row.find( '.ci-tt-edit-hours' ).val() ) || 0;
		var rate  = parseFloat( $row.find( '.ci-tt-edit-rate' ).val() )  || 0;

		if ( hours <= 0 ) { alert( 'Hours must be greater than zero.' ); return; }
		$btn.prop( 'disabled', true ).text( 'Saving…' );

		$.post( ci.ajax_url, {
			action:      'ci_tt_update_entry',
			nonce:       ci.nonce,
			entry_id:    id,
			description: desc,
			hours:       hours,
			rate:        rate,
		}, function ( res ) {
			if ( res.success ) {
				var d = res.data;
				$row.find( '.ci-tt-cell-desc' ).text( d.description );
				$row.find( '.ci-tt-cell-hours' ).text( parseFloat( d.hours ).toFixed( 2 ) );
				$row.find( '.ci-tt-cell-rate' ).text( '$' + parseFloat( d.rate ).toFixed( 2 ) );
				$row.find( '.ci-tt-cell-amount' ).text( '$' + parseFloat( d.amount ).toFixed( 2 ) );

				$row.find( '.ci-tt-save-edit, .ci-tt-cancel-edit' ).remove();
				$row.find( '.ci-tt-edit-btn' ).show();
				$row.find( '.ci-tt-delete-btn' ).show();
				$row.find( '.ci-tt-check' ).prop( 'disabled', false );
				$row.removeClass( 'ci-tt-editing' ).removeData( 'orig' );
			} else {
				$btn.prop( 'disabled', false ).text( 'Save' );
				alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Save' );
		} );
	} );

	// -------------------------------------------------------
	// Delete entry
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-tt-delete-btn', function () {
		if ( ! confirm( 'Delete this time entry?' ) ) return;
		var $btn = $( this );
		var id   = $btn.data( 'id' );

		$btn.prop( 'disabled', true ).text( 'Deleting…' );

		$.post( ci.ajax_url, {
			action:   'ci_tt_delete_entry',
			nonce:    ci.nonce,
			entry_id: id,
		}, function ( res ) {
			if ( res.success ) {
				$btn.closest( 'tr' ).fadeOut( 200, function () {
					$( this ).remove();
					updateMergeBar();
				} );
			} else {
				$btn.prop( 'disabled', false ).text( 'Delete' );
				alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Delete' );
		} );
	} );

	// -------------------------------------------------------
	// Add to invoice — show dropdown
	// -------------------------------------------------------
	$( document ).on( 'click', '.ci-tt-add-invoice-btn', function () {
		var $btn  = $( this );
		var $cell = $btn.closest( 'td' );
		var $sel  = $cell.find( '.ci-tt-invoice-select' );

		$btn.prop( 'disabled', true ).text( 'Loading…' );

		$.post( ci.ajax_url, {
			action: 'ci_tt_get_invoices',
			nonce:  ci.nonce,
		}, function ( res ) {
			if ( ! res.success || ! res.data.length ) {
				$btn.prop( 'disabled', false ).text( 'Add to Invoice' );
				alert( 'No invoices found. Create an invoice first.' );
				return;
			}
			var $dropdown = $sel.find( '.ci-tt-invoice-dropdown' ).empty();
			$.each( res.data, function ( _, inv ) {
				$dropdown.append( $( '<option>' ).val( inv.id ).text( inv.label ) );
			} );
			$btn.hide();
			$sel.css( 'display', 'flex' );
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Add to Invoice' );
		} );
	} );

	$( document ).on( 'click', '.ci-tt-cancel-invoice', function () {
		var $cell = $( this ).closest( 'td' );
		$cell.find( '.ci-tt-invoice-select' ).hide();
		$cell.find( '.ci-tt-add-invoice-btn' ).show().prop( 'disabled', false ).text( 'Add to Invoice' );
	} );

	$( document ).on( 'click', '.ci-tt-confirm-invoice', function () {
		var $btn       = $( this );
		var $row       = $btn.closest( 'tr' );
		var entry_id   = $row.data( 'id' );
		var invoice_id = $btn.closest( '.ci-tt-invoice-select' ).find( '.ci-tt-invoice-dropdown' ).val();

		$btn.prop( 'disabled', true ).text( 'Adding…' );

		$.post( ci.ajax_url, {
			action:     'ci_tt_add_to_invoice',
			nonce:      ci.nonce,
			entry_id:   entry_id,
			invoice_id: invoice_id,
		}, function ( res ) {
			if ( res.success ) {
				$row.find( '.ci-tt-invoice-cell' ).html(
					'<a href="' + res.data.edit_url + '">' + escHtml( res.data.inv_num ) + '</a>'
				);
				// Invoiced rows can't be merged — remove checkbox
				$row.find( '.ci-tt-check' ).remove();
				updateMergeBar();
			} else {
				$btn.prop( 'disabled', false ).text( 'Add' );
				alert( 'Error: ' + res.data );
			}
		} ).fail( function () {
			$btn.prop( 'disabled', false ).text( 'Add' );
		} );
	} );

} );
