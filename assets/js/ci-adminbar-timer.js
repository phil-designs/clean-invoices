jQuery( function ( $ ) {
	if ( typeof ciAdminbar === 'undefined' ) return;

	var STORAGE_KEY = 'ci_timer_state';
	var $node = $( '#wp-admin-bar-ci-timer' );
	if ( ! $node.length ) return;

	var tickInterval = null;

	function getState() {
		try { return JSON.parse( localStorage.getItem( STORAGE_KEY ) ) || null; }
		catch ( e ) { return null; }
	}

	function pad( n ) { return n < 10 ? '0' + n : '' + n; }

	function formatSeconds( s ) {
		var h   = Math.floor( s / 3600 );
		var m   = Math.floor( ( s % 3600 ) / 60 );
		var sec = s % 60;
		return pad( h ) + ':' + pad( m ) + ':' + pad( sec );
	}

	function render() {
		var state    = getState();
		var $elapsed = $( '#ci-ab-elapsed' );
		var $desc    = $( '#ci-ab-desc' );

		if ( state && state.active ) {
			var elapsed = Math.floor( ( Date.now() - state.start ) / 1000 );
			$elapsed.text( formatSeconds( elapsed ) );
			$desc.text( state.description || '(no description)' );
			$node.addClass( 'ci-ab-running' );
		} else {
			$elapsed.text( 'Time Tracker' );
			$desc.text( 'No timer running' );
			$node.removeClass( 'ci-ab-running' );
		}
	}

	function startTick() {
		clearInterval( tickInterval );
		render();
		tickInterval = setInterval( render, 1000 );
	}

	startTick();

	// Pick up changes made in another tab (e.g. tracker page started/stopped)
	$( window ).on( 'storage', function ( e ) {
		if ( e.originalEvent.key === STORAGE_KEY ) startTick();
	} );

	// Stop & Save from admin bar dropdown
	$( '#wp-admin-bar-ci-timer-stop a' ).on( 'click', function ( e ) {
		e.preventDefault();
		var $link = $( this );
		var state = getState();
		if ( ! state || ! state.active ) return;

		$link.text( 'Saving…' );

		$.post( ciAdminbar.ajax_url, {
			action:      'ci_tt_save_entry',
			nonce:       ciAdminbar.nonce,
			description: state.description || '',
			rate:        parseFloat( state.rate ) || 0,
			start:       Math.floor( state.start / 1000 ),
			end:         Math.floor( Date.now() / 1000 ),
		}, function ( res ) {
			if ( res.success ) {
				localStorage.removeItem( STORAGE_KEY );
				$link.text( '✓ Saved!' );
				// Reload the tracker page so the new entry appears in the log
				if ( window.location.href.indexOf( 'clean-invoices-time-tracker' ) !== -1 ) {
					setTimeout( function () { location.reload(); }, 600 );
				} else {
					setTimeout( function () { $link.html( '&#9632; Stop &amp; Save' ); }, 2000 );
				}
			} else {
				$link.text( 'Error — try again' );
				setTimeout( function () { $link.html( '&#9632; Stop &amp; Save' ); }, 2500 );
			}
		} ).fail( function () {
			$link.text( 'Failed — try again' );
			setTimeout( function () { $link.html( '&#9632; Stop &amp; Save' ); }, 2500 );
		} );
	} );
} );
