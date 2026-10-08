/**
 * The credential types ticked on the post being edited, as [ { id, name } ].
 * Returns null while terms are loading. Updates live as boxes are ticked.
 *
 * useAllCredentialTerms() returns every credential type, with the ticked
 * IDs and a setter, for the checkboxes at the top of the Credentials panel.
 */
import { useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';
import { decodeEntities } from '@wordpress/html-entities';

const TERM_QUERY = { per_page: -1, orderby: 'name', order: 'asc' };

export default function useCredentialTerms() {
	const { selectedIds, terms } = useSelect(
		( select ) => ( {
			selectedIds:
				select( editorStore ).getEditedPostAttribute( 'catalogist_credential' ),
			terms: select( coreStore ).getEntityRecords(
				'taxonomy',
				'catalogist_credential',
				TERM_QUERY
			),
		} ),
		[]
	);

	return useMemo( () => {
		if ( ! terms ) {
			return null;
		}
		const ids = selectedIds || [];
		return terms
			.filter( ( term ) => ids.includes( term.id ) )
			.map( ( term ) => ( { id: term.id, name: decodeEntities( term.name ) } ) );
	}, [ selectedIds, terms ] );
}

export function useAllCredentialTerms() {
	const { selectedIds, terms } = useSelect(
		( select ) => ( {
			selectedIds:
				select( editorStore ).getEditedPostAttribute( 'catalogist_credential' ),
			terms: select( coreStore ).getEntityRecords(
				'taxonomy',
				'catalogist_credential',
				TERM_QUERY
			),
		} ),
		[]
	);
	const { editPost } = useDispatch( editorStore );

	const all = useMemo(
		() =>
			terms
				? terms.map( ( term ) => ( {
						id: term.id,
						name: decodeEntities( term.name ),
				  } ) )
				: null,
		[ terms ]
	);

	const setSelected = ( ids ) => editPost( { catalogist_credential: ids } );

	return { all, selectedIds: selectedIds || [], setSelected };
}
