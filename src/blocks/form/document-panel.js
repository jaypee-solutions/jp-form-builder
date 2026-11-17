import { store as blockEditorStore } from '@wordpress/block-editor';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';
import { FormSettings } from '../../shared';
import metadata from './block.json';

/**
 * Form settings in the document sidebar of a Form, so they are reachable
 * without selecting the `jp-form-builder/form` block.
 */
function FormSettingsPanel() {
	const { postType, formBlock } = useSelect(
		( select ) => ( {
			postType: select( editorStore ).getCurrentPostType(),
			formBlock: select( blockEditorStore )
				.getBlocks()
				.find( ( block ) => block.name === metadata.name ),
		} ),
		[]
	);
	const { updateBlockAttributes } = useDispatch( blockEditorStore );

	if ( postType !== 'jp_form' || ! formBlock ) {
		return null;
	}

	return (
		<PluginDocumentSettingPanel
			name="form-settings"
			title={ __( 'Form settings', 'jp-form-builder' ) }
		>
			<FormSettings
				clientId={ formBlock.clientId }
				attributes={ formBlock.attributes }
				setAttributes={ ( changes ) =>
					updateBlockAttributes( formBlock.clientId, changes )
				}
			/>
		</PluginDocumentSettingPanel>
	);
}

registerPlugin( 'jp-form-builder-form-settings', {
	render: FormSettingsPanel,
} );
