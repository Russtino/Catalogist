/**
 * Read and update the current post's registered meta.
 * Changes are saved when the user clicks Save/Update, like any other edit.
 */
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

export default function useMeta() {
	const { postType, postId } = useSelect(
		( select ) => ( {
			postType: select( editorStore ).getCurrentPostType(),
			postId: select( editorStore ).getCurrentPostId(),
		} ),
		[]
	);

	const [ meta, setMeta ] = useEntityProp(
		'postType',
		postType,
		'meta',
		postId
	);

	const update = ( key, value ) => setMeta( { ...meta, [ key ]: value } );

	return [ meta || {}, update ];
}
