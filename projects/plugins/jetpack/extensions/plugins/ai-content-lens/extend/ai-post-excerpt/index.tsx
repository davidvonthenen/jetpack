/**
 * External dependencies
 */
import { useAiSuggestions } from '@automattic/jetpack-ai-client';
import {
	isAtomicSite,
	isSimpleSite,
	useAnalytics,
} from '@automattic/jetpack-shared-extension-utils';
import { TextareaControl, ExternalLink, Button, Notice, BaseControl } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { store as editorStore, PostTypeSupportCheck } from '@wordpress/editor';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf, _n } from '@wordpress/i18n';
import { count } from '@wordpress/wordcount';
/**
 * Internal dependencies
 */
import QuotaExceededMessage from '../../../../blocks/ai-assistant/components/quota-exceeded-message';
import useAiFeature from '../../../../blocks/ai-assistant/hooks/use-ai-feature';
import { isBetaExtension } from '../../../../editor';
import { AiExcerptControl } from '../../components/ai-excerpt-control';
/**
 * Types and constants
 */
import type { LanguageProp } from '../../../../blocks/ai-assistant/components/i18n-dropdown-control';
import type { ToneProp } from '../../../../blocks/ai-assistant/components/tone-dropdown-control';
import type { AiModelTypeProp, PromptProp } from '@automattic/jetpack-ai-client';
import type * as EditorSelectors from '@wordpress/editor/store/selectors';

import './style.scss';

type ContentLensMessageContextProps = {
	type: 'ai-content-lens';
	contentType: 'post-excerpt';
	postId: number;
	words?: number;
	request?: string;
	content?: string;
	language?: LanguageProp;
	tone?: ToneProp;
	model?: AiModelTypeProp;
};

