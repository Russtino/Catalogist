/**
 * Map template edit screen: the map editor in a classic meta box. The map is
 * written to a hidden field and saved with the template.
 */
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { createRoot, useState } from '@wordpress/element';

import ProgramMap from '../components/program-map';
import '../editor.scss';

function TemplateEditor( { input } ) {
	const [ map, setMap ] = useState( () => {
		try {
			return JSON.parse( input.value ) || [];
		} catch ( e ) {
			return [];
		}
	} );

	return (
		<ProgramMap
			title={ __( 'Template map', 'catalogist' ) }
			map={ map }
			allowTemplates={ false }
			onChange={ ( next ) => {
				setMap( next );
				input.value = JSON.stringify( next );
			} }
		/>
	);
}

domReady( () => {
	const root = document.getElementById( 'catalogist-template-root' );
	const input = document.getElementById( 'catalogist-template-map-input' );
	if ( root && input ) {
		createRoot( root ).render( <TemplateEditor input={ input } /> );
	}
} );
