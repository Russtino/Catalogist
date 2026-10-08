/**
 * Registers the catalog blocks in the editor.
 * Front-end output comes from each block's render.php.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import { PanelBody, Placeholder, SelectControl, TextControl, ToggleControl } from '@wordpress/components';

import programMap from '../../blocks/program-map/block.json';
import programDetails from '../../blocks/program-details/block.json';
import courseInfo from '../../blocks/course-info/block.json';
import programList from '../../blocks/program-list/block.json';
import courseDescription from '../../blocks/course-description/block.json';
import programFinder from '../../blocks/program-finder/block.json';
import courseFinder from '../../blocks/course-finder/block.json';
import createEdit from './create-edit';
import useCredentialTerms from '../hooks/use-credential-terms';

function ProgramMapControls( { attributes, setAttributes } ) {
	// Empty outside a program (e.g. in a site template), so only "All" is offered.
	const terms = useCredentialTerms() || [];

	return (
		<PanelBody title={ __( 'Settings', 'catalogist' ) }>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Credential', 'catalogist' ) }
				help={ __(
					'“All credentials” shows every map that has courses.',
					'catalogist'
				) }
				value={ String( attributes.credential ) }
				options={ [
					{ label: __( 'All credentials', 'catalogist' ), value: '0' },
					...terms.map( ( term ) => ( { label: term.name, value: String( term.id ) } ) ),
				] }
				onChange={ ( value ) => setAttributes( { credential: parseInt( value, 10 ) } ) }
			/>
			{ attributes.credential === 0 && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Show multiple credentials as', 'catalogist' ) }
					help={ __(
						'Tabs appear on the published page. The editor preview always shows the maps stacked.',
						'catalogist'
					) }
					value={ attributes.display }
					options={ [
						{ label: __( 'Tabs', 'catalogist' ), value: 'tabs' },
						{ label: __( 'Stacked', 'catalogist' ), value: 'stacked' },
					] }
					onChange={ ( display ) => setAttributes( { display } ) }
				/>
			) }
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Credential heading level', 'catalogist' ) }
				value={ String( attributes.headingLevel ) }
				options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( {
					label: `H${ level }`,
					value: String( level ),
				} ) ) }
				onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Semesters per row', 'catalogist' ) }
				help={ __(
					'Fewer per row gives each semester more room, so titles wrap less. Small screens always show one per row.',
					'catalogist'
				) }
				value={ String( attributes.columns ?? 0 ) }
				options={ [
					{ label: __( 'As many as fit', 'catalogist' ), value: '0' },
					{ label: '1', value: '1' },
					{ label: '2', value: '2' },
					{ label: '3', value: '3' },
					{ label: '4', value: '4' },
				] }
				onChange={ ( value ) => setAttributes( { columns: parseInt( value, 10 ) } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Link courses to their pages', 'catalogist' ) }
				checked={ attributes.linkCourses }
				onChange={ ( linkCourses ) => setAttributes( { linkCourses } ) }
			/>
			<ToggleControl
				__nextHasNoMarginBottom
				label={ __( 'Show term credit totals', 'catalogist' ) }
				checked={ attributes.showTermTotals }
				onChange={ ( showTermTotals ) => setAttributes( { showTermTotals } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( programMap, {
	edit: createEdit( {
		metadata: programMap,
		postType: 'catalogist_program',
		emptyMessage: __(
			'No program map yet. Build one in the Credentials panel in the sidebar, then update the program to see it here.',
			'catalogist'
		),
		Controls: ProgramMapControls,
	} ),
} );

registerBlockType( programDetails, {
	edit: createEdit( {
		metadata: programDetails,
		postType: 'catalogist_program',
		emptyMessage: __(
			'No details yet. Tick credential types and fill in the Credentials panel in the sidebar, then update the program.',
			'catalogist'
		),
	} ),
} );

registerBlockType( courseInfo, {
	edit: createEdit( {
		metadata: courseInfo,
		postType: 'catalogist_course',
		emptyMessage: __(
			'Fill in the Course Details panel in the sidebar, then update the course to see it here.',
			'catalogist'
		),
		Controls: ( { attributes, setAttributes } ) => (
			<PanelBody title={ __( 'Settings', 'catalogist' ) }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Link prerequisites to their pages', 'catalogist' ) }
					checked={ attributes.linkPrerequisites }
					onChange={ ( linkPrerequisites ) => setAttributes( { linkPrerequisites } ) }
				/>
			</PanelBody>
		),
	} ),
} );

const LIST_TYPES = {
	outcomes: {
		title: __( 'Program Outcomes', 'catalogist' ),
		heading: __( 'Program outcomes', 'catalogist' ),
		icon: 'yes-alt',
	},
	careers: {
		title: __( 'Careers', 'catalogist' ),
		heading: __( 'Career opportunities', 'catalogist' ),
		icon: 'businessperson',
	},
	certifications: {
		title: __( 'Industry Certifications', 'catalogist' ),
		heading: __( 'Industry certifications', 'catalogist' ),
		icon: 'awards',
	},
};

function ProgramListControls( { attributes, setAttributes } ) {
	return (
		<PanelBody title={ __( 'Settings', 'catalogist' ) }>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'List', 'catalogist' ) }
				value={ attributes.list }
				options={ Object.entries( LIST_TYPES ).map( ( [ value, type ] ) => ( {
					label: type.title,
					value,
				} ) ) }
				onChange={ ( list ) => setAttributes( { list } ) }
			/>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Heading', 'catalogist' ) }
				help={ __( 'Leave empty for the default. The heading only appears when the list has items.', 'catalogist' ) }
				placeholder={ LIST_TYPES[ attributes.list ]?.heading }
				value={ attributes.heading }
				onChange={ ( heading ) => setAttributes( { heading } ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Heading level', 'catalogist' ) }
				value={ String( attributes.headingLevel ) }
				options={ [ 2, 3, 4, 5, 6 ].map( ( level ) => ( { label: `H${ level }`, value: String( level ) } ) ) }
				onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
			/>
		</PanelBody>
	);
}

registerBlockType( programList, {
	edit: createEdit( {
		metadata: programList,
		postType: 'catalogist_program',
		emptyMessage: __(
			'This list is empty, so nothing will show on the page. Add items in the Outcomes & Careers panel in the sidebar, then update the program.',
			'catalogist'
		),
		Controls: ProgramListControls,
	} ),
	// Separate entries in the inserter for each list.
	variations: Object.entries( LIST_TYPES ).map( ( [ list, type ], i ) => ( {
		name: list,
		title: type.title,
		icon: type.icon,
		attributes: { list },
		isDefault: i === 0,
		isActive: [ 'list' ],
		scope: [ 'inserter', 'transform' ],
	} ) ),
} );

registerBlockType( courseDescription, {
	edit: createEdit( {
		metadata: courseDescription,
		postType: 'catalogist_course',
		emptyMessage: __(
			'Add a description in the Course Details panel in the sidebar, then update the course to see it here.',
			'catalogist'
		),
	} ),
} );

// Finders list every published item, so they don't depend on the current post.
function createFinderEdit( { metadata, toggles, emptyMessage, headingLevels } ) {
	return function Edit( { attributes, setAttributes } ) {
		return (
			<>
				<InspectorControls>
					<PanelBody title={ __( 'Show', 'catalogist' ) }>
						{ toggles.map( ( [ key, label ] ) => (
							<ToggleControl
								key={ key }
								__nextHasNoMarginBottom
								label={ label }
								checked={ attributes[ key ] }
								onChange={ ( value ) => setAttributes( { [ key ]: value } ) }
							/>
						) ) }
						<p className="components-base-control__help">
							{ __(
								'A filter only appears when it has options to choose from.',
								'catalogist'
							) }
						</p>
					</PanelBody>
					{ headingLevels && (
						<PanelBody title={ __( 'Settings', 'catalogist' ) }>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Title heading level', 'catalogist' ) }
								value={ String( attributes.headingLevel ) }
								options={ headingLevels.map( ( level ) => ( {
									label: `H${ level }`,
									value: String( level ),
								} ) ) }
								onChange={ ( value ) => setAttributes( { headingLevel: parseInt( value, 10 ) } ) }
							/>
						</PanelBody>
					) }
				</InspectorControls>
				<div { ...useBlockProps() }>
					<ServerSideRender
						block={ metadata.name }
						attributes={ attributes }
						EmptyResponsePlaceholder={ () => (
							<Placeholder label={ metadata.title } instructions={ emptyMessage } />
						) }
					/>
				</div>
			</>
		);
	};
}

registerBlockType( programFinder, {
	edit: createFinderEdit( {
		metadata: programFinder,
		headingLevels: [ 2, 3, 4 ],
		emptyMessage: __( 'No published programs yet. Published programs will be listed here.', 'catalogist' ),
		toggles: [
			[ 'showSearch', __( 'Search box', 'catalogist' ) ],
			[ 'showCredentialFilter', __( 'Credential filter', 'catalogist' ) ],
			[ 'showCareerFilter', __( 'Career area filter', 'catalogist' ) ],
			[ 'showDeliveryFilter', __( 'Delivery filter', 'catalogist' ) ],
			[ 'showDepartmentFilter', __( 'Department filter', 'catalogist' ) ],
			[ 'showExcerpt', __( 'Program excerpts', 'catalogist' ) ],
		],
	} ),
} );

registerBlockType( courseFinder, {
	edit: createFinderEdit( {
		metadata: courseFinder,
		emptyMessage: __( 'No published courses yet. Published courses will be listed here.', 'catalogist' ),
		toggles: [
			[ 'showSearch', __( 'Search box', 'catalogist' ) ],
			[ 'showDepartmentFilter', __( 'Department filter', 'catalogist' ) ],
			[ 'showProgramFilter', __( 'Program filter', 'catalogist' ) ],
			[ 'showDepartmentColumn', __( 'Department column', 'catalogist' ) ],
			[ 'linkCourses', __( 'Link courses to their pages', 'catalogist' ) ],
		],
	} ),
} );
