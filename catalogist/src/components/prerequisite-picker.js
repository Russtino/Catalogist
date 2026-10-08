/**
 * Prerequisites: any course except the one being edited.
 */
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';

import CoursePicker from './course-picker';

export default function PrerequisitePicker( { value, onChange } ) {
	const currentId = useSelect( ( select ) => select( editorStore ).getCurrentPostId(), [] );
	const exclude = useMemo( () => [ currentId ], [ currentId ] );

	return (
		<CoursePicker
			label={ __( 'Prerequisites', 'catalogist' ) }
			value={ value }
			onChange={ onChange }
			exclude={ exclude }
		/>
	);
}
