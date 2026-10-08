import { __ } from '@wordpress/i18n';
import { __experimentalVStack as VStack } from '@wordpress/components';

import useMeta from '../hooks/use-meta';
import LinesControl from './lines-control';

const ONE_PER_LINE = __( 'One per line. Leave empty to hide this section.', 'catalogist' );

/**
 * Outcomes & Careers panel: the lists shown by the Program List block.
 */
export default function ProgramLists() {
	const [ meta, update ] = useMeta();

	const list = ( key, label, rows = 4 ) => (
		<LinesControl
			label={ label }
			help={ ONE_PER_LINE }
			rows={ rows }
			value={ meta[ key ] ?? [] }
			onChange={ ( v ) => update( key, v ) }
		/>
	);

	return (
		<VStack spacing={ 4 }>
			{ list( '_catalogist_outcomes', __( 'Program outcomes', 'catalogist' ), 6 ) }
			{ list( '_catalogist_careers', __( 'Careers', 'catalogist' ) ) }
			{ list( '_catalogist_certifications', __( 'Certifications graduates earn', 'catalogist' ) ) }
			{ list( '_catalogist_certifications_optional', __( 'Certifications graduates can also earn', 'catalogist' ) ) }
		</VStack>
	);
}
