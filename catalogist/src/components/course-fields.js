import { __ } from '@wordpress/i18n';
import {
	TextControl,
	TextareaControl,
	__experimentalVStack as VStack,
} from '@wordpress/components';

import useMeta from '../hooks/use-meta';
import CreditsControl from './credits-control';
import PrerequisitePicker from './prerequisite-picker';

export default function CourseFields() {
	const [ meta, update ] = useMeta();

	return (
		<VStack spacing={ 4 }>
			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Course code', 'catalogist' ) }
				help={ __( 'For example, CPD 153', 'catalogist' ) }
				value={ meta._catalogist_course_code ?? '' }
				onChange={ ( v ) => update( '_catalogist_course_code', v ) }
			/>

			<CreditsControl
				label={ __( 'Credit hours', 'catalogist' ) }
				value={ meta._catalogist_credits }
				onChange={ ( v ) => update( '_catalogist_credits', v ) }
			/>

			<details className="catalogist-hours">
				<summary>{ __( 'Lecture, lab and contact hours (optional)', 'catalogist' ) }</summary>
				<VStack spacing={ 3 }>
					<CreditsControl
						label={ __( 'Lecture hours', 'catalogist' ) }
						value={ meta._catalogist_lecture_hours }
						onChange={ ( v ) => update( '_catalogist_lecture_hours', v ) }
					/>
					<CreditsControl
						label={ __( 'Lab hours', 'catalogist' ) }
						value={ meta._catalogist_lab_hours }
						onChange={ ( v ) => update( '_catalogist_lab_hours', v ) }
					/>
					<CreditsControl
						label={ __( 'Contact hours', 'catalogist' ) }
						value={ meta._catalogist_contact_hours }
						onChange={ ( v ) => update( '_catalogist_contact_hours', v ) }
					/>
				</VStack>
			</details>

			<PrerequisitePicker
				value={ meta._catalogist_prerequisites ?? [] }
				onChange={ ( ids ) => update( '_catalogist_prerequisites', ids ) }
			/>

			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Course description', 'catalogist' ) }
				help={ __( 'Shown by the Course Description block. Leave a blank line between paragraphs.', 'catalogist' ) }
				rows={ 8 }
				value={ meta._catalogist_description ?? '' }
				onChange={ ( v ) => update( '_catalogist_description', v ) }
			/>

			<TextControl
				__nextHasNoMarginBottom
				__next40pxDefaultSize
				label={ __( 'Prerequisite notes', 'catalogist' ) }
				help={ __(
					'Anything the list above can’t express, such as “or instructor permission.”',
					'catalogist'
				) }
				value={ meta._catalogist_prerequisite_notes ?? '' }
				onChange={ ( v ) => update( '_catalogist_prerequisite_notes', v ) }
			/>
		</VStack>
	);
}
