/**
 * Editor entry point: adds Program and Course detail panels
 * to the document sidebar of the block editor.
 */
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';

import CourseFields from './components/course-fields';
import ProgramFields from './components/program-fields';
import CredentialsPanel from './components/credentials-panel';
import ProgramLists from './components/program-lists';

import './editor.scss';

function CatalogPanels() {
	const postType = useSelect(
		( select ) => select( editorStore ).getCurrentPostType(),
		[]
	);

	if ( postType === 'catalogist_course' ) {
		return (
			<PluginDocumentSettingPanel
				name="catalogist-course-details"
				title={ __( 'Course Details', 'catalogist' ) }
			>
				<CourseFields />
			</PluginDocumentSettingPanel>
		);
	}

	if ( postType === 'catalogist_program' ) {
		return (
			<>
				<PluginDocumentSettingPanel
					name="catalogist-program-details"
					title={ __( 'Program Details', 'catalogist' ) }
				>
					<ProgramFields />
				</PluginDocumentSettingPanel>
				<PluginDocumentSettingPanel
					name="catalogist-program-credentials"
					title={ __( 'Credentials', 'catalogist' ) }
				>
					<CredentialsPanel />
				</PluginDocumentSettingPanel>
				<PluginDocumentSettingPanel
					name="catalogist-program-lists"
					title={ __( 'Outcomes & Careers', 'catalogist' ) }
				>
					<ProgramLists />
				</PluginDocumentSettingPanel>
			</>
		);
	}

	return null;
}

registerPlugin( 'catalogist-panels', { render: CatalogPanels } );