function AiPostExcerpt() {
	const { excerpt, postId } = useSelect( select => {
		const { getEditedPostAttribute, getCurrentPostId } = select(
			editorStore
		) as typeof EditorSelectors;

		return {
			excerpt: getEditedPostAttribute( 'excerpt' ) ?? '',
			postId: getCurrentPostId() ?? 0,
		};
	}, [] );

	const { tracks } = useAnalytics();

	const { editPost } = useDispatch( 'core/editor' );

	const { dequeueAiAssistantFeatureAsyncRequest, increaseAiAssistantRequestsCount } =
		useDispatch( 'wordpress-com/plans' );

	// Post excerpt words number
	const [ excerptWordsNumber, setExcerptWordsNumber ] = useState( 50 );

	const [ reenable, setReenable ] = useState( false );
	const [ language, setLanguage ] = useState< LanguageProp >();
	const [ tone, setTone ] = useState< ToneProp >();
	const [ model, setModel ] = useState< AiModelTypeProp >( null );

	const { request, stopSuggestion, suggestion, requestingState, error, reset } = useAiSuggestions( {
		onDone: useCallback( () => {
			/*
			 * Increase the AI Suggestion counter.
			 * @todo: move this at store level.
			 */
			increaseAiAssistantRequestsCount();
		}, [ increaseAiAssistantRequestsCount ] ),
		onError: useCallback(
			suggestionError => {
				/*
				 * Incrses AI Suggestion counter
				 * only for valid errors.
				 * @todo: move this at store level.
				 */
				if (
					suggestionError.code === 'error_network' ||
					suggestionError.code === 'error_quota_exceeded'
				) {
					return;
				}

				// Increase the AI Suggestion counter.
				increaseAiAssistantRequestsCount();
			},
			[ increaseAiAssistantRequestsCount ]
		),
	} );

	// Cancel and reset AI suggestion when the component is unmounted
	useEffect( () => {
		return () => {
			stopSuggestion();
			reset();
		};
	}, [ stopSuggestion, reset ] );

	// Pick raw post content
	const postContent = useSelect( select => {
		const content = ( select( editorStore ) as typeof EditorSelectors ).getEditedPostContent();
		if ( ! content ) {
			return '';
		}

		const document = new window.DOMParser().parseFromString( content, 'text/html' );

		const documentRawText = document.body.textContent || document.body.innerText || '';

		// Keep only one break line (\n) between blocks.
		return documentRawText.replace( /\n{2,}/g, '\n' ).trim();
	}, [] );

	// Show custom prompt number of words
	const currentExcerpt = suggestion || excerpt;
	const numberOfWords = count( currentExcerpt, 'words' );
	const helpNumberOfWords = sprintf(
		// Translators: %1$s is the number of words in the excerpt.
		_n( '%1$s word', '%1$s words', numberOfWords, 'jetpack' ),
		numberOfWords
	);

	const isGenerateButtonDisabled =
		requestingState === 'requesting' ||
		requestingState === 'suggesting' ||
		( requestingState === 'done' && ! reenable );

	const isBusy = requestingState === 'requesting' || requestingState === 'suggesting';
	const isTextAreaDisabled = isBusy || requestingState === 'done';

	/**
	 * Request AI for a new excerpt.
	 *
	 * @return {void}
	 */
	function requestExcerpt(): void {
		// Enable Generate button
		setReenable( false );

		// Reset suggestion state
		reset();

		const messageContext: ContentLensMessageContextProps = {
			type: 'ai-content-lens',
			contentType: 'post-excerpt',
			postId,
			words: excerptWordsNumber,
			language,
			tone,
			content: `Post content:
${ postContent }
`,
		};

		const prompt: PromptProp = [
			{
				role: 'jetpack-ai',
				context: messageContext,
			},
		];

		/*
		 * Always dequeue/cancel the AI Assistant feature async request,
		 * in case there is one pending,
		 * when performing a new AI suggestion request.
		 */
		dequeueAiAssistantFeatureAsyncRequest();

		request( prompt, { feature: 'jetpack-ai-content-lens', model } );
		tracks.recordEvent( 'jetpack_ai_assistant_block_generate', {
			feature: 'jetpack-ai-content-lens',
		} );
	}

	function setExcerpt() {
		editPost( { excerpt: suggestion } );
		tracks.recordEvent( 'jetpack_ai_assistant_block_accept', {
			feature: 'jetpack-ai-content-lens',
		} );
		reset();
	}

	function discardExcerpt() {
		editPost( { excerpt: excerpt } );
		tracks.recordEvent( 'jetpack_ai_assistant_block_discard', {
			feature: 'jetpack-ai-content-lens',
		} );
		reset();
	}

	const { requireUpgrade, isOverLimit } = useAiFeature();

	// Set the docs link depending on the site type
	const docsLink =
		isAtomicSite() || isSimpleSite()
			? __( 'https://wordpress.com/support/excerpts/', 'jetpack' )
			: __( 'https://jetpack.com/support/create-better-post-excerpts-with-ai/', 'jetpack' );

	return (
		<div className="jetpack-ai-post-excerpt">
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Write an excerpt (optional)', 'jetpack' ) }
				onChange={ value => editPost( { excerpt: value } ) }
				help={ numberOfWords ? helpNumberOfWords : null }
				value={ currentExcerpt }
				disabled={ isTextAreaDisabled }
			/>

			<ExternalLink href={ docsLink }>
				{ __( 'Learn more about manual excerpts', 'jetpack' ) }
			</ExternalLink>

			<div className="jetpack-generated-excerpt__ai-container">
				{ error?.code && error.code !== 'error_quota_exceeded' && (
					<Notice
						status={ error.severity }
						isDismissible={ false }
						className="jetpack-ai-assistant__error"
					>
						{ error.message }
					</Notice>
				) }

				{ isOverLimit && <QuotaExceededMessage placement="excerpt-panel" /> }

				<AiExcerptControl
					words={ excerptWordsNumber }
					onWordsNumberChange={ wordsNumber => {
						setExcerptWordsNumber( wordsNumber );
						setReenable( true );
					} }
					language={ language }
					onLanguageChange={ newLang => {
						setLanguage( newLang );
						setReenable( true );
					} }
					tone={ tone }
					onToneChange={ newTone => {
						setTone( newTone );
						setReenable( true );
					} }
					model={ model }
					onModelChange={ newModel => {
						setModel( newModel );
						setReenable( true );
					} }
					disabled={ isBusy || requireUpgrade }
				/>

				<BaseControl
					help={
						! postContent?.length ? __( 'Add content to generate an excerpt.', 'jetpack' ) : null
					}
					__nextHasNoMarginBottom={ true }
				>
					<div className="jetpack-generated-excerpt__generate-buttons-container">
						<Button
							onClick={ discardExcerpt }
							variant="secondary"
							isDestructive
							disabled={ requestingState !== 'done' || requireUpgrade }
						>
							{ __( 'Discard', 'jetpack' ) }
						</Button>
						<Button
							onClick={ setExcerpt }
							variant="secondary"
							disabled={ requestingState !== 'done' || requireUpgrade }
						>
							{ __( 'Accept', 'jetpack' ) }
						</Button>
						<Button
							onClick={ requestExcerpt }
							variant="secondary"
							isBusy={ isBusy }
							disabled={ isGenerateButtonDisabled || requireUpgrade || ! postContent }
						>
							{ __( 'Generate', 'jetpack' ) }
						</Button>
					</div>
				</BaseControl>
			</div>
		</div>
	);
}

export const PluginDocumentSettingPanelAiExcerpt = () => {
	const isExcerptUsedAsDescription = useSelect( select => {
		const { getCurrentPostType } = select( editorStore ) as typeof EditorSelectors;
		const postType = getCurrentPostType();
		const isTemplateOrTemplatePart = postType === 'wp_template' || postType === 'wp_template_part';
		const isPattern = postType === 'wp_block';
		return isTemplateOrTemplatePart || isPattern;
	}, [] );
	if ( isExcerptUsedAsDescription ) {
		return null;
	}
	return (
		<PostTypeSupportCheck supportKeys="excerpt">
			<PluginDocumentSettingPanel
				className={ isBetaExtension( 'ai-content-lens' ) ? 'is-beta-extension inset-shadow' : '' }
				name="ai-content-lens-plugin"
				title={ __( 'Excerpt', 'jetpack' ) }
			>
				<AiPostExcerpt />
			</PluginDocumentSettingPanel>
		</PostTypeSupportCheck>
	);
};
