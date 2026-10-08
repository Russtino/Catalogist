/**
 * Pure helpers for program maps. Every function returns a new map and never
 * mutates its input.
 *
 * A map is a list of terms: [ { label, items } ], where each item is either
 *   { type: 'course', id }                                  a specific course, or
 *   { type: 'choice', label, credits, optionSets }         a slot filled by one of several options. Each option
 *                                                           is one course or a group taken together (lecture + lab):
 *                                                           optionSets: [ { courses: [ 31 ], group: false },
 *                                                                         { courses: [ 41, 42 ], group: true } ].
 *                                                           No options = open-ended, e.g. "Any humanities course".
 * Either kind may carry `pathway` (a pathway id); '' means shared by every pathway.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Accept maps saved before 0.17, whose terms held a plain `courses` list.
 */
export const normalizeMap = ( map ) =>
	( Array.isArray( map ) ? map : [] ).map( ( term ) => ( {
		label: term.label ?? '',
		items: Array.isArray( term.items )
			? term.items.map( ( item ) =>
					item.type === 'choice'
						? {
								type: 'choice',
								label: item.label ?? '',
								credits: Number( item.credits ) || 0,
								// Pre-0.19 maps listed single courses in `options`.
								optionSets: Array.isArray( item.optionSets )
									? item.optionSets.map( ( set ) => ( {
											courses: [
												...( set.courses ?? [] ),
											],
											// Older data has no flag: more than one course means a group.
											group:
												set.group ??
												( set.courses ?? [] ).length >
													1,
									  } ) )
									: ( item.options ?? [] ).map( ( id ) => ( {
											courses: [ id ],
											group: false,
									  } ) ),
								pathway: item.pathway ?? '',
								template: item.template ?? 0,
						  }
						: {
								type: 'course',
								id: item.id,
								pathway: item.pathway ?? '',
								template: item.template ?? 0,
						  }
			  )
			: ( term.courses ?? [] ).map( ( id ) => ( {
					type: 'course',
					id,
					pathway: '',
			  } ) ),
	} ) );

export const termName = ( term, index ) =>
	term.label ||
	/* translators: %d: term number */
	sprintf( __( 'Term %d', 'catalogist' ), index + 1 );

const move = ( array, from, to ) => {
	const next = [ ...array ];
	const [ item ] = next.splice( from, 1 );
	next.splice( to, 0, item );
	return next;
};

const withTerm = ( map, index, fn ) =>
	map.map( ( term, i ) => ( i === index ? fn( term ) : term ) );

const withItems = ( map, index, fn ) =>
	withTerm( map, index, ( term ) => ( {
		...term,
		items: fn( term.items ),
	} ) );

export const newTerm = ( number ) => ( {
	/* translators: %d: semester number */
	label: sprintf( __( 'Semester %d', 'catalogist' ), number ),
	items: [],
} );

export const newChoice = ( pathway = '' ) => ( {
	type: 'choice',
	label: __( 'Elective', 'catalogist' ),
	credits: 3,
	optionSets: [],
	pathway,
} );

// ---- Terms ----------------------------------------------------------------

export const addTerm = ( map ) => [ ...map, newTerm( map.length + 1 ) ];

export const renameTerm = ( map, index, label ) =>
	withTerm( map, index, ( term ) => ( { ...term, label } ) );

export const removeTerm = ( map, index ) =>
	map.filter( ( _, i ) => i !== index );

export const moveTerm = ( map, index, offset ) =>
	move( map, index, index + offset );

// ---- Items ----------------------------------------------------------------

export const addCourse = ( map, index, id, pathway = '' ) =>
	withItems( map, index, ( items ) => [
		...items,
		{ type: 'course', id, pathway },
	] );

export const addChoice = ( map, index, pathway = '' ) =>
	withItems( map, index, ( items ) => [ ...items, newChoice( pathway ) ] );

export const updateItem = ( map, index, position, patch ) =>
	withItems( map, index, ( items ) =>
		items.map( ( item, i ) =>
			i === position ? { ...item, ...patch } : item
		)
	);

export const removeItem = ( map, index, position ) =>
	withItems( map, index, ( items ) =>
		items.filter( ( _, i ) => i !== position )
	);

export const moveItem = ( map, index, position, offset ) =>
	withItems( map, index, ( items ) =>
		move( items, position, position + offset )
	);

// Move an item to another position in the same term (used to reorder within a pathway group).
export const moveItemTo = ( map, index, from, to ) =>
	withItems( map, index, ( items ) => move( items, from, to ) );

// Pathways: an item's pathway counts only if the credential still has it.
export const itemPathway = ( item, pathwayIds ) =>
	item.pathway && pathwayIds.includes( item.pathway ) ? item.pathway : '';

// Does this item belong to the given pathway's route? ('' = no pathways, everything counts.)
export const inPathway = ( item, pathway, pathwayIds = [] ) => {
	const own = itemPathway( item, pathwayIds );
	return own === '' || pathway === '' || own === pathway;
};

