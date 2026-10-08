/**
 * Turns spreadsheet rows plus a column mapping into an import plan:
 * what will be created, updated, skipped, or rejected, and why.
 */
import { __, sprintf } from '@wordpress/i18n';
import { codeKey, parseCredits, parsePrerequisites, prefixOf } from './utils';

/**
 * @param {Object} choices For codes that appear on more than one row, the
 *                         user's pick: code key => row number, or 0 to import
 *                         none of them. Codes without a choice block the import.
 */
export default function buildPlan(
	table,
	mapping,
	existing,
	options,
	choices = {},
	prefixDepartments = {} // "CPD" => { id, name }
) {
	const get = ( row, field ) =>
		field in mapping ? row.cells[ mapping[ field ] ] ?? '' : undefined;

	const rowsFor = new Map(); // code key => row numbers using that code

	const plan = table.rows.map( ( row ) => {
		const subject = get( row, 'subject' );
		const number = get( row, 'code' ) || '';
		const code = ( subject ? `${ subject } ${ number }` : number ).trim();
		const item = {
			rowNumber: row.rowNumber,
			code,
			key: codeKey( code ),
			title: get( row, 'title' ) || '',
			messages: [],
			payload: null,
		};

		if ( ! item.key || ! item.title ) {
			item.action = 'error';
			item.messages.push( __( 'Missing course code or title.', 'catalogist' ) );
			return item;
		}

		const payload = { row: row.rowNumber, code, title: item.title };

		if ( 'credits' in mapping ) {
			const raw = get( row, 'credits' );
			const credits = parseCredits( raw );
			if ( credits === null ) {
				item.messages.push(
					/* translators: %s: cell value */
					sprintf( __( 'Credits “%s” isn’t a number, so credits weren’t set.', 'catalogist' ), raw )
				);
			} else if ( credits !== undefined ) {
				payload.credits = credits;
				item.credits = credits;
			}
		}

		// Optional hours: same number rules as credits.
		[
			[ 'lectureHours', __( 'Lecture hours', 'catalogist' ) ],
			[ 'labHours', __( 'Lab hours', 'catalogist' ) ],
			[ 'contactHours', __( 'Contact hours', 'catalogist' ) ],
		].forEach( ( [ field, label ] ) => {
			if ( ! ( field in mapping ) ) {
				return;
			}
			const raw = get( row, field );
			const hours = parseCredits( raw );
			if ( hours === null ) {
				item.messages.push(
					/* translators: 1: field name, 2: cell value */
					sprintf( __( '%1$s “%2$s” isn’t a number, so it wasn’t set.', 'catalogist' ), label, raw )
				);
			} else if ( hours !== undefined ) {
				payload[ field ] = hours;
			}
		} );

		// Empty cells leave existing values alone.
		const description = get( row, 'description' );
		if ( description ) {
			payload.description = description;
		}

		// A department named in the spreadsheet wins; otherwise use the course prefix.
		const department = get( row, 'department' );
		if ( department ) {
			payload.department = department;
			item.department = department;
		} else {
			const fromPrefix = prefixDepartments[ prefixOf( code ) ];
			if ( fromPrefix ) {
				payload.departmentId = fromPrefix.id;
				item.department = fromPrefix.name;
				item.departmentFromPrefix = true;
			}
		}

		// Prerequisites are set in a second pass, once every course exists.
		if ( 'prerequisites' in mapping || 'notes' in mapping ) {
			const raw = get( row, 'prerequisites' ) || '';
			const { codes, keepText } = parsePrerequisites( raw );
			const notes = [ get( row, 'notes' ), keepText ? raw : '' ]
				.filter( Boolean )
				.join( ' ' );

			item.prereqs = { notes };
			if ( 'prerequisites' in mapping ) {
				item.prereqs.codes = codes;
			}
		}

		const match = existing[ item.key ];
		if ( match ) {
			item.existingId = match.id;
			item.action = options.existing === 'skip' ? 'skip' : 'update';
			if ( item.action === 'skip' ) {
				item.messages.push( __( 'Already exists.', 'catalogist' ) );
			}
		} else {
			item.action = 'create';
		}

		item.payload = payload;
		rowsFor.set( item.key, [ ...( rowsFor.get( item.key ) || [] ), row.rowNumber ] );
		return item;
	} );

	// Codes on more than one row: apply the user's choice, or wait for one.
	plan.forEach( ( item ) => {
		const rows = rowsFor.get( item.key );
		if ( item.action === 'error' || ! rows || rows.length < 2 ) {
			return;
		}

		item.duplicateRows = rows;
		const choice = choices[ item.key ];

		if ( choice === undefined || ( choice !== 0 && ! rows.includes( choice ) ) ) {
			item.action = 'duplicate';
			item.messages = [ __( 'Same code as other rows. Choose which to import.', 'catalogist' ) ];
		} else if ( choice === 0 ) {
			item.action = 'skip';
			item.messages = [ __( 'Duplicate code; you chose not to import it.', 'catalogist' ) ];
		} else if ( choice !== item.rowNumber ) {
			item.action = 'skip';
			item.messages = [
				/* translators: %d: spreadsheet row number */
				sprintf( __( 'Duplicate code; you chose row %d.', 'catalogist' ), choice ),
			];
		}
		// The chosen row keeps its create/update action and messages.
	} );

	return plan;
}

/**
 * Groups of rows sharing a code, for the duplicate picker:
 * [ { key, code, items: [ plan items ] } ], in order of first appearance.
 */
export function duplicateGroups( plan ) {
	const groups = new Map();

	plan.forEach( ( item ) => {
		if ( item.duplicateRows ) {
			if ( ! groups.has( item.key ) ) {
				groups.set( item.key, { key: item.key, code: item.code, items: [] } );
			}
			groups.get( item.key ).items.push( item );
		}
	} );

	return [ ...groups.values() ];
}
