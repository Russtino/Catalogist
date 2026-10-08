/**
 * One section per credential ticked on the program, each with its own
 * length, credit total, and program map.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	CheckboxControl,
	ExternalLink,
	TextControl,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

import useMeta from '../hooks/use-meta';
import useCredentialTerms, { useAllCredentialTerms } from '../hooks/use-credential-terms';
import CreditsControl from './credits-control';
import ProgramMap from './program-map';
import PathwaysEditor from './pathways-editor';
import { normalizeMap } from './program-map/map-utils';

const blankEntry = ( id ) => ( {
	credential: id,
	length: '',
	totalCredits: 0,
	description: '',
	pathways: [],
	map: [],
} );

/**
 * Checkboxes for every credential type, so choosing a program's credentials
 * and filling them in happen in one panel. (The separate Credential Types
 * panel is hidden on programs; see editor.js.)
 */
function CredentialChoices() {
	const { all, selectedIds, setSelected } = useAllCredentialTerms();

	if ( ! all ) {
		return <Spinner />;
	}

	const manageUrl = addQueryArgs( 'edit-tags.php', {
		taxonomy: 'catalogist_credential',
		post_type: 'catalogist_program',
	} );

	return (
		<fieldset className="catalogist-credential-choices">
			<legend className="catalogist-credential-choices__legend">
				{ __( 'Credentials this program offers', 'catalogist' ) }
			</legend>
			{ all.length ? (
				all.map( ( term ) => (
					<CheckboxControl
						key={ term.id }
						__nextHasNoMarginBottom
						label={ term.name }
						checked={ selectedIds.includes( term.id ) }
						onChange={ ( checked ) =>
							setSelected(
								checked
									? [ ...selectedIds, term.id ]
									: selectedIds.filter(
											( id ) => id !== term.id
									  )
							)
						}
					/>
				) )
			) : (
				<p className="catalogist-field-hint">
					{ __( 'No credential types yet.', 'catalogist' ) }
				</p>
			) }
			<ExternalLink href={ manageUrl }>
				{ __( 'Add or rename credential types', 'catalogist' ) }
			</ExternalLink>
		</fieldset>
	);
}

export default function CredentialsPanel() {
	return (
		<VStack spacing={ 6 }>
			<CredentialChoices />
			<CredentialSections />
		</VStack>
	);
}

function CredentialSections() {
	const [ meta, update ] = useMeta();
	const terms = useCredentialTerms();

	if ( ! terms ) {
		return null;
	}

	if ( ! terms.length ) {
		return (
			<p className="catalogist-field-hint">
				{ __(
					'Tick each credential the program offers. Each one gets its own length, credits, and program map here.',
					'catalogist'
				) }
			</p>
		);
	}

	// Entries for unticked credentials are kept, so re-ticking restores them.
	const entries = meta._catalogist_credentials ?? [];
	const entryFor = ( id ) =>
		entries.find( ( entry ) => entry.credential === id ) ??
		blankEntry( id );

	const setEntry = ( id, patch ) =>
		update(
			'_catalogist_credentials',
			entries
				.filter( ( entry ) => entry.credential !== id )
				.concat( { ...entryFor( id ), ...patch } )
		);

	// Removing a pathway turns its entries into shared ones.
	const setPathways = ( id, pathways, removedId ) => {
		const patch = { pathways };
		if ( removedId ) {
			patch.map = normalizeMap( entryFor( id ).map ).map( ( term ) => ( {
				...term,
				items: term.items.map( ( item ) =>
					item.pathway === removedId ? { ...item, pathway: '' } : item
				),
			} ) );
		}
		setEntry( id, patch );
	};

	return (
		<VStack spacing={ 6 }>
			{ terms.map( ( term ) => {
				const entry = entryFor( term.id );
				const copySources = terms
					.filter( ( other ) => other.id !== term.id )
					.map( ( other ) => ( {
						name: other.name,
						map: entryFor( other.id ).map,
					} ) )
					.filter( ( source ) => source.map.length );

				return (
					<section key={ term.id } className="catalogist-credential">
						<h3 className="catalogist-credential__title">
							{ term.name }
						</h3>
						<VStack spacing={ 4 }>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __( 'Length', 'catalogist' ) }
								help={ __(
									'For example, 4 semesters',
									'catalogist'
								) }
								value={ entry.length }
								onChange={ ( length ) =>
									setEntry( term.id, { length } )
								}
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'Short description',
									'catalogist'
								) }
								help={ __(
									'Optional. One sentence about this credential, shown with it on the program page.',
									'catalogist'
								) }
								value={ entry.description ?? '' }
								onChange={ ( description ) =>
									setEntry( term.id, { description } )
								}
							/>
							<CreditsControl
								label={ __(
									'Total credit hours',
									'catalogist'
								) }
								value={ entry.totalCredits }
								onChange={ ( totalCredits ) =>
									setEntry( term.id, { totalCredits } )
								}
							/>
							<PathwaysEditor
								value={ entry.pathways ?? [] }
								onChange={ ( pathways, removedId ) =>
									setPathways( term.id, pathways, removedId )
								}
							/>
							<ProgramMap
								title={ sprintf(
									/* translators: %s: credential type */
									__( 'Program map: %s', 'catalogist' ),
									term.name
								) }
								map={ entry.map }
								onChange={ ( map ) =>
									setEntry( term.id, { map } )
								}
								declaredTotal={
									Number( entry.totalCredits ) || 0
								}
								copySources={ copySources }
								pathways={ entry.pathways ?? [] }
							/>
						</VStack>
					</section>
				);
			} ) }
		</VStack>
	);
}