// Move an item to the end of the previous or next term.
export const shiftItem = ( map, index, position, offset ) => {
	const item = map[ index ].items[ position ];
	const without = removeItem( map, index, position );
	return withItems( without, index + offset, ( items ) => [
		...items,
		item,
	] );
};

// ---- Lookups and totals ---------------------------------------------------

// Courses placed directly in the map (choice options don't count, so they can still be placed).
// With a target pathway, only shared entries and that pathway's own count as placed,
// so one course can appear in two pathways.
export const placedCourseIds = ( map, pathway = null, pathwayIds = [] ) =>
	new Set(
		map.flatMap( ( term ) =>
			term.items
				.filter( ( item ) => item.type === 'course' )
				.filter( ( item ) => {
					if ( pathway === null ) {
						return true;
					}
					const own = itemPathway( item, pathwayIds );
					return own === '' || own === pathway;
				} )
				.map( ( item ) => item.id )
		)
	);

// Every course a choice slot offers, across all its options.
export const choiceCourseIds = ( item ) =>
	item.optionSets.flatMap( ( set ) => set.courses );

export const itemCredits = ( item, byId ) =>
	item.type === 'choice'
		? Number( item.credits ) || 0
		: byId.get( item.id )?.credits || 0;

// Credits for one pathway's route (shared + its own), or everything when pathway is ''.
export const termCredits = ( term, byId, pathway = '', pathwayIds = [] ) =>
	term.items
		.filter( ( item ) => inPathway( item, pathway, pathwayIds ) )
		.reduce( ( sum, item ) => sum + itemCredits( item, byId ), 0 );

export const mapCredits = ( map, byId, pathway = '', pathwayIds = [] ) =>
	map.reduce(
		( sum, term ) => sum + termCredits( term, byId, pathway, pathwayIds ),
		0
	);

export const itemCount = ( map ) =>
	map.reduce( ( n, term ) => n + term.items.length, 0 );

export const formatCredits = ( n ) =>
	Number.isInteger( n ) ? String( n ) : n.toFixed( 1 );

/**
 * Copy a template's terms into a map, term by term: the template's first term
 * goes into the map's first term, and so on. Missing terms are created with the
 * template's labels. Courses already in the map, and choice slots with the same
 * label in the same term, are skipped. Added entries are tagged with the
 * template's ID so they can be removed later.
 */
export const applyTemplate = ( map, template, templateId = 0 ) => {
	const placed = placedCourseIds( map );
	const next = map.map( ( term ) => ( {
		...term,
		items: [ ...term.items ],
	} ) );

	normalizeMap( template ).forEach( ( templateTerm, i ) => {
		if ( ! next[ i ] ) {
			next[ i ] = {
				label: templateTerm.label || newTerm( i + 1 ).label,
				items: [],
			};
		}
		const term = next[ i ];

		templateTerm.items.forEach( ( item ) => {
			// Template entries arrive as shared; pathways can be assigned afterwards.
			if ( item.type === 'course' ) {
				if ( ! placed.has( item.id ) ) {
					term.items.push( {
						...item,
						pathway: '',
						template: templateId,
					} );
					placed.add( item.id );
				}
			} else if (
				! term.items.some(
					( existing ) =>
						existing.type === 'choice' &&
						existing.label.trim().toLowerCase() ===
							item.label.trim().toLowerCase()
				)
			) {
				term.items.push( {
					...item,
					optionSets: item.optionSets.map( ( set ) => ( {
						...set,
						courses: [ ...set.courses ],
					} ) ),
					pathway: '',
					template: templateId,
				} );
			}
		} );
	} );

	return next;
};

/**
 * Entries a template added to a map: those tagged with its ID, plus untagged
 * entries that match it (maps it was applied to before entries were tagged):
 * the same course, or a choice slot with the same label.
 *
 * @return {Array} [ { term, position, item } ]
 */
export const templateEntries = ( map, templateMap, templateId ) => {
	const template = normalizeMap( templateMap );
	const courseIds = new Set();
	const choiceLabels = new Set();

	template.forEach( ( term ) =>
		term.items.forEach( ( item ) => {
			if ( item.type === 'course' ) {
				courseIds.add( item.id );
			} else {
				choiceLabels.add( item.label.trim().toLowerCase() );
			}
		} )
	);

	const found = [];
	map.forEach( ( term, termIndex ) =>
		term.items.forEach( ( item, position ) => {
			const tagged = item.template === templateId;
			const matches =
				! item.template &&
				( item.type === 'course'
					? courseIds.has( item.id )
					: choiceLabels.has( item.label.trim().toLowerCase() ) );

			if ( tagged || matches ) {
				found.push( { term: termIndex, position, item } );
			}
		} )
	);
	return found;
};

/**
 * Remove entries by term and position.
 */
export const removeEntries = ( map, entries ) =>
	map.map( ( term, termIndex ) => ( {
		...term,
		items: term.items.filter(
			( item, position ) =>
				! entries.some(
					( entry ) =>
						entry.term === termIndex && entry.position === position
				)
		),
	} ) );
