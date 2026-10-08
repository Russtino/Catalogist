/**
 * A credential's pathways: named routes through its map, such as Coding and
 * Networking. Map entries are tagged with a pathway in the map editor.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { plus, trash } from '@wordpress/icons';

const newId = () => 'pw' + Math.random().toString( 36 ).slice( 2, 8 );

/**
 * @param {Object}   props
 * @param {Array}    props.value    [ { id, name, description } ]
 * @param {Function} props.onChange Receives ( pathways, removedId ).
 */
export default function PathwaysEditor( { value = [], onChange } ) {
	const update = ( id, patch ) =>
		onChange(
			value.map( ( p ) => ( p.id === id ? { ...p, ...patch } : p ) )
		);

	return (
		<details
			className="catalogist-pathways-editor"
			open={ value.length > 0 }
		>
			<summary>
				{ value.length
					? sprintf(
							/* translators: %d: number of pathways */
							__( 'Pathways (%d)', 'catalogist' ),
							value.length
					  )
					: __( 'Pathways (optional)', 'catalogist' ) }
			</summary>
			<VStack spacing={ 3 }>
				<p className="catalogist-field-hint">
					{ __(
						'For credentials with routes students choose between, such as Coding and Networking. In the map editor, each course or choice can then be marked as shared or as part of one pathway.',
						'catalogist'
					) }
				</p>
				{ value.map( ( pathway, i ) => (
					<div
						key={ pathway.id }
						className="catalogist-pathways-editor__item"
					>
						<HStack alignment="bottom">
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ sprintf(
									/* translators: %d: pathway number */
									__( 'Pathway %d name', 'catalogist' ),
									i + 1
								) }
								value={ pathway.name }
								onChange={ ( name ) =>
									update( pathway.id, { name } )
								}
							/>
							<Button
								icon={ trash }
								isDestructive
								label={ sprintf(
									/* translators: %s: pathway name */
									__( 'Remove the %s pathway', 'catalogist' ),
									pathway.name || i + 1
								) }
								onClick={ () =>
									onChange(
										value.filter(
											( p ) => p.id !== pathway.id
										),
										pathway.id
									)
								}
							/>
						</HStack>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __(
								'Description (optional)',
								'catalogist'
							) }
							value={ pathway.description ?? '' }
							onChange={ ( description ) =>
								update( pathway.id, { description } )
							}
						/>
					</div>
				) ) }
				<Button
					variant="secondary"
					icon={ plus }
					onClick={ () =>
						onChange( [
							...value,
							{ id: newId(), name: '', description: '' },
						] )
					}
				>
					{ __( 'Add pathway', 'catalogist' ) }
				</Button>
				{ value.length > 0 && (
					<p className="catalogist-field-hint">
						{ __(
							'Removing a pathway makes its courses shared; nothing is deleted from the map.',
							'catalogist'
						) }
					</p>
				) }
			</VStack>
		</details>
	);
}
