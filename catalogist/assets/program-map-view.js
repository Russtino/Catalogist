/**
 * Program Map block: turns multiple credential maps into accessible tabs.
 * Follows the WAI-ARIA tabs pattern (arrow keys, Home, End). Without this
 * script the maps stay stacked under their headings.
 */
( function () {
	function init( root ) {
		var tablist = root.querySelector( '.catalogist-program-map__tabs' );
		if ( ! tablist || root.classList.contains( 'is-tabbed' ) ) {
			return;
		}

		var tabs = Array.prototype.slice.call( tablist.querySelectorAll( '[role="tab"]' ) );
		var panels = tabs.map( function ( tab ) {
			return document.getElementById( tab.getAttribute( 'aria-controls' ) );
		} );

		if ( panels.indexOf( null ) !== -1 ) {
			return;
		}

		panels.forEach( function ( panel, i ) {
			panel.setAttribute( 'role', 'tabpanel' );
			panel.setAttribute( 'aria-labelledby', tabs[ i ].id );
			panel.setAttribute( 'tabindex', '0' );

			// The tab now names the panel, so its heading would be repetitive.
			var heading = panel.querySelector( '.catalogist-program-map__heading' );
			if ( heading ) {
				heading.hidden = true;
			}
		} );

		function select( index, moveFocus ) {
			tabs.forEach( function ( tab, i ) {
				var active = i === index;
				tab.setAttribute( 'aria-selected', active ? 'true' : 'false' );
				tab.tabIndex = active ? 0 : -1;
				panels[ i ].hidden = ! active;
			} );
			if ( moveFocus ) {
				tabs[ index ].focus();
			}
		}

		tablist.addEventListener( 'click', function ( event ) {
			var tab = event.target.closest( '[role="tab"]' );
			if ( tab ) {
				select( tabs.indexOf( tab ), false );
			}
		} );

		tablist.addEventListener( 'keydown', function ( event ) {
			var current = tabs.indexOf( document.activeElement );
			var keys = {
				ArrowRight: current + 1,
				ArrowLeft: current - 1,
				Home: 0,
				End: tabs.length - 1,
			};

			if ( current === -1 || ! ( event.key in keys ) ) {
				return;
			}
			event.preventDefault();
			select( ( keys[ event.key ] + tabs.length ) % tabs.length, true );
		} );

		tablist.hidden = false;
		root.classList.add( 'is-tabbed' );
		select( 0, false );
	}

	/**
	 * Pathway switcher: "All · Coding · Networking" toggle buttons that show one
	 * pathway's rows and totals. Without this script, every pathway is listed.
	 */
	function initPathways( switcher ) {
		var panel = switcher.closest( '.catalogist-program-map__credential' );
		if ( ! panel || switcher.getAttribute( 'data-ready' ) ) {
			return;
		}
		switcher.setAttribute( 'data-ready', '1' );

		var buttons = Array.prototype.slice.call( switcher.querySelectorAll( '[data-pathway]' ) );
		var description = switcher.querySelector( '.catalogist-pathways__description' );
		var descriptions = {};
		try {
			descriptions = JSON.parse( switcher.getAttribute( 'data-descriptions' ) ) || {};
		} catch ( e ) {}

		function select( id ) {
			buttons.forEach( function ( button ) {
				button.setAttribute( 'aria-pressed', button.getAttribute( 'data-pathway' ) === id ? 'true' : 'false' );
			} );

			panel.querySelectorAll( 'tbody[data-pathway]' ).forEach( function ( group ) {
				group.hidden = id !== '' && group.getAttribute( 'data-pathway' ) !== id;
			} );

			panel.querySelectorAll( '[data-totals]' ).forEach( function ( el ) {
				try {
					var texts = JSON.parse( el.getAttribute( 'data-totals' ) );
					el.textContent = id in texts ? texts[ id ] : texts[ '' ];
				} catch ( e ) {}
			} );

			if ( description ) {
				description.textContent = descriptions[ id ] || '';
				description.hidden = ! descriptions[ id ];
			}
		}

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				select( button.getAttribute( 'data-pathway' ) );
			} );
		} );

		switcher.hidden = false;
		select( '' );
	}

	function boot() {
		document.querySelectorAll( '.catalogist-pathways' ).forEach( initPathways );
		document.querySelectorAll( '.catalogist-program-map--tabs' ).forEach( init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
