import { TextControl } from '@wordpress/components';

/**
 * Number input for credit hours. Stores a number; an empty box saves as 0.
 */
export default function CreditsControl( { label, help, value, onChange } ) {
	return (
		<TextControl
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			type="number"
			min={ 0 }
			step={ 0.5 }
			label={ label }
			help={ help }
			value={ value ? String( value ) : '' }
			onChange={ ( next ) => {
				const parsed = parseFloat( next );
				onChange( Number.isNaN( parsed ) ? 0 : parsed );
			} }
		/>
	);
}
