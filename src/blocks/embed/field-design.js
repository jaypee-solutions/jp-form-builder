import { PanelColorSettings, useSettings } from '@wordpress/block-editor';
import { RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const COLOR_ATTRIBUTES = [
	'fieldBackground',
	'fieldBorderColor',
	'focusColor',
];

function colorSettingLabels() {
	return {
		fieldBackground: __( 'Field background', 'jp-form-builder' ),
		fieldBorderColor: __( 'Field border', 'jp-form-builder' ),
		focusColor: __( 'Focus and accent', 'jp-form-builder' ),
	};
}

// The attribute keeps a palette slug, so a theme's palette change reaches the Form, or a custom colour.
function useColorSettings( attributes, setAttributes ) {
	const [ custom, theme, defaults ] = useSettings(
		'color.palette.custom',
		'color.palette.theme',
		'color.palette.default'
	);
	const palette = [
		...( theme ?? [] ),
		...( custom ?? [] ),
		...( defaults ?? [] ),
	];
	const labels = colorSettingLabels();

	return COLOR_ATTRIBUTES.map( ( name ) => ( {
		label: labels[ name ],
		value:
			palette.find( ( color ) => color.slug === attributes[ name ] )
				?.color || attributes[ name ],
		onChange: ( color ) =>
			setAttributes( {
				[ name ]:
					palette.find( ( entry ) => entry.color === color )?.slug ??
					color ??
					'',
			} ),
		clearable: true,
	} ) );
}

function pixels( length ) {
	return length ? parseFloat( length ) : undefined;
}

function LengthControl( { label, value, max, onChange } ) {
	return (
		<RangeControl
			label={ label }
			value={ pixels( value ) }
			min={ 0 }
			max={ max }
			allowReset
			resetFallbackValue={ undefined }
			onChange={ ( pixelValue ) =>
				onChange( pixelValue === undefined ? '' : `${ pixelValue }px` )
			}
		/>
	);
}

/**
 * Inspector panel with the design settings of the Fields of an embedded Form.
 *
 * @param {Object}                    props               Component props.
 * @param {Object}                    props.attributes    Block attributes.
 * @param {(changes: Object) => void} props.setAttributes Block attribute setter.
 */
export function FieldDesignControls( { attributes, setAttributes } ) {
	const colorSettings = useColorSettings( attributes, setAttributes );

	return (
		<PanelColorSettings
			title={ __( 'Fields', 'jp-form-builder' ) }
			colorSettings={ colorSettings }
		>
			<LengthControl
				label={ __( 'Field border radius', 'jp-form-builder' ) }
				value={ attributes.fieldRadius }
				max={ 40 }
				onChange={ ( fieldRadius ) => setAttributes( { fieldRadius } ) }
			/>
			<LengthControl
				label={ __( 'Field border width', 'jp-form-builder' ) }
				value={ attributes.fieldBorderWidth }
				max={ 8 }
				onChange={ ( fieldBorderWidth ) =>
					setAttributes( { fieldBorderWidth } )
				}
			/>
			<p className="components-base-control__help">
				{ __(
					'Keep the focus color at least 3:1 against the background so keyboard users can see it.',
					'jp-form-builder'
				) }
			</p>
		</PanelColorSettings>
	);
}
