/**
 * Reads a CSV or Excel file into a grid of trimmed strings:
 * { grid: [ [ 'A1', 'B1' ], [ 'A2', 'B2' ] ], sheets, sheet }.
 * Parsing happens in the browser; the file is never uploaded.
 */
import Papa from 'papaparse';
import readExcelFile from 'read-excel-file/browser';

function cellText( value ) {
	if ( value === null || value === undefined ) {
		return '';
	}
	if ( value instanceof Date ) {
		return value.toISOString().slice( 0, 10 );
	}
	return String( value ).trim();
}

const toGrid = ( data ) => data.map( ( row ) => ( row || [] ).map( cellText ) );

export default async function readSpreadsheet( file, sheetName ) {
	const name = file.name.toLowerCase();

	if ( name.endsWith( '.xlsx' ) ) {
		const sheets = await readExcelFile( file );
		const chosen = sheets.find( ( s ) => s.sheet === sheetName ) || sheets[ 0 ];
		return {
			sheets: sheets.map( ( s ) => s.sheet ),
			sheet: chosen ? chosen.sheet : null,
			grid: toGrid( chosen ? chosen.data : [] ),
		};
	}

	if ( name.endsWith( '.csv' ) || name.endsWith( '.txt' ) ) {
		// Keep empty lines so row numbers match what the spreadsheet shows.
		const result = Papa.parse( await file.text(), { skipEmptyLines: false } );
		const grid = toGrid( result.data );
		while ( grid.length && grid[ grid.length - 1 ].every( ( cell ) => cell === '' ) ) {
			grid.pop();
		}
		return { sheets: [], sheet: null, grid };
	}

	throw new Error( 'unsupported' );
}
