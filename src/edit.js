import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { TextControl, Panel, PanelBody, Button, Spinner } from '@wordpress/components';
import { useBlockProps, store as blockEditorStore } from '@wordpress/block-editor';  // Added useBlockProps here
import { createBlock } from '@wordpress/blocks';
import { useDispatch, useSelect } from '@wordpress/data';
import './editor.scss';

export default function Edit({ clientId }) {
    const [prompt, setPrompt] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const blockProps = useBlockProps();

    // Get required functions from dispatch and select
    const { insertBlock } = useDispatch(blockEditorStore);
    const { getBlockRootClientId, getBlockIndex } = useSelect(select => ({
        getBlockRootClientId: select(blockEditorStore).getBlockRootClientId,
        getBlockIndex: select(blockEditorStore).getBlockIndex,  // Added getBlockIndex
    }), []);

    const generateImage = async () => {
        if (!prompt) return;
        setIsLoading(true);

        try {
            // Get API response (you need to add your API call here)
            const response = await fetch('/wp-json/replicate-api/v1/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': wpApiSettings.nonce
                },
                body: JSON.stringify({
                    input: {
                        prompt: prompt
                    },
                    model: 'stability-ai/sdxl'
                })
            });

            const data = await response.json();
            console.log('Response data:', data);

            if (data.output && data.output[0]) {
                console.log('Image URL:', data.output[0]);

                // Get current block's parent and position
                const parentClientId = getBlockRootClientId(clientId);
                const currentIndex = getBlockIndex(clientId, parentClientId);

                // Create the image block
                const imageBlock = createBlock('core/image', {
                    url: data.output[0],
                    caption: prompt,
                    alt: prompt
                });

                console.log('Created block:', imageBlock);

                // Insert block after current position
                insertBlock(
                    imageBlock,
                    currentIndex + 1,
                    parentClientId
                );

                // Clear the prompt
                setPrompt('');
            }
        } catch (error) {
            console.error('Error generating image:', error);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <div {...blockProps}>
            <Panel>
                <PanelBody
                    title={__('Image Generator', 'replicate')}
                    initialOpen={true}
                >
                    <TextControl
                        label={__('Enter your prompt', 'replicate')}
                        value={prompt}
                        onChange={(value) => setPrompt(value)}
                        placeholder={__('Describe the image you want to generate...', 'replicate')}
                        className="components-base-control__input"
                    />
                    <Button
                        isPrimary
                        onClick={generateImage}
                        disabled={isLoading || !prompt}
                    >
                        {isLoading ? __('Generating...', 'replicate') : __('Generate Image', 'replicate')}
                    </Button>
                </PanelBody>
            </Panel>

            {isLoading && (
                <div className="generation-loading">
                    <Spinner />
                    <p>{__('Generating your image...', 'replicate')}</p>
                </div>
            )}
        </div>
    );
}
