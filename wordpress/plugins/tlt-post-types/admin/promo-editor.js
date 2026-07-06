/**
 * Promotion editor — date tools for the "Event dates" field (promo_event_schedule).
 * Mirrors the show performance-date helpers: paste straight from a spreadsheet
 * ("8/28/2026 <tab> 7:30:00 PM" → "2026-08-28 7:30 PM") and a "Generate run"
 * widget (from/to date + time → one line per day). The field is conditional
 * (only shows when "Also show on the calendar" is on), so we re-scan periodically.
 */
( function ( $ ) {
	'use strict';

	function pad( n ) { return ( n < 10 ? '0' : '' ) + n; }

	function toISODate( s ) {
		s = $.trim( s );
		var m;
		if ( ( m = s.match( /^(\d{4})-(\d{1,2})-(\d{1,2})$/ ) ) ) return m[1] + '-' + pad( +m[2] ) + '-' + pad( +m[3] );
		if ( ( m = s.match( /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/ ) ) ) {
			var yr = +m[3]; if ( yr < 100 ) yr += 2000;
			return yr + '-' + pad( +m[1] ) + '-' + pad( +m[2] );
		}
		return null;
	}

	function normalizeTime( s ) {
		s = $.trim( s );
		var m;
		if ( ( m = s.match( /^(\d{1,2}):(\d{2})(?::\d{2})?\s*([AaPp][Mm])$/ ) ) ) return m[1] + ':' + m[2] + ' ' + m[3].toUpperCase();
		if ( ( m = s.match( /^(\d{1,2}):(\d{2})(?::\d{2})?$/ ) ) ) {
			var h = +m[1], ap = h >= 12 ? 'PM' : 'AM', h12 = h % 12 || 12;
			return h12 + ':' + m[2] + ' ' + ap;
		}
		return s;
	}

	function normalizeLine( line ) {
		line = $.trim( ( line || '' ).replace( /\t/g, ' ' ) );
		var dm = line.match( /^(\d{1,4}[\/\-]\d{1,2}[\/\-]\d{1,4})\s+(.*)$/ );
		if ( ! dm ) return null;
		var iso = toISODate( dm[1] );
		if ( ! iso ) return null;
		var rest = $.trim( dm[2] ), time = '', loc = '';
		var tm = rest.match( /^(\d{1,2}:\d{2}(?::\d{2})?\s*(?:[AaPp][Mm])?)\s*(.*)$/ );
		if ( tm ) { time = normalizeTime( tm[1] ); loc = $.trim( tm[2] ).replace( /^@\s*/, '' ); }
		return iso + ( time ? ' ' + time : '' ) + ( loc ? ' @ ' + loc : '' );
	}

	function normalizeScheduleText( text ) {
		var out = [], any = false;
		( text || '' ).split( /\r\n|\r|\n/ ).forEach( function ( raw ) {
			if ( ! $.trim( raw ) ) return;
			var n = normalizeLine( raw );
			if ( n ) { out.push( n ); any = true; } else { out.push( $.trim( raw ) ); }
		} );
		return any ? out.join( '\n' ) : null;
	}

	function initField( $ta ) {
		if ( $ta.data( 'tltDates' ) ) return;
		$ta.data( 'tltDates', 1 );

		// Spreadsheet paste → normalized lines.
		$ta.on( 'paste', function ( e ) {
			var clip = ( e.originalEvent || e ).clipboardData || window.clipboardData;
			if ( ! clip ) return;
			var normalized = normalizeScheduleText( clip.getData( 'text' ) );
			if ( normalized === null ) return;
			e.preventDefault();
			var el = $ta[0], a = el.selectionStart, b = el.selectionEnd, v = el.value;
			el.value = v.slice( 0, a ) + normalized + v.slice( b );
			el.selectionStart = el.selectionEnd = a + normalized.length;
			$ta.trigger( 'input' );
		} );

		// Generate-run widget.
		var $tools = $( '<div class="tlt-promo-dates" style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap;align-items:center;font-size:13px"></div>' );
		var $start = $( '<input type="date">' );
		var $end   = $( '<input type="date">' );
		var $time  = $( '<input type="text" placeholder="7:30 PM" style="width:90px">' );
		var $btn   = $( '<button type="button" class="button button-secondary">Generate run &rarr;</button>' );
		$tools.append( 'From ', $start, ' to ', $end, ' at ', $time, ' ', $btn );
		$ta.after( $tools );

		$btn.on( 'click', function () {
			var s = $start.val(), e = $end.val();
			if ( ! s || ! e ) { $start.focus(); return; }
			var t = normalizeTime( $time.val() || '7:30 PM' );
			var d = new Date( s + 'T00:00:00' ), end = new Date( e + 'T00:00:00' ), lines = [], guard = 0;
			while ( d <= end && guard++ < 400 ) {
				lines.push( d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ) + ( t ? ' ' + t : '' ) );
				d.setDate( d.getDate() + 1 );
			}
			var cur = $ta.val().replace( /\s+$/, '' );
			$ta.val( ( cur ? cur + '\n' : '' ) + lines.join( '\n' ) ).trigger( 'input' );
		} );
	}

	function scan() {
		$( '.acf-field[data-name="promo_event_schedule"] textarea' ).each( function () { initField( $( this ) ); } );
	}

	$( document ).ready( function () { scan(); setInterval( scan, 1200 ); } );

} )( jQuery );
