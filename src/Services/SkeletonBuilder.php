<?php

namespace MediaWiki\Extension\GeminiTranslator\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DOMText;

class SkeletonBuilder {

	private const IGNORE_TAGS = [ 'style', 'script', 'link', 'meta' ];
	private const BLOCK_TAGS = [ 'div', 'p', 'table', 'tbody', 'tr', 'td', 'ul', 'ol', 'li', 'blockquote', 'section' ];

	public function createSkeleton( string $html ): string {
		if ( trim( $html ) === '' ) {
			return '';
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// Remove References
		$removals = $xpath->query( '//sup[contains(@class,"reference")] | //span[contains(@class,"mw-editsection")]' );
		foreach ( $removals as $node ) {
			$node->parentNode->removeChild( $node );
		}

		$this->processNode( $dom, $dom->documentElement );

		return $dom->saveHTML();
	}

	private function processNode( DOMDocument $dom, $node ): void {
		if ( !$node ) { return; }

		// Use a standard for loop because we might modify the list of children
		$children = iterator_to_array( $node->childNodes );
		foreach ( $children as $child ) {
			
			// Remove garbage
			if ( $child instanceof DOMElement && in_array( strtolower( $child->nodeName ), self::IGNORE_TAGS ) ) {
				$node->removeChild( $child );
				continue;
			}

			// Handle Text Nodes
			if ( $child instanceof DOMText ) {
				$raw = $child->textContent;
				// If it's pure whitespace, leave it alone (it preserves layout)
				if ( trim( $raw ) === '' ) {
					continue;
				}
				
				// Identify whitespace to preserve
				$lSpace = preg_match( '/^\s+/', $raw, $m ) ? $m[0] : '';
				$rSpace = preg_match( '/\s+$/', $raw, $m ) ? $m[0] : '';
				$cleanText = trim( $raw );

				// Only wrap if there is actual text
				if ( strlen( $cleanText ) > 0 ) {
					$this->wrapTextNode( $dom, $child, $lSpace, $cleanText, $rSpace );
				}
				continue;
			}

			// Recurse
			if ( $child->hasChildNodes() ) {
				$this->processNode( $dom, $child );
			}
		}
	}

	private function wrapTextNode( DOMDocument $dom, DOMText $originalNode, string $lSpace, string $text, string $rSpace ): void {
		$parent = $originalNode->parentNode;

		// 1. Insert Leading Space (as a Text Node)
		if ( $lSpace !== '' ) {
			$parent->insertBefore( $dom->createTextNode( $lSpace ), $originalNode );
		}

		// 2. Insert The Token
		$span = $dom->createElement( 'span' );
		$span->setAttribute( 'class', 'gemini-token' );
		$span->setAttribute( 'data-source', base64_encode( $text ) );
		// Visual style
		$span->setAttribute( 'style', 'background-color: #f8f9fa; color: transparent; border-bottom: 2px solid #eaecf0; transition: all 0.5s ease;' );
		$span->textContent = $text; // Keep original length visible for skeleton feel

		$parent->insertBefore( $span, $originalNode );

		// 3. Insert Trailing Space
		if ( $rSpace !== '' ) {
			$parent->insertBefore( $dom->createTextNode( $rSpace ), $originalNode );
		}

		// 4. Remove the original node
		$parent->removeChild( $originalNode );
	}
}
