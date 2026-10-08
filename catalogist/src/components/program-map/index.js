/**
 * Map editor: a summary plus a full-size editor in a modal. Used for each
 * credential's program map and for map templates. The parent owns the data.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import {
	Button,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { plus } from '@wordpress/icons';

import useCourses from '../../hooks/use-courses';
import TermColumn from './term-column';
import validateMap from './validate';
import * as ops from './map-utils';

const ACTIONS = [
	'renameTerm',
	'removeTerm',
	'moveTerm',
	'addCourse',
	'addChoice',
	'updateItem',
	'removeItem',
	'moveItem',
	'moveItemTo',
	'shiftItem',
];

const TEMPLATE_QUERY = {
	per_page: -1,
	status: 'publish,draft,pending,future,private',
	orderby: 'title',
	order: 'asc',
	_fields: 'id,title,meta',
};

/**
 * @param {Object}   props
 * @param {string}   props.title          Modal title.
 * @param {Array}    props.map            Terms (older maps are normalized automatically).
 * @param {Function} props.onChange       Receives the new map.
 * @param {number}   props.declaredTotal  Credits the map should add up to (0 = don't check).
 * @param {Array}    props.copySources    Other maps to offer copying: [ { name, map } ].
 * @param {boolean}  props.allowTemplates Offer "Apply template" (off when editing a template).
 * @param {Array}    props.pathways       The credential's pathways: [ { id, name } ].
 */
