/**
 * Pure helpers for the course import. No WordPress imports, so they can be
 * unit-tested directly in Node.
 */

// Fields a spreadsheet column can be mapped to. Header synonyms are compared
// after normalizing (lowercase, punctuation collapsed to spaces).
export const FIELD_SYNONYMS = {
	code: [ 'course code', 'code', 'course number', 'course #', 'course no', 'course id', 'course num', 'number', 'course' ],
	// Registrar exports often split "CPD 153" into Subject + Number columns.
	subject: [ 'subject', 'subject code', 'subj', 'prefix', 'course prefix' ],
	title: [ 'title', 'course title', 'name', 'course name' ],
	credits: [ 'credits', 'credit hours', 'credit hrs', 'cr hrs', 'cr', 'credit', 'hours', 'units', 'credit hour' ],
	lectureHours: [ 'lecture hours', 'lecture hrs', 'lecture', 'lec hrs', 'lec' ],
	labHours: [ 'lab hours', 'lab hrs', 'lab', 'laboratory hours' ],
	contactHours: [ 'contact hours', 'contact hrs', 'clock hours', 'total contact hours' ],
	description: [ 'description', 'course description', 'desc', 'catalog description' ],
	prerequisites: [ 'prerequisites', 'prerequisite', 'prereqs', 'prereq', 'pre requisites', 'pre requisite' ],
	notes: [ 'prerequisite notes', 'prereq notes', 'notes', 'requirements' ],
	department: [ 'department', 'dept', 'division', 'program area', 'discipline' ],
};

export const normalizeHeader = ( header ) =>
	String( header )
		.toLowerCase()
		.replace( /[^a-z0-9#]+/g, ' ' )
		.trim();

/**
 * Match columns to fields: the user's last mapping (by header name) first,
 * then header synonyms. Each column is used at most once.
 *
 * @return {Object} field => column index
 */
export function guessMapping( headers, saved = {} ) {
	const mapping = {};
	const used = new Set();
	const fields = Object.keys( FIELD_SYNONYMS );

	fields.forEach( ( field ) => {
		const index = saved[ field ] ? headers.indexOf( saved[ field ] ) : -1;
		if ( index !== -1 && ! used.has( index ) ) {
			mapping[ field ] = index;
			used.add( index );
		}
	} );

	fields.forEach( ( field ) => {
		if ( field in mapping ) {
			return;
		}
		const index = headers.findIndex(
			( header, i ) =>
				! used.has( i ) &&
				FIELD_SYNONYMS[ field ].includes( normalizeHeader( header ) )
		);
		if ( index !== -1 ) {
			mapping[ field ] = index;
			used.add( index );
		}
	} );

	return mapping;
}

// "CPD 153", "CPD-153" and "cpd153" are the same course.
export const codeKey = ( code ) =>
	String( code || '' )
		.toUpperCase()
		.replace( /[\s\-_.]+/g, '' );

// Letters followed by digits, e.g. CPD 153, MATH-110, BIO101L.
const CODE_PATTERN = /\b[A-Za-z]{2,5}[\s-]?\d{2,4}[A-Za-z]?\b/g;
const EMPTY_VALUES = /^\s*(none|n\/?a|-+|—)?\s*$/i;

/**
 * Pull course codes out of a prerequisite cell.
 * keepText is true when the cell says more than a list of codes
 * ("CPD 101 or instructor permission"), so the original wording should
 * also go into the prerequisite notes.
 */
export function parsePrerequisites( text ) {
	const value = String( text || '' );

	if ( EMPTY_VALUES.test( value ) ) {
		return { codes: [], keepText: false };
	}

	const seen = new Set();
	const codes = ( value.match( CODE_PATTERN ) || [] )
		.map( ( code ) => code.trim() )
		.filter( ( code ) => {
			const key = codeKey( code );
			if ( seen.has( key ) ) {
				return false;
			}
			seen.add( key );
			return true;
		} );

	const leftover = value
		.replace( CODE_PATTERN, ' ' )
		.replace( /[,;/&()+.]|\band\b/gi, ' ' )
		.trim();

	return { codes, keepText: leftover !== '' };
}

/**
 * @return {number|null|undefined} a number, null if the text isn't a number,
 *                                 or undefined if the cell is empty.
 */
export function parseCredits( text ) {
	const value = String( text || '' ).trim();
	if ( value === '' ) {
		return undefined;
	}
	return /^\d+(\.\d+)?$/.test( value ) ? parseFloat( value ) : null;
}

/**
 * Spreadsheet-style column name: 0 => A, 25 => Z, 26 => AA.
 */
export function columnLetter( index ) {
	let n = index + 1;
	let letters = '';
	while ( n > 0 ) {
		const remainder = ( n - 1 ) % 26;
		letters = String.fromCharCode( 65 + remainder ) + letters;
		n = Math.floor( ( n - 1 ) / 26 );
	}
	return letters;
}

/**
 * Guess the header row: the first row with at least two filled cells, which
 * skips title rows like "Fall 2026 Course Catalog" above the real headers.
 * Returns a 0-based index into the grid, or -1 if the sheet is empty.
 */
export function detectHeaderRow( grid ) {
	const filled = grid.map( ( row ) => row.filter( ( cell ) => cell !== '' ).length );
	const index = filled.findIndex( ( count ) => count >= 2 );
	return index !== -1 ? index : filled.findIndex( ( count ) => count > 0 );
}

/**
 * Split the grid into headers and course rows, given the header row.
 * Rows above the header row are ignored; empty header cells are named by
 * their column letter.
 */
export function tableFromGrid( grid, headerIndex ) {
	if ( headerIndex < 0 || headerIndex >= grid.length ) {
		return { headers: [], rows: [] };
	}

	const width = grid
		.slice( headerIndex )
		.reduce( ( max, row ) => Math.max( max, row.length ), 0 );

	const headers = Array.from(
		{ length: width },
		( _, i ) => ( grid[ headerIndex ][ i ] || '' ).replace( /^\ufeff/, '' ) || columnLetter( i )
	);

	const rows = grid
		.slice( headerIndex + 1 )
		.map( ( row, i ) => ( {
			rowNumber: headerIndex + i + 2, // Spreadsheet row number, for messages.
			cells: headers.map( ( _, c ) => row[ c ] ?? '' ),
		} ) )
		.filter( ( row ) => row.cells.some( ( cell ) => cell !== '' ) );

	return { headers, rows };
}

/**
 * The letters a course code starts with: "CPD 153" => "CPD". Matches
 * Catalogist_Departments::prefix_of() in PHP.
 */
export const prefixOf = ( code ) => {
	const match = String( code || '' ).match( /^\s*([A-Za-z]+)/ );
	return match ? match[ 1 ].toUpperCase() : '';
};
