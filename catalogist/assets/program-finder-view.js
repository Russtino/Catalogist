/**
 * Program Finder and Course Finder blocks: search and filter items in the browser.
 * The current search and filters are kept in the URL so results can be
 * bookmarked or linked. Without this script, every program is listed.
 */
( function () {
	// Lowercase and strip accents, matching remove_accents() on the server.
	function normalize( text ) {
		return ( text || '' )
			.toLowerCase()
			.normalize( 'NFD' )
			.replace( /[\u0300-\u036f]/g, '' );
	}

	function init( root ) {
		var form = root.querySelector( '.catalogist-finder__form' );
		if ( ! form || root.classList.contains( 'is-interactive' ) ) {
			return;
		}

		var items = Array.prototype.slice.call( root.querySelectorAll( '.catalogist-finder__item' ) );
		var status = root.querySelector( '.catalogist-finder__status' );
		var empty = root.querySelector( '.catalogist-finder__empty' );
		var results = root.querySelector( '.catalogist-finder__results' );
		var search = form.querySelector( 'input[type="search"]' );
		var selects = Array.prototype.slice.call( form.querySelectorAll( 'select' ) );
		var fields = ( search ? [ search ] : [] ).concat( selects );
		var announceTimer;

		// Restore filters from the URL, ignoring values that no longer exist.
		var params = new URLSearchParams( window.location.search );
		fields.forEach( function ( field ) {
			if ( params.has( field.name ) ) {
				field.value = params.get( field.name );
				if ( field.value !== params.get( field.name ) ) {
					field.value = '';
				}
			}
		} );

		function announce( count ) {
			var template = root.getAttribute( count === 1 ? 'data-count-one' : 'data-count-other' );
			var text = template.replace( '%d', count );

			// Wait for a pause in typing so screen readers aren't flooded.
			clearTimeout( announceTimer );
			announceTimer = setTimeout( function () {
				status.textContent = text;
			}, 400 );
		}

		function updateUrl() {
			var next = new URLSearchParams( window.location.search );
			fields.forEach( function ( field ) {
				if ( field.value ) {
					next.set( field.name, field.value );
				} else {
					next.delete( field.name );
				}
			} );
			var query = next.toString();
			window.history.replaceState(
				null,
				'',
				window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash
			);
		}

		function apply( saveToUrl ) {
			var words = search ? normalize( search.value ).split( /\s+/ ).filter( Boolean ) : [];
			var shown = 0;

			items.forEach( function ( item ) {
				var text = item.getAttribute( 'data-search' ) || '';
				var match = words.every( function ( word ) {
					return text.indexOf( word ) !== -1;
				} );

				selects.forEach( function ( select ) {
					if ( match && select.value ) {
						var slugs = ' ' + ( item.getAttribute( 'data-' + select.name ) || '' ) + ' ';
						match = slugs.indexOf( ' ' + select.value + ' ' ) !== -1;
					}
				} );

				item.hidden = ! match;
				if ( match ) {
					shown++;
				}
			} );

			empty.hidden = shown !== 0;
			if ( results ) {
				results.hidden = shown === 0; // Hides the course table's header row too.
			}
			announce( shown );
			if ( saveToUrl ) {
				updateUrl();
			}
		}

		form.addEventListener( 'input', function () {
			apply( true );
		} );
		form.addEventListener( 'change', function () {
			apply( true );
		} );
		form.addEventListener( 'submit', function ( event ) {
			event.preventDefault(); // Results already update as you type.
		} );
		form.addEventListener( 'reset', function () {
			// Fields are cleared after the reset event finishes.
			setTimeout( function () {
				apply( true );
			}, 0 );
		} );

		// The "Clear filters" button shown when nothing matches.
		empty.querySelector( '.catalogist-finder__reset' ).addEventListener( 'click', function () {
			form.reset();
			( search || selects[ 0 ] ).focus();
		} );

		form.hidden = false;
		root.classList.add( 'is-interactive' );
		apply( false );
	}

	function boot() {
		document
			.querySelectorAll( '.wp-block-catalogist-program-finder, .wp-block-catalogist-course-finder' )
			.forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
