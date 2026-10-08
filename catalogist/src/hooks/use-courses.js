/**
 * Loads every course (including drafts) once and shares it between
 * the prerequisite picker and the program map editor.
 */
import { __ } from '@wordpress/i18n';
import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';

const COURSE_QUERY = {
	per_page: -1,
	status: 'publish,draft,pending,future,private',
	orderby: 'title',
	order: 'asc',
	_fields: 'id,title,meta,status',
};

export default function useCourses() {
	const records = useSelect(
		( select ) =>
			select( coreStore ).getEntityRecords(
				'postType',
				'catalogist_course',
				COURSE_QUERY
			),
		[]
	);

	return useMemo( () => {
		if ( ! records ) {
			return { isLoading: true, list: [], byId: new Map() };
		}

		const list = records.map( ( record ) => {
			const title =
				decodeEntities(
					record.title?.raw ?? record.title?.rendered ?? ''
				) || __( '(untitled)', 'catalogist' );
			const code = record.meta?._catalogist_course_code || '';

			return {
				id: record.id,
				title,
				code,
				credits: Number( record.meta?._catalogist_credits ) || 0,
				prerequisites: record.meta?._catalogist_prerequisites || [],
				published: record.status === 'publish',
				label: code ? `${ code } — ${ title }` : title,
			};
		} );

		// Sort by code/title with natural number ordering (CPD 90 before CPD 153).
		list.sort( ( a, b ) =>
			a.label.localeCompare( b.label, undefined, { numeric: true } )
		);

		// Labels must be unique so they can be mapped back to IDs.
		const seen = new Set();
		list.forEach( ( course ) => {
			if ( seen.has( course.label ) ) {
				course.label = `${ course.label } (#${ course.id })`;
			}
			seen.add( course.label );
		} );

		return {
			isLoading: false,
			list,
			byId: new Map( list.map( ( course ) => [ course.id, course ] ) ),
		};
	}, [ records ] );
}
