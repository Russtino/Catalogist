import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import App from './app';
import './import.scss';

domReady( () => {
	const root = document.getElementById( 'catalogist-import-root' );
	if ( root ) {
		createRoot( root ).render(
			<App
				coursesUrl={ root.dataset.coursesUrl }
				canPublish={ root.dataset.canPublish === '1' }
			/>
		);
	}
} );
