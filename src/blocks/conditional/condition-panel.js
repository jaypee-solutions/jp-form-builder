import { InspectorControls } from '@wordpress/block-editor';
import {
	CheckboxControl,
	PanelBody,
	SelectControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import {
	conditionForSource,
	operatorsForType,
	takesMultipleValues,
	takesSingleValue,
	valuesForOperator,
} from '../../shared/condition-rules';
import { operatorLabel } from './condition-text';

function sourceOptions( sources, sourceField, source ) {
	const options = sources.map( ( candidate ) => ( {
		label: `${ candidate.label } (${ candidate.key })`,
		value: candidate.key,
	} ) );
	// A Field that was removed, renamed or moved behind the group stays visible, so the author sees what is wrong.
	const stale =
		sourceField && ! source
			? [
					{
						label: sprintf(
							/* translators: %s: Field key. */
							__( '%s (not available)', 'jp-form-builder' ),
							sourceField
						),
						value: sourceField,
						disabled: true,
					},
				]
			: [];
	return [
		{ label: __( 'Choose a Field', 'jp-form-builder' ), value: '' },
		...stale,
		...options,
	];
}

function ValueControls( { condition, source, setCondition } ) {
	const { operator, values } = condition;
	const choices = source.options.map( ( option ) => ( {
		label: ( option.label ?? '' ).trim() || option.value,
		value: option.value,
	} ) );

	if ( takesSingleValue( operator ) ) {
		return (
			<SelectControl
				label={ __( 'Value', 'jp-form-builder' ) }
				value={ values[ 0 ] ?? '' }
				options={ [
					{
						label: __( 'Choose an option', 'jp-form-builder' ),
						value: '',
					},
					...choices,
				] }
				onChange={ ( value ) =>
					setCondition( { values: value ? [ value ] : [] } )
				}
			/>
		);
	}
	if ( ! takesMultipleValues( operator ) ) {
		return null;
	}
	return (
		<fieldset className="jp-form-builder__condition-values">
			<legend>{ __( 'Values', 'jp-form-builder' ) }</legend>
			{ choices.map( ( choice ) => (
				<CheckboxControl
					key={ choice.value }
					label={ choice.label }
					checked={ values.includes( choice.value ) }
					onChange={ ( isChecked ) =>
						setCondition( {
							values: isChecked
								? [ ...values, choice.value ]
								: values.filter(
										( value ) => value !== choice.value
									),
						} )
					}
				/>
			) ) }
		</fieldset>
	);
}

/**
 * Inspector panel that edits a Conditional group's Condition.
 *
 * @param {Object}                    props               Component props.
 * @param {Object}                    props.attributes    The group's attributes.
 * @param {(changes: Object) => void} props.setAttributes Block attribute setter.
 * @param {Object[]}                  props.sources       The Fields before the group.
 * @param {Object|undefined}          props.source        The Field the Condition looks at, when it is one of `sources`.
 */
export function ConditionPanel( {
	attributes,
	setAttributes,
	sources,
	source,
} ) {
	const { sourceField, operator, values } = attributes;
	const operators = operatorsForType( source?.type );

	const chooseOperator = ( next ) =>
		setAttributes( {
			operator: next,
			values: valuesForOperator( next, values, source.options ),
		} );

	return (
		<InspectorControls>
			<PanelBody title={ __( 'Condition', 'jp-form-builder' ) }>
				{ sources.length === 0 && (
					<p>
						{ __(
							'Add Fields before this group to use one of them in a Condition.',
							'jp-form-builder'
						) }
					</p>
				) }
				<SelectControl
					label={ __( 'Source Field', 'jp-form-builder' ) }
					value={ sourceField }
					options={ sourceOptions( sources, sourceField, source ) }
					onChange={ ( key ) =>
						setAttributes(
							conditionForSource(
								sources.find(
									( { key: candidate } ) => candidate === key
								)
							)
						)
					}
					help={
						sourceField && ! source
							? __(
									'This Field was removed or comes after this group. Choose another one.',
									'jp-form-builder'
								)
							: __(
									'Only Fields before this group can be used.',
									'jp-form-builder'
								)
					}
				/>
				{ source && (
					<>
						<SelectControl
							label={ __( 'Operator', 'jp-form-builder' ) }
							value={ operator }
							options={ operators.map( ( value ) => ( {
								label: operatorLabel( value ),
								value,
							} ) ) }
							onChange={ chooseOperator }
						/>
						<ValueControls
							condition={ attributes }
							source={ source }
							setCondition={ setAttributes }
						/>
					</>
				) }
			</PanelBody>
		</InspectorControls>
	);
}