export default function ProgramMap( {
	title,
	map: rawMap,
	onChange,
	declaredTotal = 0,
	copySources = [],
	allowTemplates = true,
	pathways: rawPathways = [],
} ) {
	// Pathways need a name to be usable in the editor.
	const pathways = useMemo(
		() => rawPathways.filter( ( p ) => p.id && p.name ),
		[ rawPathways ]
	);
	const pathwayIds = pathways.map( ( p ) => p.id );
	const map = useMemo( () => ops.normalizeMap( rawMap ), [ rawMap ] );
	const { isLoading, list, byId } = useCourses();
	const [ isOpen, setOpen ] = useState( false );
	const [ templateId, setTemplateId ] = useState( '' );
	const [ applied, setApplied ] = useState( '' );
	// Terms per row in the editor (0 = as many as fit), remembered in this browser.
	const [ columns, setColumns ] = useState( () => {
		try {
			return (
				parseInt(
					window.localStorage.getItem( 'catalogistMapColumns' ),
					10
				) || 0
			);
		} catch ( e ) {
			return 0;
		}
	} );
	const chooseColumns = ( value ) => {
		const next = parseInt( value, 10 ) || 0;
		setColumns( next );
		try {
			window.localStorage.setItem(
				'catalogistMapColumns',
				String( next )
			);
		} catch ( e ) {
			// Storage unavailable: the choice lasts until the editor closes.
		}
	};
	const [ removal, setRemoval ] = useState( null ); // { title, entries } awaiting confirmation

	// null while loading or if the request failed; resolved tells those apart.
	const { templates, templatesResolved } = useSelect(
		( select ) => {
			if ( ! allowTemplates ) {
				return { templates: null, templatesResolved: true };
			}
			const store = select( coreStore );
			const args = [ 'postType', 'catalogist_template', TEMPLATE_QUERY ];
			return {
				templates: store.getEntityRecords( ...args ),
				templatesResolved: store.hasFinishedResolution(
					'getEntityRecords',
					args
				),
			};
		},
		[ allowTemplates ]
	);

	const issues = useMemo(
		() => validateMap( map, byId, declaredTotal, pathways ),
		[ map, byId, declaredTotal, pathways ]
	);

	const actions = Object.fromEntries(
		ACTIONS.map( ( name ) => [
			name,
			( ...args ) => onChange( ops[ name ]( map, ...args ) ),
		] )
	);

	if ( isLoading ) {
		return <Spinner />;
	}

	const problemCount = issues.filter(
		( issue ) => issue.status !== 'info'
	).length;
	// "62", or per pathway: "Coding 62 · Networking 63".
	const total = pathways.length
		? pathways
				.map( ( p ) =>
					sprintf(
						/* translators: 1: pathway name, 2: credit hours */
						__( '%1$s %2$s', 'catalogist' ),
						p.name,
						ops.formatCredits(
							ops.mapCredits( map, byId, p.id, pathwayIds )
						)
					)
				)
				.join( ' · ' )
		: ops.formatCredits( ops.mapCredits( map, byId ) );
	const templateTitle = ( template ) =>
		decodeEntities(
			template.title?.raw ?? template.title?.rendered ?? ''
		) || __( '(untitled template)', 'catalogist' );

	const applyTemplate = () => {
		const template = templates.find(
			( t ) => String( t.id ) === templateId
		);
		if ( template ) {
			onChange(
				ops.applyTemplate(
					map,
					template.meta?._catalogist_template_map ?? [],
					template.id
				)
			);
			/* translators: %s: template name */
			setApplied(
				sprintf(
					__(
						'Added “%s.” Courses already in this map were skipped.',
						'catalogist'
					),
					templateTitle( template )
				)
			);
			setTemplateId( '' );
			setRemoval( null );
		}
	};

	// Find what the chosen template added, then ask for confirmation.
	const startRemoval = () => {
		const template = templates.find(
			( t ) => String( t.id ) === templateId
		);
		if ( ! template ) {
			return;
		}
		const entries = ops.templateEntries(
			map,
			template.meta?._catalogist_template_map ?? [],
			template.id
		);
		if ( ! entries.length ) {
			setRemoval( null );
			setApplied(
				sprintf(
					/* translators: %s: template name */
					__(
						'Nothing from “%s” was found in this map.',
						'catalogist'
					),
					templateTitle( template )
				)
			);
			return;
		}
		setApplied( '' );
		setRemoval( { title: templateTitle( template ), entries } );
	};

	const confirmRemoval = () => {
		onChange( ops.removeEntries( map, removal.entries ) );
		setApplied(
			sprintf(
				/* translators: 1: number of entries, 2: template name */
				__(
					'Removed %1$d entries from “%2$s.” Empty terms were kept; remove them if you don’t need them.',
					'catalogist'
				),
				removal.entries.length,
				removal.title
			)
		);
		setRemoval( null );
		setTemplateId( '' );
	};

	const entryName = ( item ) =>
		item.type === 'choice'
			? item.label || __( 'Elective', 'catalogist' )
			: byId.get( item.id )?.label ?? `#${ item.id }`;

	return (
		<VStack spacing={ 3 }>
			<p className="catalogist-map-summary">
				{ map.length
					? sprintf(
							/* translators: 1: number of terms, 2: number of courses and choices, 3: credit hours */
							__(
								'Map: %1$d terms · %2$d entries · %3$s credits',
								'catalogist'
							),
							map.length,
							ops.itemCount( map ),
							total
					  )
					: __( 'No map yet.', 'catalogist' ) }
			</p>

			{ problemCount > 0 && (
				<Notice status="warning" isDismissible={ false }>
					{ sprintf(
						/* translators: %d: number of issues */
						_n(
							'%d scheduling issue',
							'%d scheduling issues',
							problemCount,
							'catalogist'
						),
						problemCount
					) }
				</Notice>
			) }

			<Button variant="secondary" onClick={ () => setOpen( true ) }>
				{ map.length
					? __( 'Edit map', 'catalogist' )
					: __( 'Create map', 'catalogist' ) }
			</Button>

			{ ! map.length &&
				copySources.map( ( source ) => (
					<Button
						key={ source.name }
						variant="tertiary"
						onClick={ () =>
							// Pathways belong to each credential, so copied entries start out shared.
							onChange(
								ops
									.normalizeMap( source.map )
									.map( ( term ) => ( {
										...term,
										items: term.items.map( ( item ) => ( {
											...structuredClone( item ),
											pathway: '',
										} ) ),
									} ) )
							)
						}
					>
						{ sprintf(
							/* translators: %s: credential type */
							__( 'Copy map from %s', 'catalogist' ),
							source.name
						) }
					</Button>
				) ) }

			{ isOpen && (
				<Modal
					title={ title }
					size="fill"
					onRequestClose={ () => setOpen( false ) }
				>
					<div className="catalogist-map-toolbar">
						<Button
							variant="primary"
							icon={ plus }
							onClick={ () => onChange( ops.addTerm( map ) ) }
						>
							{ __( 'Add term', 'catalogist' ) }
						</Button>

						{ allowTemplates && (
							<div className="catalogist-map-toolbar__template">
								{ ! templatesResolved && <Spinner /> }
								{ templatesResolved &&
									templates?.length > 0 && (
										<>
											<SelectControl
												__nextHasNoMarginBottom
												__next40pxDefaultSize
												hideLabelFromVision
												label={ __(
													'Map template',
													'catalogist'
												) }
												value={ templateId }
												options={ [
													{
														label: __(
															'Choose a template…',
															'catalogist'
														),
														value: '',
													},
													...templates.map(
														( t ) => ( {
															label: templateTitle(
																t
															),
															value: String(
																t.id
															),
														} )
													),
												] }
												onChange={ ( value ) => {
													setTemplateId( value );
													setApplied( '' );
													setRemoval( null );
												} }
											/>
											<Button
												variant="secondary"
												disabled={ ! templateId }
												onClick={ applyTemplate }
											>
												{ __( 'Apply', 'catalogist' ) }
											</Button>
											<Button
												variant="tertiary"
												isDestructive
												disabled={ ! templateId }
												onClick={ startRemoval }
											>
												{ __( 'Remove', 'catalogist' ) }
											</Button>
										</>
									) }
								{ templatesResolved &&
									Array.isArray( templates ) &&
									templates.length === 0 && (
										<span className="catalogist-map-toolbar__note">
											{ __(
												'No saved map templates.',
												'catalogist'
											) }{ ' ' }
											<a
												href="edit.php?post_type=catalogist_template"
												target="_blank"
												rel="noreferrer"
											>
												{ __(
													'Manage templates',
													'catalogist'
												) }
											</a>
										</span>
									) }
								{ templatesResolved &&
									! Array.isArray( templates ) && (
										<span className="catalogist-map-toolbar__note">
											{ __(
												'Map templates couldn’t be loaded.',
												'catalogist'
											) }
										</span>
									) }
							</div>
						) }

						<div className="catalogist-map-toolbar__columns">
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Terms per row', 'catalogist' ) }
								labelPosition="side"
								value={ String( columns ) }
								options={ [
									{
										label: __(
											'As many as fit',
											'catalogist'
										),
										value: '0',
									},
									{ label: '1', value: '1' },
									{ label: '2', value: '2' },
									{ label: '3', value: '3' },
									{ label: '4', value: '4' },
								] }
								onChange={ chooseColumns }
							/>
						</div>

						<span className="catalogist-map-toolbar__total">
							{ sprintf(
								/* translators: %s: credit hours */
								__( 'Total: %s credits', 'catalogist' ),
								total
							) }
						</span>
						<Button
							variant="secondary"
							onClick={ () => setOpen( false ) }
						>
							{ __( 'Done', 'catalogist' ) }
						</Button>
					</div>

					<p className="catalogist-map-hint">
						{ __(
							'Changes are saved when you save or update.',
							'catalogist'
						) }
					</p>

					{ removal && (
						<Notice
							status="warning"
							isDismissible={ false }
							actions={ [
								{
									label: __(
										'Remove these entries',
										'catalogist'
									),
									onClick: confirmRemoval,
									variant: 'primary',
								},
								{
									label: __( 'Cancel', 'catalogist' ),
									onClick: () => setRemoval( null ),
								},
							] }
						>
							<p>
								{ sprintf(
									/* translators: 1: number of entries, 2: template name */
									__(
										'Remove %1$d entries that came from “%2$s”? This includes any changes you made to them.',
										'catalogist'
									),
									removal.entries.length,
									removal.title
								) }
							</p>
							<ul className="catalogist-map-removal-list">
								{ removal.entries.map( ( entry ) => (
									<li
										key={ `${ entry.term }-${ entry.position }` }
									>
										{ ops.termName(
											map[ entry.term ],
											entry.term
										) }
										: { entryName( entry.item ) }
									</li>
								) ) }
							</ul>
						</Notice>
					) }

					{ applied && (
						<Notice
							status="success"
							onRemove={ () => setApplied( '' ) }
						>
							{ applied }
						</Notice>
					) }

					{ issues.length > 0 && (
						<div className="catalogist-map-issues">
							{ issues.map( ( issue, i ) => (
								<Notice
									key={ i }
									status={ issue.status }
									isDismissible={ false }
								>
									{ issue.message }
								</Notice>
							) ) }
						</div>
					) }

					{ map.length ? (
						<div
							className="catalogist-map-grid"
							style={
								columns
									? {
											gridTemplateColumns: `repeat(${ columns }, minmax(0, 1fr))`,
									  }
									: undefined
							}
						>
							{ map.map( ( term, index ) => (
								<TermColumn
									key={ index }
									map={ map }
									term={ term }
									index={ index }
									termCount={ map.length }
									byId={ byId }
									courseList={ list }
									pathways={ pathways }
									actions={ actions }
								/>
							) ) }
						</div>
					) : (
						<p className="catalogist-map-empty">
							{ allowTemplates && templates?.length
								? __(
										'Add a term, or apply a template to start from your general education requirements.',
										'catalogist'
								  )
								: __(
										'Add a term to start building the map.',
										'catalogist'
								  ) }
						</p>
					) }
				</Modal>
			) }
		</VStack>
	);
}
