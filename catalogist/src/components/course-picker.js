/**
 * Searchable multi-select of courses, stored as an array of post IDs.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { FormTokenField, Spinner } from '@wordpress/components';

import useCourses from '../hooks/use-courses';
import tabToSelect from './tab-to-select';

const MISSING = /^#(\d+) /;

export default function CoursePicker( { label, help, value, onChange, exclude = [] } ) {
	const { isLoading, list, byId } = useCourses();

	const idByLabel = useMemo(
		() =>
			new Map(
				list
					.filter( ( course ) => ! exclude.includes( course.id ) )
					.map( ( course ) => [ course.label, course.id ] )
			),
		[ list, exclude ]
	);

	if ( isLoading ) {
		return <Spinner />;
	}

	const missingLabel = ( id ) =>
		/* translators: %d: post ID */
		sprintf( __( '#%d (course not found)', 'catalogist' ), id );

	const tokens = value.map( ( id ) => byId.get( id )?.label ?? missingLabel( id ) );

	const handleChange = ( nextTokens ) => {
		const ids = nextTokens
			.map( ( token ) => ( typeof token === 'string' ? token : token.value ) )
			.map( ( text ) => {
				if ( idByLabel.has( text ) ) {
					return idByLabel.get( text );
				}
				// Keep references to deleted courses until the user removes them.
				const match = text.match( MISSING );
				return match ? parseInt( match[ 1 ], 10 ) : null;
			} )
			.filter( Boolean );

		onChange( [ ...new Set( ids ) ] );
	};

	const field = (
		<FormTokenField
			__nextHasNoMarginBottom
			__next40pxDefaultSize
			label={ label }
			value={ tokens }
			suggestions={ [ ...idByLabel.keys() ] }
			onChange={ handleChange }
			__experimentalExpandOnFocus
			__experimentalAutoSelectFirstMatch
			__experimentalValidateInput={ ( text ) => idByLabel.has( text ) }
			__experimentalShowHowTo={ false }
		/>
	);

	// Tab, like Enter, adds the highlighted course.
	return (
		<div onKeyDownCapture={ tabToSelect }>
			{ field }
			{ help && (
				<p className="components-form-token-field__help">{ help }</p>
			) }
		</div>
	);
}
