/**
 * TLT Show editor enhancements (Classic editor screen):
 *   - #show_photo_gallery / #show_splash_gallery → visual media managers
 *     (click to add from the media library, drag to reorder, × to remove).
 *     The underlying textarea still holds the JSON so saving is unchanged.
 *   - #show_performances / #show_audition_schedule → a date picker + "Add"
 *     button, and (performances) a "Generate a run" tool.
 */
( function ( $ ) {
	$( function () {

		/* ---------- shared: open the WP media library ---------- */
		function pickImages( cb ) {
			if ( typeof wp === 'undefined' || ! wp.media ) {
				alert( 'Media library unavailable — reload the page.' );
				return;
			}
			var frame = wp.media( {
				title: 'Select photos',
				button: { text: 'Use these photos' },
				library: { type: 'image' },
				multiple: 'add'
			} );
			frame.on( 'select', function () {
				cb( frame.state().get( 'selection' ).toJSON() );
			} );
			frame.open();
		}

		function bestUrl( att ) {
			if ( att.sizes && att.sizes.large ) return att.sizes.large.url;
			return att.url;
		}

		/* ---------- focal-point picker (for the cover crop) ---------- */
		function openFocal( item, onSave ) {
			var m = ( item.focal || '50% 30%' ).match( /(-?\d+)%\s+(-?\d+)%/ );
			var fx = m ? +m[1] : 50, fy = m ? +m[2] : 30;

			var $ov   = $( '<div class="tlt-focal-ov"></div>' );
			var $img  = $( '<img>' ).attr( 'src', item.url );
			var $dot  = $( '<div class="tlt-focal-dot"></div>' );
			var $area = $( '<div class="tlt-focal-img"></div>' ).append( $img ).append( $dot );

			// Live phone preview — the portrait crop is exactly what the cover shows.
			var $phone = $( '<div class="tlt-focal-phone"></div>' )
				.css( 'background-image', "url('" + item.url + "')" )
				.append( '<div class="tlt-focal-phone__shade"></div>' );
			var $preview = $( '<div class="tlt-focal-preview"></div>' )
				.append( $phone ).append( '<span>Phone preview (cover)</span>' );

			var $stage = $( '<div class="tlt-focal-stage"></div>' ).append( $area ).append( $preview );

			var $save = $( '<button type="button" class="button button-primary">Save focal point</button>' );
			var $cncl = $( '<button type="button" class="button">Cancel</button>' );
			var $panel = $( '<div class="tlt-focal-panel"></div>' )
				.append( '<h2 style="margin-top:0">Set focal point</h2>' )
				.append( '<p class="tlt-focal-hint">Click the subject (a face, the action). The phone preview shows exactly what the full-screen cover will display.</p>' )
				.append( $stage )
				.append( $( '<p style="margin:14px 0 0"></p>' ).append( $save ).append( ' ' ).append( $cncl ) );
			$ov.append( $panel ).appendTo( 'body' );

			function place() {
				$dot.css( { left: fx + '%', top: fy + '%' } );
				$phone.css( 'background-position', fx + '% ' + fy + '%' );
			}
			place();

			$area.on( 'click', function ( e ) {
				var r = $img[ 0 ].getBoundingClientRect();
				fx = Math.max( 0, Math.min( 100, Math.round( ( ( e.clientX - r.left ) / r.width ) * 100 ) ) );
				fy = Math.max( 0, Math.min( 100, Math.round( ( ( e.clientY - r.top ) / r.height ) * 100 ) ) );
				place();
			} );
			$save.on( 'click', function () { onSave( fx + '% ' + fy + '%' ); $ov.remove(); } );
			$cncl.on( 'click', function () { $ov.remove(); } );
			$ov.on( 'click', function ( e ) { if ( e.target === $ov[ 0 ] ) $ov.remove(); } );
		}

		/* ---------- gallery manager ----------
		 * mode 'objects' → [{url,alt,caption}] (production gallery)
		 * mode 'urls'    → ["url", …]          (splash gallery)        */
		function initGallery( id, mode, label, noFocal ) {
			var $ta = $( '#' + id );
			if ( ! $ta.length ) return;

			var items = [];
			try {
				var raw = JSON.parse( $.trim( $ta.val() ) || '[]' );
				if ( Array.isArray( raw ) ) {
					items = raw.map( function ( x ) {
						return ( typeof x === 'string' ) ? { url: x } : ( x || {} );
					} ).filter( function ( x ) { return x.url; } );
				}
			} catch ( e ) { /* malformed JSON — leave the raw box for manual repair */ }

			var $wrap = $( '<div class="tlt-gallery"></div>' );
			var $grid = $( '<div class="tlt-gallery__grid"></div>' );
			var $bar  = $( '<p class="tlt-gallery__bar"></p>' );
			var $add  = $( '<button type="button" class="button button-primary">+ Add / Upload Photos</button>' );
			var $count = $( '<span class="tlt-gallery__count"></span>' );
			var $raw  = $( '<a href="#" class="tlt-gallery__raw">Edit raw</a>' );
			$bar.append( $add ).append( ' ' ).append( $count ).append( ' &middot; ' ).append( $raw );
			$wrap.append( $grid ).append( $bar );
			$ta.hide().before( $wrap );

			function sync() {
				var out = items.map( function ( it ) {
					if ( mode === 'urls' ) return it.url;
					var o = { url: it.url, alt: it.alt || '', caption: it.caption || '' };
					if ( it.focal ) o.focal = it.focal;
					return o;
				} );
				$ta.val( out.length ? JSON.stringify( out ) : '' );
				$count.text( items.length + ( items.length === 1 ? ' photo' : ' photos' ) );
			}

			function render() {
				$grid.empty();
				items.forEach( function ( it, i ) {
					var $cell = $( '<div class="tlt-thumb"></div>' ).attr( 'data-i', i );
					var $img = $( '<img>' ).attr( 'src', it.url );
					if ( mode === 'objects' ) $img.css( 'object-position', it.focal || '50% 30%' );
					$cell.append( $img )
						.append( '<button type="button" class="tlt-thumb__rm" title="Remove">&times;</button>' );
					if ( mode === 'objects' && ! noFocal ) {
						$cell.append( '<button type="button" class="tlt-thumb__focal' + ( it.focal ? ' is-set' : '' ) + '" title="Set focal point (for the cover crop)">&#9678;</button>' );
					}
					$cell.appendTo( $grid );
				} );
				if ( ! items.length ) {
					$grid.append( '<p class="tlt-gallery__empty">No photos yet. Click &ldquo;Add / Upload Photos&rdquo;.</p>' );
				}
			}

			$add.on( 'click', function () {
				pickImages( function ( sel ) {
					sel.forEach( function ( a ) {
						items.push( { url: bestUrl( a ), alt: a.alt || '', caption: a.caption || '' } );
					} );
					render(); sync();
				} );
			} );

			$grid.on( 'click', '.tlt-thumb__rm', function () {
				items.splice( $( this ).closest( '.tlt-thumb' ).data( 'i' ), 1 );
				render(); sync();
			} );

			$grid.on( 'click', '.tlt-thumb__focal', function ( e ) {
				e.stopPropagation();
				var i = $( this ).closest( '.tlt-thumb' ).data( 'i' );
				openFocal( items[ i ], function ( focal ) {
					items[ i ].focal = focal; sync(); render();
				} );
			} );

			$raw.on( 'click', function ( e ) { e.preventDefault(); $ta.toggle(); } );

			if ( $grid.sortable ) {
				$grid.sortable( {
					items: '> .tlt-thumb',
					tolerance: 'pointer',
					update: function () {
						var reordered = [];
						$grid.find( '.tlt-thumb' ).each( function () {
							reordered.push( items[ $( this ).data( 'i' ) ] );
						} );
						items = reordered; render(); sync();
					}
				} );
			}

			render(); sync();
		}

		initGallery( 'show_photo_gallery', 'objects', 'Photos' );
		initGallery( 'show_dramaturgy_gallery', 'objects', 'Dramaturgy images', true );

		/* ---------- poster (single image → real Featured Image) ---------- */
		( function initPoster() {
			var $h = $( '#tlt_poster_id' );
			if ( ! $h.length ) return;
			var $box = $h.closest( '.tlt-poster' );
			var $preview = $( '<div class="tlt-poster__preview"></div>' );
			var $set = $( '<button type="button" class="button button-primary">Set / Replace Poster</button>' );
			var $rm  = $( '<button type="button" class="button tlt-poster__rm">Remove</button>' );
			$box.append( $preview ).append( $( '<p class="tlt-poster__bar"></p>' ).append( $set ).append( ' ' ).append( $rm ) );

			function render( url ) {
				if ( url ) { $preview.html( $( '<img>' ).attr( 'src', url ) ); $rm.show(); }
				else { $preview.html( '<span class="tlt-poster__empty">No poster set.</span>' ); $rm.hide(); }
			}
			render( $box.data( 'current' ) || '' );

			$set.on( 'click', function () {
				if ( typeof wp === 'undefined' || ! wp.media ) return;
				var f = wp.media( { title: 'Select poster', button: { text: 'Use as poster' }, library: { type: 'image' }, multiple: false } );
				f.on( 'select', function () {
					var a = f.state().get( 'selection' ).first().toJSON();
					$h.val( a.id );
					render( ( a.sizes && a.sizes.medium ) ? a.sizes.medium.url : a.url );
				} );
				f.open();
			} );
			$rm.on( 'click', function () { $h.val( '0' ); render( '' ); } );
		} )();

		/* ---------- file pickers for URL fields (PDFs) ---------- */
		function initFilePicker( id, mime, label ) {
			var $in = $( '#' + id );
			if ( ! $in.length ) return;
			var $btn = $( '<button type="button" class="button tlt-filepick">' + label + '</button>' );
			$in.after( $btn );
			$btn.on( 'click', function () {
				if ( typeof wp === 'undefined' || ! wp.media ) return;
				var f = wp.media( { title: 'Select file', button: { text: 'Use this file' }, library: { type: mime }, multiple: false } );
				f.on( 'select', function () {
					var a = f.state().get( 'selection' ).first().toJSON();
					$in.val( a.url );
				} );
				f.open();
			} );
		}
		initFilePicker( 'show_program_pdf_url', 'application/pdf', 'Upload / Choose PDF' );
		initFilePicker( 'show_dramaturgy_url', 'application/pdf', 'Upload / Choose PDF' );

		/* ---------- reviews: "Publication + URL → Add" helper ---------- */
		( function initReviews() {
			var $ta = $( '#show_reviews' );
			if ( ! $ta.length ) return;
			var $name = $( '<input type="text" placeholder="Publication (e.g. The News Tribune)" class="tlt-rev-name">' );
			var $url  = $( '<input type="url" placeholder="https://link-to-review" class="tlt-rev-url">' );
			var $add  = $( '<button type="button" class="button">Add review</button>' );
			var $row  = $( '<div class="tlt-dates"></div>' )
				.append( '<strong>Add a review:</strong> ' ).append( $name ).append( ' ' ).append( $url ).append( ' ' ).append( $add );
			$ta.before( $row );
			$add.on( 'click', function () {
				var n = $.trim( $name.val() ), u = $.trim( $url.val() );
				if ( ! n || ! u ) return;
				var cur = $ta.val().replace( /\s+$/, '' );
				$ta.val( ( cur ? cur + '\n' : '' ) + n + ' | ' + u );
				$name.val( '' ); $url.val( '' );
			} );
		} )();

		/* ---------- date tools ---------- */
		function pad( n ) { return ( '0' + n ).slice( -2 ); }

		// "8/28/2026" or "2026-08-28" → "2026-08-28"
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

		// "7:30:00 PM" → "7:30 PM"; "19:30" → "7:30 PM"
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

		// One pasted line → "YYYY-MM-DD 7:30 PM [@ Location]" (or null if not date-like)
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

		// Whole pasted block → normalized text, or null if nothing looks like a date
		function normalizeScheduleText( text ) {
			var out = [], any = false;
			( text || '' ).split( /\r\n|\r|\n/ ).forEach( function ( raw ) {
				if ( ! $.trim( raw ) ) return;
				var n = normalizeLine( raw );
				if ( n ) { out.push( n ); any = true; } else { out.push( $.trim( raw ) ); }
			} );
			return any ? out.join( '\n' ) : null;
		}

		function initDates( id, opts ) {
			var $ta = $( '#' + id );
			if ( ! $ta.length ) return;

			// Paste straight from a spreadsheet: rows like "8/28/2026 <tab> 7:30:00 PM"
			// get auto-converted to "2026-08-28 7:30 PM". Non-date pastes are untouched.
			$ta.on( 'paste', function ( e ) {
				var clip = ( e.originalEvent || e ).clipboardData || window.clipboardData;
				if ( ! clip ) return;
				var normalized = normalizeScheduleText( clip.getData( 'text' ) );
				if ( normalized === null ) return; // not schedule data — let it paste normally
				e.preventDefault();
				var el = $ta[ 0 ], a = el.selectionStart, b = el.selectionEnd, v = el.value;
				el.value = v.slice( 0, a ) + normalized + v.slice( b );
				el.selectionStart = el.selectionEnd = a + normalized.length;
			} );

			function appendLines( lines ) {
				if ( ! lines.length ) return;
				var cur = $ta.val().replace( /\s+$/, '' );
				$ta.val( ( cur ? cur + '\n' : '' ) + lines.join( '\n' ) );
			}

			var $tools = $( '<div class="tlt-dates"></div>' );

			// single add
			var $d = $( '<input type="date">' );
			var $t = $( '<input type="text" class="tlt-dates__time" value="7:30 PM">' );
			var $loc = opts.location ? $( '<input type="text" placeholder="Location (optional)" class="tlt-dates__loc">' ) : null;
			var $addBtn = $( '<button type="button" class="button">Add date</button>' );
			var $one = $( '<div class="tlt-dates__row"></div>' )
				.append( '<strong>Add one:</strong> ' ).append( $d ).append( ' ' ).append( $t );
			if ( $loc ) $one.append( ' ' ).append( $loc );
			$one.append( ' ' ).append( $addBtn );
			$tools.append( $one );

			$addBtn.on( 'click', function () {
				if ( ! $d.val() ) return;
				var line = $d.val() + ' ' + ( $.trim( $t.val() ) || '7:30 PM' );
				if ( $loc && $.trim( $loc.val() ) ) line += ' @ ' + $.trim( $loc.val() );
				appendLines( [ line ] );
				$d.val( '' );
			} );

			// run generator (performances): pick a date range, check the days that
			// have shows, and set each day's own time (matinee or evening — your call).
			if ( opts.generator ) {
				var $gen = $( '<div class="tlt-dates__gen"></div>' );

				var $s = $( '<input type="date">' ), $e = $( '<input type="date">' );
				$gen.append( $( '<div class="tlt-dates__genrow"></div>' )
					.append( '<strong>Generate a run:</strong> ' ).append( $s ).append( ' to ' ).append( $e ) );

				$gen.append( '<div class="tlt-dates__genrow tlt-dates__hint">Check each day a show runs and set its time:</div>' );

				// one checkbox + time per weekday (Sunday defaults to a 2:00 matinee)
				var dows = [ [ 0, 'Sun', '2:00 PM' ], [ 1, 'Mon', '7:30 PM' ], [ 2, 'Tue', '7:30 PM' ],
					[ 3, 'Wed', '7:30 PM' ], [ 4, 'Thu', '7:30 PM' ], [ 5, 'Fri', '7:30 PM' ], [ 6, 'Sat', '7:30 PM' ] ];
				var dayInputs = {};
				var $grid = $( '<div class="tlt-dates__daygrid"></div>' );
				dows.forEach( function ( d ) {
					var $cb = $( '<input type="checkbox">' ).val( d[0] );
					var $time = $( '<input type="text" class="tlt-dates__time">' ).val( d[2] );
					dayInputs[ d[0] ] = { cb: $cb, time: $time };
					$grid.append( $( '<label class="tlt-dates__day"></label>' )
						.append( $cb ).append( ' ' + d[1] + ' ' ).append( $time ) );
				} );
				$gen.append( $grid );

				var $genBtn = $( '<button type="button" class="button button-secondary">Generate run &rarr;</button>' );
				$gen.append( $( '<div class="tlt-dates__genrow"></div>' ).append( $genBtn ) );
				$tools.append( $gen );

				$genBtn.on( 'click', function () {
					if ( ! $s.val() || ! $e.val() ) { alert( 'Pick a start and end date.' ); return; }
					var checked = [];
					Object.keys( dayInputs ).forEach( function ( k ) {
						if ( dayInputs[ k ].cb.is( ':checked' ) ) checked.push( parseInt( k, 10 ) );
					} );
					if ( ! checked.length ) { alert( 'Check at least one day.' ); return; }
					var start = new Date( $s.val() + 'T00:00' ), end = new Date( $e.val() + 'T00:00' );
					var lines = [], d;
					for ( d = new Date( start ); d <= end; d.setDate( d.getDate() + 1 ) ) {
						var dow = d.getDay();
						if ( checked.indexOf( dow ) === -1 ) continue;
						var time = $.trim( dayInputs[ dow ].time.val() ) || '7:30 PM';
						lines.push( d.getFullYear() + '-' + pad( d.getMonth() + 1 ) + '-' + pad( d.getDate() ) + ' ' + time );
					}
					appendLines( lines );
				} );
			}

			$ta.before( $tools );
		}

		initDates( 'show_performances', { generator: true } );
		initDates( 'show_audition_schedule', { location: true } );

		/* ---------- cast: import a Casting Manager CSV (drop or button) ----------
		 * Each row is: Character, First, Last, phone, email, … → "First Last as Character". */
		function castFromCsv( text ) {
			var out = [];
			( text || '' ).split( /\r\n|\r|\n/ ).forEach( function ( line ) {
				if ( ! $.trim( line ) ) return;
				var f = line.indexOf( '\t' ) >= 0 ? line.split( '\t' ) : line.split( ',' );
				if ( f.length < 3 ) return;
				var role = $.trim( f[0] ), first = $.trim( f[1] ), last = $.trim( f[2] );
				if ( /first name|last name|character|^role\b|e-?mail/i.test( role + ' ' + first + ' ' + last ) ) return; // skip header
				if ( ! first && ! last ) return;
				var actor = $.trim( ( first + ' ' + last ).replace( /\s+/g, ' ' ) );
				out.push( role ? actor + ' as ' + role : actor );
			} );
			return out.length ? out.join( ', ' ) : null;
		}

		( function initCast() {
			var $ta = $( '#show_cast' );
			if ( ! $ta.length ) return;

			function loadFile( file ) {
				if ( ! file ) return;
				var r = new FileReader();
				r.onload = function () {
					var cast = castFromCsv( r.result );
					if ( ! cast ) { alert( 'Could not find cast rows in that file.' ); return; }
					var cur = $.trim( $ta.val() );
					if ( cur && ! confirm( 'Replace the current cast with the imported list?\n(Cancel to append it instead.)' ) ) {
						$ta.val( cur + ', ' + cast );
					} else {
						$ta.val( cast );
					}
				};
				r.readAsText( file );
			}

			var $btn  = $( '<button type="button" class="button">Import cast CSV</button>' );
			var $file = $( '<input type="file" accept=".csv,text/csv" style="display:none">' );
			$ta.before( $( '<div class="tlt-cast-tools"></div>' )
				.append( $btn ).append( $file )
				.append( '<span class="description"> &mdash; or drag a Casting Manager CSV onto the box (columns: Character, First, Last, &hellip;)</span>' ) );
			$btn.on( 'click', function () { $file.trigger( 'click' ); } );
			$file.on( 'change', function () { loadFile( this.files && this.files[0] ); this.value = ''; } );

			$ta.on( 'dragover', function ( e ) { e.preventDefault(); $ta.addClass( 'tlt-drop' ); } );
			$ta.on( 'dragleave', function () { $ta.removeClass( 'tlt-drop' ); } );
			$ta.on( 'drop', function ( e ) {
				var dt = ( e.originalEvent || e ).dataTransfer;
				$ta.removeClass( 'tlt-drop' );
				if ( dt && dt.files && dt.files.length ) { e.preventDefault(); loadFile( dt.files[0] ); }
			} );
		} )();

	} );
} )( jQuery );
