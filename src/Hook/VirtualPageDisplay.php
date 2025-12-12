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
		// LOGGING: Check every page load in Main namespace
		if ( $title->getNamespace() !== NS_MAIN ) { return; }
		
		$text = $title->getText();
		
		// 1. Check existence
		if ( $title->exists() ) { 
			// error_log("GEMINI HOOK: Skipping '$text' (Exists)");
			return; 
		}

		// 2. Parse URL
		$lastSlash = strrpos( $text, '/' );
		if ( $lastSlash === false ) { return; }

		$baseText = substr( $text, 0, $lastSlash );
		$langCode = substr( $text, $lastSlash + 1 );
		
		// 3. Validate Lang
		$len = strlen( $langCode );
		$isValidLang = ( $len >= 2 && $len <= 3 ) || in_array( strtolower($langCode), [ 'zh-cn', 'zh-tw', 'pt-br' ] );
		if ( !$isValidLang ) { return; }

		// 4. Check Parent
		$parentTitle = Title::newFromText( $baseText );
		if ( !$parentTitle || !$parentTitle->exists() ) {
			error_log("GEMINI HOOK: Skipping '$text' (Parent '$baseText' not found)");
			return;
		}

		// LOGGING: Match found
		error_log("GEMINI HOOK: Match! Hijacking '$text' -> Parent: '$baseText', Lang: '$langCode'");

		// --- HIJACK DISPLAY ---
		$this->renderVirtualPage( $output, $parentTitle, $langCode, $title, $user );
	}

	private function renderVirtualPage( OutputPage $output, Title $parent, string $lang, Title $fullTitle, $user ): void {
		$output->setPageTitle( $fullTitle->getText() );
		$output->setArticleFlag( false ); 
		$output->addBodyClasses( 'gemini-virtual-page' );
		
		// 1. STRICT ANONYMOUS CHECK
		if ( !$user->isNamed() ) {
			error_log("GEMINI HOOK: Blocked anonymous user.");
			$output->addWikiMsg( 'geminitranslator-login-required' );
			$request = $output->getRequest();
			$request->setVal( 'action', 'view' );
			return; 
		}

		// 2. Load Resources
		$output->addModules( [ 'ext.geminitranslator.bootstrap' ] );
		$output->addInlineStyle( '.noarticletext { display: none !important; }' );

		// 3. Get Parent Content
		$rev = $this->revisionLookup->getRevisionByTitle( $parent );
		if ( !$rev ) { return; }

		$content = $rev->getContent( 'main' );
		$skeletonHtml = '';
		
		if ( $content ) {
			error_log("GEMINI HOOK: Parsing parent content...");
			$services = MediaWikiServices::getInstance();
			$parser = $services->getParser();
			$popts = ParserOptions::newFromContext( RequestContext::getMain() );
			
			$parseOut = $parser->parse( $content->getText(), $parent, $popts, true );
			
			// LOGGING: Call SkeletonBuilder
			error_log("GEMINI HOOK: Content parsed. Calling SkeletonBuilder...");
			$builder = $services->getService( 'GeminiTranslator.SkeletonBuilder' );
			$skeletonHtml = $builder->createSkeleton( $parseOut->getText(), $lang );
			error_log("GEMINI HOOK: Skeleton generated. Length: " . strlen($skeletonHtml));
		}

		// 4. Output HTML
		$html = '<div class="gemini-virtual-container">';
		
		// Notice Banner
		$noticeMsg = \wfMessage( 'geminitranslator-viewing-live', $parent->getPrefixedText(), $parent->getText() )->parse();
		$html .= '<div class="mw-message-box mw-message-box-notice">' . $noticeMsg . '</div>';
		
		$html .= '<div id="gemini-virtual-content" style="margin-top: 20px;">';
		$html .= $skeletonHtml;
		$html .= '</div></div>';

		$output->addHTML( $html );
		
		$output->addJsConfigVars( [
			'wgGeminiTargetLang' => $lang
		] );

		$request = $output->getRequest();
		$request->setVal( 'action', 'view' );
		error_log("GEMINI HOOK: Output sent to browser.");
	}
}
