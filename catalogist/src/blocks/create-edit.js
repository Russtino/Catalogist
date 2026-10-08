/**
 * Shared editor behavior for the catalog blocks: a live server-rendered
 * preview when the block has a post to show, otherwise a placeholder.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';

export default function createEdit( { metadata, postType, emptyMessage, Controls } ) {
	return function Edit( { attributes, setAttributes, context } ) {
		const blockProps = useBlockProps();
		const hasPost = context.postType === postType && !! context.postId;

		return (
			<>
				{ Controls && (
					<InspectorControls>
						<Controls
							attributes={ attributes }
							setAttributes={ setAttributes }
						/>
					</InspectorControls>
				) }
				<div { ...blockProps }>
					{ hasPost ? (
						<ServerSideRender
							block={ metadata.name }
							attributes={ attributes }
							urlQueryArgs={ { post_id: context.postId } }
							EmptyResponsePlaceholder={ () => (
								<Placeholder
									label={ metadata.title }
									instructions={ emptyMessage }
								/>
							) }
						/>
					) : (
						<Placeholder
							label={ metadata.title }
							instructions={ __(
								'This block shows details for the current program or course. It fills in when used on a program or course page.',
								'catalogist'
							) }
						/>
					) }
				</div>
			</>
		);
	};
}
