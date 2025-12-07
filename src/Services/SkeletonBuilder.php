<?php

namespace MediaWiki\Extension\GeminiTranslator\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DOMText;
use MediaWiki\Extension\GeminiTranslator\PageTranslator;

class SkeletonBuilder {

	private PageTranslator $translator;
	private const IGNORE_TAGS = [ 'style', 'script', 'link', 'meta' ];

	public function __construct( PageTranslator $translator ) {
		$this->translator = $translator;
	}

	public function createSkeleton( string $html, string $targetLang ): string {
		if ( trim( $html ) === '' ) {
			return '';
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		// PHASE 1: Harvest all text nodes
		$textNodes = []; 
		$rawStrings = [];
		
		$this->harvestNodes( $dom->documentElement, $textNodes, $rawStrings );

		// PHASE 2: Check Cache
		$cached = $this->translator->getCachedTranslations( array_unique( $rawStrings ), $targetLang );

		// PHASE 3: Apply Cache or Tokenize
		foreach ( $textNodes as $item ) {
			/** @var DOMText $node */
			$node = $item['node'];
			$text = $item['text'];
			$lSpace = $item['lSpace'];
			$rSpace = $item['rSpace'];

			if ( isset( $cached[$text] ) ) {
				// HIT: Insert translated text directly
				$this->replaceWithText( $dom, $node, $lSpace, $cached[$text], $rSpace );
			} else {
				// MISS: Create Shimmer Token
				$this->replaceWithToken( $dom, $node, $lSpace, $text, $rSpace );
			}
		}

		return $dom->saveHTML();
	}

	/**
	 * Recursive traversal to find all text nodes
	 */
	private function harvestNodes( $node, array &$textNodes, array &$rawStrings ): void {
		if ( !$node ) { return; }

		// Skip References
		if ( $node instanceof DOMElement ) {
			$class = $node->getAttribute( 'class' );
			if ( 
				( $node->nodeName === 'sup' && strpos( $class, 'reference' ) !== false ) ||
				( $node->nodeName === 'span' && strpos( $class, 'mw-editsection' ) !== false ) 
			) {
				return;
			}
			if ( in_array( strtolower( $node->nodeName ), self::IGNORE_TAGS ) ) {
				$node->parentNode->removeChild( $node );
				return;
			}
		}

		$children = iterator_to_array( $node->childNodes );
		foreach ( $children as $child ) {
			if ( $child instanceof DOMText ) {
				$raw = $child->textContent;
				if ( trim( $raw ) === '' ) { continue; }
				
				$lSpace = preg_match( '/^\s+/', $raw, $m ) ? $m[0] : '';
				$rSpace = preg_match( '/\s+$/', $raw, $m ) ? $m[0] : '';
				$cleanText = trim( $raw );

				if ( strlen( $cleanText ) > 0 ) {
					// Store for Phase 2
					$textNodes[] = [
						'node' => $child,
						'text' => $cleanText,
						'lSpace' => $lSpace,
						'rSpace' => $rSpace
					];
					$rawStrings[] = $cleanText;
				}
				continue;
			}
			if ( $child->hasChildNodes() ) {
				$this->harvestNodes( $child, $textNodes, $rawStrings );
			}
		}
	}

	private function replaceWithText( DOMDocument $dom, DOMText $originalNode, string $lSpace, string $translatedText, string $rSpace ): void {
		$parent = $originalNode->parentNode;
		// Combine spaces and text into one node for cleanliness
		$fullText = $lSpace . $translatedText . $rSpace;
		$newNode = $dom->createTextNode( $fullText );
		$parent->replaceChild( $newNode, $originalNode );
	}

	private function replaceWithToken( DOMDocument $dom, DOMText $originalNode, string $lSpace, string $text, string $rSpace ): void {
		$parent = $originalNode->parentNode;

		if ( $lSpace !== '' ) {
			$parent->insertBefore( $dom->createTextNode( $lSpace ), $originalNode );
		}

		$span = $dom->createElement( 'span' );
		$span->setAttribute( 'class', 'gemini-token' );
		$span->setAttribute( 'data-source', base64_encode( $text ) );
		// No inline styles needed anymore - handled by gemini.css
		$span->textContent = $text; 

		$parent->insertBefore( $span, $originalNode );

		if ( $rSpace !== '' ) {
			$parent->insertBefore( $dom->createTextNode( $rSpace ), $originalNode );
		}

		$parent->removeChild( $originalNode );
	}
}
