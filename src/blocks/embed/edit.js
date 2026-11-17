import {
	InspectorControls,
	store as blockEditorStore,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Disabled,
	PanelBody,
	Placeholder,
	SelectControl,
	Spinner,
} from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';
import { addQueryArgs } from '@wordpress/url';
import { getEditorData } from '../../shared';
import DemoForm from './demo-form';
import { FieldDesignControls } from './field-design';

// null while loading, false when the request failed, else the published Forms.
function useForms( enabled ) {
	const [ forms, setForms ] = useState( null );

	useEffect( () => {
		if ( ! enabled ) {
			return undefined;
		}
		let current = true;
		apiFetch( { path: '/jp-form-builder/v1/forms' } )
			.then( ( result ) => current && setForms( result ) )
			.catch( () => current && setForms( false ) );
		return () => {
			current = false;
		};
	}, [ enabled ] );

	return forms;
}

// The design settings that change the markup; the wrapper's own block supports are already on blockProps.
const DESIGN_ATTRIBUTES = [
	'fieldBackground',
	'fieldBorderColor',
	'focusColor',
	'fieldRadius',
	'fieldBorderWidth',
];

function previewAttributes( attributes ) {
	const preview = { formId: attributes.formId };
	DESIGN_ATTRIBUTES.forEach( ( name ) => {
		if ( attributes[ name ] ) {
			preview[ name ] = attributes[ name ];
		}
	} );
	const blockGap = attributes.style?.spacing?.blockGap;
	if ( blockGap ) {
		preview.style = { spacing: { blockGap } };
	}
	return preview;
}

export default function Edit( { attributes, setAttributes } ) {
	const { formId } = attributes;
	const isPreview = useSelect(
		( select ) => select( blockEditorStore ).getSettings().isPreviewMode,
		[]
	);
	const forms = useForms( ! isPreview );
	const { canEditForms, editFormUrl } = getEditorData( 'embed' );
	const blockProps = useBlockProps();

	const options = [
		{ value: '0', label: __( 'Choose a Form…', 'jp-form-builder' ) },
		...( forms || [] ).map( ( form ) => ( {
			value: String( form.id ),
			label: form.title || __( '(no title)', 'jp-form-builder' ),
		} ) ),
	];
	const chosen = ( forms || [] ).find( ( form ) => form.id === formId );
	const picker = (
		<SelectControl
			label={ __( 'Form', 'jp-form-builder' ) }
			value={ String( chosen ? formId : 0 ) }
			options={ options }
			onChange={ ( value ) =>
				setAttributes( { formId: Number( value ) } )
			}
		/>
	);

	const editLink =
		canEditForms && chosen ? (
			<a
				href={ addQueryArgs( editFormUrl, {
					post: chosen.id,
					action: 'edit',
				} ) }
			>
				{ __( 'Edit form', 'jp-form-builder' ) }
			</a>
		) : null;

	let content;
	if ( isPreview ) {
		content = <DemoForm />;
	} else if ( forms === null ) {
		content = (
			<Placeholder label={ __( 'Form', 'jp-form-builder' ) }>
				<Spinner />
			</Placeholder>
		);
	} else if ( forms === false ) {
		content = (
			<Placeholder
				label={ __( 'Form', 'jp-form-builder' ) }
				instructions={ __(
					'The list of Forms could not be loaded.',
					'jp-form-builder'
				) }
			/>
		);
	} else if ( formId === 0 ) {
		content = (
			<Placeholder
				label={ __( 'Form', 'jp-form-builder' ) }
				instructions={
					forms.length > 0
						? __(
								'Choose the Form to show here.',
								'jp-form-builder'
							)
						: __(
								'There is no published Form yet. Create and publish one under Forms first.',
								'jp-form-builder'
							)
				}
			>
				{ forms.length > 0 && picker }
			</Placeholder>
		);
	} else if ( ! chosen ) {
		content = (
			<Placeholder
				label={ __( 'Form', 'jp-form-builder' ) }
				instructions={ __(
					'The chosen Form no longer exists or is not published. Choose another one or remove this block.',
					'jp-form-builder'
				) }
			>
				{ forms.length > 0 && picker }
			</Placeholder>
		);
	} else {
		content = (
			<Disabled>
				{ /* Only the Form: the block wrapper's alignment and margin are already on blockProps. */ }
				<ServerSideRender
					block="jp-form-builder/embed"
					attributes={ previewAttributes( attributes ) }
				/>
			</Disabled>
		);
	}

	return (
		<>
			<InspectorControls group="styles">
				<FieldDesignControls
					attributes={ attributes }
					setAttributes={ setAttributes }
				/>
			</InspectorControls>
			<InspectorControls>
				<PanelBody title={ __( 'Form', 'jp-form-builder' ) }>
					{ forms && forms.length > 0 && picker }
					{ editLink }
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				{ content }
				{ editLink && (
					<p className="jp-form-builder__edit-link">{ editLink }</p>
				) }
			</div>
		</>
	);
}
