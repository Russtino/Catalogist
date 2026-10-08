/**
 * One term (semester) in the program map editor: courses and choice slots.
 */
import { __, sprintf } from '@wordpress/i18n';
import { useMemo, useState } from '@wordpress/element';
import {
	Button,
	ComboboxControl,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import {
	chevronUp,
	chevronDown,
	chevronLeft,
	chevronRight,
	closeSmall,
	pencil,
	plus,
	trash,
} from '@wordpress/icons';

import CoursePicker from '../course-picker';
import CreditsControl from '../credits-control';
import {
	termName,
	termCredits,
	formatCredits,
	itemPathway,
	placedCourseIds,
} from './map-utils';

function ChoiceEditor( { item, onChange, onDone } ) {
	// Single-course options and groups are edited separately, then stored
	// together: singles first, then groups.
	const singles = item.optionSets
		.filter( ( set ) => ! set.group )
		.map( ( set ) => set.courses[ 0 ] )
		.filter( Boolean );
	const groups = item.optionSets.filter( ( set ) => set.group );

	const save = ( nextSingles, nextGroups ) =>
		onChange( {
			optionSets: [
				...nextSingles.map( ( id ) => ( {
					courses: [ id ],
					group: false,
				} ) ),
				...nextGroups,
			],
		} );

	return (
		<div className="catalogist-map-choice-editor">
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Label', 'catalogist' ) }
				help={ __(
					'For example, Social science elective',
					'catalogist'
				) }
				value={ item.label }
				onChange={ ( label ) => onChange( { label } ) }
			/>
			<CreditsControl
				label={ __( 'Credits', 'catalogist' ) }
				value={ item.credits }
				onChange={ ( credits ) => onChange( { credits } ) }
			/>
			<CoursePicker
				label={ __( 'Single-course options', 'catalogist' ) }
				help={ __(
					'Students pick one of these. Leave everything empty for an open choice, like “Any approved humanities course.”',
					'catalogist'
				) }
				value={ singles }
				onChange={ ( next ) => save( next, groups ) }
			/>

			<div className="catalogist-map-choice-editor__groups">
				<p className="catalogist-map-choice-editor__heading">
					{ __( 'Course groups', 'catalogist' ) }
				</p>
				<p className="catalogist-field-hint">
					{ __(
						'For options taken together, such as a lecture and its lab. Each group counts as one option.',
						'catalogist'
					) }
				</p>
				{ groups.map( ( group, i ) => (
					<div
						key={ i }
						className="catalogist-map-choice-editor__group"
					>
						<CoursePicker
							label={ sprintf(
								/* translators: %d: group number */
								__( 'Group %d', 'catalogist' ),
								i + 1
							) }
							value={ group.courses }
							onChange={ ( courses ) =>
								save(
									singles,
									groups.map( ( g, j ) =>
										j === i ? { courses, group: true } : g
									)
								)
							}
						/>
						<Button
							size="small"
							icon={ trash }
							isDestructive
							label={ sprintf(
								/* translators: %d: group number */
								__( 'Remove group %d', 'catalogist' ),
								i + 1
							) }
							onClick={ () =>
								save(
									singles,
									groups.filter( ( g, j ) => j !== i )
								)
							}
						/>
					</div>
				) ) }
				<Button
					variant="link"
					icon={ plus }
					onClick={ () =>
						save( singles, [
							...groups,
							{ courses: [], group: true },
						] )
					}
				>
					{ __( 'Add a course group', 'catalogist' ) }
				</Button>
			</div>

			<Button variant="secondary" size="compact" onClick={ onDone }>
				{ __( 'Done', 'catalogist' ) }
			</Button>
		</div>
	);
}

export default function TermColumn( {
	map,
	term,
	index,
	termCount,
	byId,
	courseList,
	pathways = [],
	actions,
} ) {
	const [ editing, setEditing ] = useState( null ); // position of the choice being edited
	const [ target, setTarget ] = useState( '' ); // pathway new entries go to ('' = shared)

	const pathwayIds = pathways.map( ( p ) => p.id );
	const hasPathways = pathways.length > 0;
	const addTo = pathwayIds.includes( target ) ? target : '';

	// A course is offered unless it's already shared, or already in the target pathway.
	const addOptions = useMemo( () => {
		const placed = placedCourseIds( map, addTo, pathwayIds );
		return courseList
			.filter( ( course ) => ! placed.has( course.id ) )
			.map( ( course ) => ( {
				value: String( course.id ),
				label: course.label,
			} ) );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ map, courseList, addTo, pathwayIds.join() ] );

	// Display groups: shared entries, then each pathway's, keeping each item's real position.
	const groups = [ { id: '', name: '' }, ...pathways ]
		.map( ( group ) => ( {
			...group,
			positions: term.items
				.map( ( item, position ) =>
					itemPathway( item, pathwayIds ) === group.id ? position : -1
				)
				.filter( ( position ) => position !== -1 ),
		} ) )
		.filter( ( group ) => group.id === '' || group.positions.length );

	const pathwayOptions = [
		{ label: __( 'Shared', 'catalogist' ), value: '' },
		...pathways.map( ( p ) => ( {
			label: p.name || __( '(unnamed pathway)', 'catalogist' ),
			value: p.id,
		} ) ),
	];

	const name = termName( term, index );
	const isFirst = index === 0;
	const isLast = index === termCount - 1;

	const itemName = ( item ) => {
		if ( item.type === 'choice' ) {
			return item.label || __( 'Elective', 'catalogist' );
		}
		return (
			byId.get( item.id )?.label ??
			/* translators: %d: post ID */
			sprintf( __( '#%d (course not found)', 'catalogist' ), item.id )
		);
	};

	return (
		<section className="catalogist-map-term" aria-label={ name }>
			<div className="catalogist-map-term__header">
				<TextControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Term name', 'catalogist' ) }
					value={ term.label }
					onChange={ ( label ) => actions.renameTerm( index, label ) }
				/>
				<Button
					size="small"
					icon={ chevronLeft }
					/* translators: %s: term name */
					label={ sprintf(
						__( 'Move %s earlier', 'catalogist' ),
						name
					) }
					disabled={ isFirst }
					accessibleWhenDisabled
					onClick={ () => actions.moveTerm( index, -1 ) }
				/>
				<Button
					size="small"
					icon={ chevronRight }
					/* translators: %s: term name */
					label={ sprintf(
						__( 'Move %s later', 'catalogist' ),
						name
					) }
					disabled={ isLast }
					accessibleWhenDisabled
					onClick={ () => actions.moveTerm( index, 1 ) }
				/>
				<Button
					size="small"
					icon={ trash }
					isDestructive
					/* translators: %s: term name */
					label={ sprintf( __( 'Remove %s', 'catalogist' ), name ) }
					onClick={ () => actions.removeTerm( index ) }
				/>
			</div>

			{ term.items.length ? (
				groups.map( ( group ) => (
					<div
						key={ group.id || 'shared' }
						className="catalogist-map-term__group"
					>
						{ hasPathways && (
							<h4 className="catalogist-map-term__group-heading">
								{ group.id
									? sprintf(
											/* translators: %s: pathway name */
											__( '%s pathway', 'catalogist' ),
											group.name ||
												__( '(unnamed)', 'catalogist' )
									  )
									: __( 'Shared', 'catalogist' ) }
							</h4>
						) }
						{ group.positions.length ? (
							<ul className="catalogist-map-term__courses">
								{ group.positions.map(
									( position, groupIndex ) => {
										const item = term.items[ position ];

										const label = itemName( item );
										const isChoice = item.type === 'choice';
										const course = isChoice
											? null
											: byId.get( item.id );
										const credits = isChoice
											? item.credits
											: course?.credits;

										return (
											<li
												// Choice slots have no ID, so position keeps keys stable enough here.
												key={
													isChoice
														? `choice-${ position }`
														: item.id
												}
												className={
													'catalogist-map-course' +
													( isChoice
														? ' catalogist-map-course--choice'
														: '' ) +
													( ! isChoice && ! course
														? ' catalogist-map-course--missing'
														: '' )
												}
											>
												<div className="catalogist-map-course__name">
													{ isChoice ? (
														<>
															<span className="catalogist-map-course__badge">
																{ __(
																	'Choice',
																	'catalogist'
																) }
															</span>
															{ label }
															<span className="catalogist-map-course__options">
																{ item
																	.optionSets
																	.length
																	? item.optionSets
																			.map(
																				(
																					set
																				) =>
																					set.courses
																						.map(
																							(
																								id
																							) =>
																								byId.get(
																									id
																								)
																									?.code ||
																								byId.get(
																									id
																								)
																									?.label ||
																								`#${ id }`
																						)
																						.join(
																							' + '
																						)
																			)
																			.filter(
																				Boolean
																			)
																			.join(
																				', '
																			)
																	: __(
																			'Open choice',
																			'catalogist'
																	  ) }
															</span>
														</>
													) : (
														<>
															{ course?.code && (
																<span className="catalogist-map-course__code">
																	{
																		course.code
																	}
																</span>
															) }
															{ course
																? course.title
																: label }
														</>
													) }
												</div>
												<div className="catalogist-map-course__credits">
													{ credits !== undefined
														? sprintf(
																/* translators: %s: credit hours */
																__(
																	'%s cr',
																	'catalogist'
																),
																formatCredits(
																	Number(
																		credits
																	) || 0
																)
														  )
														: '' }
												</div>
												<div className="catalogist-map-course__actions">
													<Button
														size="small"
														icon={ chevronUp }
														/* translators: %s: course or choice */
														label={ sprintf(
															__(
																'Move %s up',
																'catalogist'
															),
															label
														) }
														disabled={
															groupIndex === 0
														}
														accessibleWhenDisabled
														onClick={ () =>
															actions.moveItemTo(
																index,
																position,
																group.positions[
																	groupIndex -
																		1
																]
															)
														}
													/>
													<Button
														size="small"
														icon={ chevronDown }
														/* translators: %s: course or choice */
														label={ sprintf(
															__(
																'Move %s down',
																'catalogist'
															),
															label
														) }
														disabled={
															groupIndex ===
															group.positions
																.length -
																1
														}
														accessibleWhenDisabled
														onClick={ () =>
															actions.moveItemTo(
																index,
																position,
																group.positions[
																	groupIndex +
																		1
																]
															)
														}
													/>
													<Button
														size="small"
														icon={ chevronLeft }
														/* translators: %s: course or choice */
														label={ sprintf(
															__(
																'Move %s to the previous term',
																'catalogist'
															),
															label
														) }
														disabled={ isFirst }
														accessibleWhenDisabled
														onClick={ () =>
															actions.shiftItem(
																index,
																position,
																-1
															)
														}
													/>
													<Button
														size="small"
														icon={ chevronRight }
														/* translators: %s: course or choice */
														label={ sprintf(
															__(
																'Move %s to the next term',
																'catalogist'
															),
															label
														) }
														disabled={ isLast }
														accessibleWhenDisabled
														onClick={ () =>
															actions.shiftItem(
																index,
																position,
																1
															)
														}
													/>
													{ isChoice && (
														<Button
															size="small"
															icon={ pencil }
															/* translators: %s: choice label */
															label={ sprintf(
																__(
																	'Edit %s',
																	'catalogist'
																),
																label
															) }
															isPressed={
																editing ===
																position
															}
															onClick={ () =>
																setEditing(
																	editing ===
																		position
																		? null
																		: position
																)
															}
														/>
													) }
													<Button
														size="small"
														icon={ closeSmall }
														isDestructive
														/* translators: %s: course or choice */
														label={ sprintf(
															__(
																'Remove %s',
																'catalogist'
															),
															label
														) }
														onClick={ () => {
															setEditing( null );
															actions.removeItem(
																index,
																position
															);
														} }
													/>
												</div>
												{ hasPathways && (
													<div className="catalogist-map-course__pathway">
														<SelectControl
															__nextHasNoMarginBottom
															size="small"
															/* translators: %s: course or choice */
															label={ sprintf(
																__(
																	'Pathway for %s',
																	'catalogist'
																),
																label
															) }
															hideLabelFromVision
															value={ itemPathway(
																item,
																pathwayIds
															) }
															options={
																pathwayOptions
															}
															onChange={ (
																pathway
															) =>
																actions.updateItem(
																	index,
																	position,
																	{ pathway }
																)
															}
														/>
													</div>
												) }
												{ isChoice &&
													editing === position && (
														<ChoiceEditor
															item={ item }
															onChange={ (
																patch
															) =>
																actions.updateItem(
																	index,
																	position,
																	patch
																)
															}
															onDone={ () =>
																setEditing(
																	null
																)
															}
														/>
													) }
											</li>
										);
									}
								) }
							</ul>
						) : (
							<p className="catalogist-map-empty">
								{ __( 'None', 'catalogist' ) }
							</p>
						) }
					</div>
				) )
			) : (
				<p className="catalogist-map-empty">
					{ __( 'No courses yet.', 'catalogist' ) }
				</p>
			) }

			{ hasPathways && (
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Add to', 'catalogist' ) }
					value={ addTo }
					options={ pathwayOptions }
					onChange={ setTarget }
				/>
			) }
			<ComboboxControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Add a course', 'catalogist' ) }
				options={ addOptions }
				value={ null }
				onChange={ ( value ) =>
					value &&
					actions.addCourse( index, parseInt( value, 10 ), addTo )
				}
			/>
			<Button
				variant="link"
				icon={ plus }
				className="catalogist-map-term__add-choice"
				onClick={ () => {
					actions.addChoice( index, addTo );
					setEditing( term.items.length ); // Open the new slot for editing.
				} }
			>
				{ __( 'Add a choice slot', 'catalogist' ) }
			</Button>

			<p className="catalogist-map-term__total">
				{ hasPathways &&
				term.items.some( ( item ) => itemPathway( item, pathwayIds ) )
					? pathways
							.map( ( p ) =>
								sprintf(
									/* translators: 1: pathway name, 2: credit hours */
									__( '%1$s: %2$s cr', 'catalogist' ),
									p.name,
									formatCredits(
										termCredits(
											term,
											byId,
											p.id,
											pathwayIds
										)
									)
								)
							)
							.join( ' · ' )
					: sprintf(
							/* translators: %s: credit hours */
							__( '%s credits', 'catalogist' ),
							formatCredits( termCredits( term, byId ) )
					  ) }
			</p>
		</section>
	);
}
