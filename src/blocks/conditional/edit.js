import {
	InnerBlocks,
	store as blockEditorStore,
	useBlockProps,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { isConditionComplete } from '../../shared/condition-rules';
import { collectConditionSources } from '../../shared/form-fields';
import { ConditionPanel } from './condition-panel';
import { describeCondition } from './condition-text';

// A group holds Fields, layout blocks and pass-through blocks, never another group.
const ALLOWED_BLOCKS = [
	'jp-form-builder/name',
	'jp-form-builder/email',
	'jp-form-builder/phone',
	'jp-form-builder/address',
	'jp-form-builder/postcode',
	'jp-form-builder/country',
	'jp-form-builder/text',
	'jp-form-builder/select',
	'jp-form-builder/radio',
	'jp-form-builder/checkbox',
	'jp-form-builder/textarea',
	'jp-form-builder/consent',
	'core/paragraph',
	'core/heading',
	'core/columns',
	'core/group',
];

export default function Edit( { clientId, attributes, setAttributes } ) {
	const { formBlocks, hasFields } = useSelect(
		( select ) => {
			const { getBlockCount, getBlockParentsByBlockName, getBlocks } =
				select( blockEditorStore );
			const [ formClientId ] = getBlockParentsByBlockName(
				clientId,
				'jp-form-builder/form'
			);
			return {
				formBlocks: getBlocks( formClientId ),
				hasFields: getBlockCount( clientId ) > 0,
			};
		},
		[ clientId ]
	);
	const sources = useMemo(
		() => collectConditionSources( formBlocks, clientId ),
		[ formBlocks, clientId ]
	);
	const source = sources.find(
		( candidate ) => candidate.key === attributes.sourceField
	);
	const isComplete = isConditionComplete( attributes, source );

	const blockProps = useBlockProps( {
		className: 'jp-form-builder__conditional',
	} );
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'jp-form-builder__fields' },
		{
			allowedBlocks: ALLOWED_BLOCKS,
			templateLock: false,
			renderAppender: hasFields
				? undefined
				: InnerBlocks.ButtonBlockAppender,
		}
	);

	return (
		<>
			<ConditionPanel
				attributes={ attributes }
				setAttributes={ setAttributes }
				sources={ sources }
				source={ source }
			/>
			<div { ...blockProps }>
				<p
					className={ `jp-form-builder__condition-chip${
						isComplete ? '' : ' is-incomplete'
					}` }
				>
					{ isComplete
						? describeCondition( attributes, source )
						: __( 'Condition incomplete', 'jp-form-builder' ) }
				</p>
				{ ! hasFields && (
					<p className="jp-form-builder__conditional-hint">
						{ __(
							'Add Fields here. They are shown only while the Condition holds.',
							'jp-form-builder'
						) }
					</p>
				) }
				<div { ...innerBlocksProps } />
			</div>
		</>
	);
}
