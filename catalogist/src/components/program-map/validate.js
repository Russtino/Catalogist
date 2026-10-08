/**
 * Checks a program map for scheduling problems.
 * Returns [ { status: 'error' | 'warning' | 'info', message } ].
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	termName,
	mapCredits,
	formatCredits,
	inPathway,
	itemPathway,
	choiceCourseIds,
} from './map-utils';

/**
 * @param {Array} pathways [ { id, name } ]. Each pathway's route (shared entries
 *                         plus its own) is checked separately.
 */
export default function validateMap( map, byId, declaredTotal, pathways = [] ) {
	const ids = pathways.map( ( p ) => p.id );
	const routes = ids.length ? pathways : [ { id: '', name: '' } ];
	const found = new Map(); // status + message => { status, message, routes: Set of names }

	// The same problem can show up on several routes; it's reported once, and
	// prefixed with pathway names unless it affects every pathway.
	const add = ( status, message, route ) => {
		const key = status + message;
		if ( ! found.has( key ) ) {
			found.set( key, { status, message, routes: new Set() } );
		}
		found.get( key ).routes.add( route.name );
	};

	routes.forEach( ( route ) => checkRoute( route ) );

	// Unpublished courses are left off the public page, which is easy to miss.
	const unpublished = new Set();
	map.forEach( ( term ) =>
		term.items.forEach( ( item ) =>
			( item.type === 'choice'
				? choiceCourseIds( item )
				: [ item.id ]
			).forEach( ( id ) => {
				const course = byId.get( id );
				if ( course && ! course.published ) {
					unpublished.add( course.label );
				}
			} )
		)
	);
	if ( unpublished.size ) {
		add(
			'warning',
			sprintf(
				/* translators: %s: comma-separated course names */
				__(
					'Not published yet, so not shown on the public page: %s. Publish these courses to include them.',
					'catalogist'
				),
				[ ...unpublished ].join( ', ' )
			),
			{ name: '' }
		);
	}

	function checkRoute( route ) {
		const inRoute = ( item ) => inPathway( item, route.id, ids );
		const termOf = new Map(); // course ID => first term it's placed in

		map.forEach( ( term, i ) =>
			term.items.filter( inRoute ).forEach( ( item ) => {
				if ( item.type === 'course' && ! termOf.has( item.id ) ) {
					termOf.set( item.id, i );
				}
			} )
		);

		map.forEach( ( term, i ) => {
			term.items.filter( inRoute ).forEach( ( item ) => {
				// Problems with a shared entry aren't pathway-specific.
				const scope = itemPathway( item, ids ) ? route : { name: '' };
				if ( item.type === 'choice' ) {
					const missing = choiceCourseIds( item ).filter(
						( id ) => ! byId.has( id )
					);
					if ( missing.length ) {
						add(
							'error',
							sprintf(
								/* translators: 1: choice label, 2: term name */
								__(
									'The “%1$s” choice in %2$s lists a course that no longer exists. Edit the choice to remove it.',
									'catalogist'
								),
								item.label,
								termName( term, i )
							),
							scope
						);
					}
					return; // Prerequisites of options depend on the student's pick, so they aren't checked.
				}

				const course = byId.get( item.id );

				if ( ! course ) {
					add(
						'error',
						sprintf(
							/* translators: 1: term name, 2: post ID */
							__(
								'%1$s includes a course that no longer exists (#%2$d). Remove it from the map.',
								'catalogist'
							),
							termName( term, i ),
							item.id
						),
						scope
					);
					return;
				}

				course.prerequisites.forEach( ( prereqId ) => {
					const prereqLabel =
						byId.get( prereqId )?.label ?? `#${ prereqId }`;

					// A missing prerequisite depends on the route, so it's reported per pathway.
					if ( ! termOf.has( prereqId ) ) {
						add(
							'info',
							sprintf(
								/* translators: 1: course, 2: prerequisite course */
								__(
									'%1$s requires %2$s, which isn’t in this program map.',
									'catalogist'
								),
								course.label,
								prereqLabel
							),
							route
						);
					} else if ( termOf.get( prereqId ) >= i ) {
						add(
							'warning',
							sprintf(
								/* translators: 1: course, 2: term name, 3: prerequisite course */
								__(
									'%1$s is in %2$s, but its prerequisite %3$s isn’t scheduled in an earlier term.',
									'catalogist'
								),
								course.label,
								termName( term, i ),
								prereqLabel
							),
							route
						);
					}
				} );
			} );
		} );

		const total = mapCredits( map, byId, route.id, ids );
		if ( declaredTotal > 0 && map.length && total !== declaredTotal ) {
			add(
				'warning',
				sprintf(
					/* translators: 1: credits in map, 2: credential total credits */
					__(
						'The map adds up to %1$s credits, but the credential’s total is set to %2$s.',
						'catalogist'
					),
					formatCredits( total ),
					formatCredits( declaredTotal )
				),
				route
			);
		}
	}

	const issues = [];
	found.forEach( ( { status, message, routes: names } ) => {
		const specific = [ ...names ].filter( Boolean );
		if (
			names.has( '' ) ||
			specific.length === 0 ||
			specific.length === routes.length
		) {
			issues.push( { status, message } );
			return;
		}
		issues.push( {
			status,
			message: sprintf(
				/* translators: 1: pathway name(s), 2: message */
				__( '%1$s pathway: %2$s', 'catalogist' ),
				specific.join( ', ' ),
				message
			),
		} );
	} );

	return issues;
}
