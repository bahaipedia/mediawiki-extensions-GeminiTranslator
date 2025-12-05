<?php

namespace MediaWiki\Extension\GeminiTranslator\Hook;

use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Output\OutputPage;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Context\RequestContext;

class VirtualPageDisplay implements BeforeInitializeHook {

	private RevisionLookup $revisionLookup;
	private TitleFactory $titleFactory;

	public function __construct( RevisionLookup $revisionLookup, TitleFactory $titleFactory ) {
		$this->revisionLookup = $revisionLookup;
		$this->titleFactory = $titleFactory;
	}

	public function onBeforeInitialize( $title, $article, $output, $user, $request, $mediaWiki ): void {
		if ( $title->getNamespace() !== NS_MAIN ) {
			return;
		}
		if ( $title->exists() || !$title->isSubpage() ) {
			return;
		}

		$parts = explode( '/', $title->getText() );
		$langCode = end( $parts );
		$len = strlen( $langCode );
		$isValidLang = ( $len >= 2 && $len <= 3 ) || in_array( $langCode, [ 'zh-cn', 'zh-tw', 'pt-br' ] );
		
		if ( !$isValidLang ) {
			return;
		}

		$parentTitle = $title->getBaseTitle();
		if ( !$parentTitle || !$parentTitle->exists() ) {
			return;
		}

		$this->renderVirtualPage( $output, $parentTitle, $langCode, $title );
	}

	private function renderVirtualPage( OutputPage $output, Title $parent, string $lang, Title $fullTitle ): void {
		$output->setPageTitle( $fullTitle->getText() );
		$output->setArticleFlag( false );
		$output->addBodyClasses( 'gemini-virtual-page' );
		$output->addModules( [ 'ext.geminitranslator.bootstrap' ] );

		// 1. Get Parent Revision
		$rev = $this->revisionLookup->getRevisionByTitle( $parent );
		if ( !$rev ) { return; }

		// 2. Parse Lead Section (Section 0)
		// We do this server-side to give the "Instant" skeleton feel
		$content = $rev->getContent( 'main' );
		$section0 = $content ? $content->getSection( 0 ) : null;
		
		$skeletonHtml = '';
		if ( $section0 ) {
			$services = MediaWikiServices::getInstance();
			$parser = $services->getParser();
			$popts = ParserOptions::newFromContext( RequestContext::getMain() );
			
			$parseOut = $parser->parse( $section0->getText(), $parent, $popts, true );
			
			// Transform to Skeleton
			$builder = $services->getService( 'GeminiTranslator.SkeletonBuilder' );
			$skeletonHtml = $builder->createSkeleton( $parseOut->getText() );
		}

		// 3. Output HTML
		$html = '<div class="gemini-virtual-container">';
		$html .= '<div class="mw-message-box mw-message-box-notice">';
		$html .= '<strong>Translated Content:</strong> This page is a real-time translation of <a href="' . $parent->getLinkURL() . '">' . $parent->getText() . '</a>.';
		$html .= '</div>';
		
		// The Content Area
		$html .= '<div id="gemini-virtual-content" style="margin-top: 20px;">';
		// Inject the skeleton immediately!
		$html .= $skeletonHtml;
		$html .= '</div>';
		
		$html .= '</div>'; // End container

		$output->addHTML( $html );
		
		// Pass vars to JS for lazy loading the rest
		$output->addJsConfigVars( [
			'wgGeminiParentRevId' => $rev->getId(),
			'wgGeminiTargetLang' => $lang
		] );

		$request = $output->getRequest();
		$request->setVal( 'action', 'view' );
	}
}
