/**
 * A textarea for a list: one item per line, stored as an array of strings.
 * The raw text is kept locally so blank lines can be typed while editing.
 */
import { useState } from '@wordpress/element';
import { TextareaControl } from '@wordpress/components';

/**
 * Copying a multi-line cell from Excel or Google Sheets wraps it in quotes and
 * doubles any quotes inside. Undo that when the whole text arrives that way.
 */
const unwrapSpreadsheetCell = ( text ) => {
	const trimmed = text.trim();
	if (
		trimmed.length > 1 &&
		trimmed.startsWith( '"' ) &&
		trimmed.endsWith( '"' ) &&
		trimmed.includes( '\n' )
	) {
		return trimmed.slice( 1, -1 ).replace( /""/g, '"' );
	}
	return text;
};

export default function LinesControl( {
	label,
	help,
	value = [],
	onChange,
	rows = 4,
} ) {
	const [ text, setText ] = useState( value.join( '\n' ) );

	return (
		<TextareaControl
			__nextHasNoMarginBottom
			label={ label }
			help={ help }
			rows={ rows }
			value={ text }
			onChange={ ( raw ) => {
				const next = unwrapSpreadsheetCell( raw );
				setText( next );
				onChange(
					next
						.split( '\n' )
						.map( ( line ) => line.trim() )
						.filter( Boolean )
				);
			} }
		/>
	);
}
