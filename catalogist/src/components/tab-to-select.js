/**
 * Lets Tab pick the highlighted suggestion in a course search box, the way
 * Enter already does. Attach it as `onKeyDownCapture` on an element that
 * wraps a ComboboxControl or FormTokenField.
 *
 * Tab only does this while something has been typed and a suggestion is
 * highlighted. Otherwise it moves focus as usual, so keyboard users can
 * still tab through the editor.
 */
const HIGHLIGHTED = '.components-form-token-field__suggestion.is-selected';

export default function tabToSelect( event ) {
	if ( event.key !== 'Tab' || event.shiftKey || event.altKey || event.ctrlKey || event.metaKey ) {
		return;
	}

	const input = event.target;
	if ( ! ( input instanceof window.HTMLInputElement ) || input.value.trim() === '' ) {
		return;
	}

	if ( ! event.currentTarget.querySelector( HIGHLIGHTED ) ) {
		return;
	}

	// Keep focus in the box, ready for the next course, and let the
	// component's own Enter handling add the highlighted suggestion.
	event.preventDefault();
	event.stopPropagation();
	input.dispatchEvent(
		new window.KeyboardEvent( 'keydown', {
			key: 'Enter',
			code: 'Enter',
			keyCode: 13,
			which: 13,
			bubbles: true,
			cancelable: true,
		} )
	);
}
