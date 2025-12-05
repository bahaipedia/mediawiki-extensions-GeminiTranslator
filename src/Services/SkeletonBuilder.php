<?php

namespace MediaWiki\Extension\GeminiTranslator\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use DOMText;

class SkeletonBuilder {

	/**
	 * Tags that we should strictly ignore (remove or skip)
	 */
	private const IGNORE_TAGS = [ 'style', 'script', 'link', 'meta' ];

	/**
	 * Tags that act as block containers (we don't translate the tag, but we traverse inside)
	 */
	private const BLOCK_TAGS = [ 'div', 'p', 'table', 'tbody', 'tr', 'td', 'ul', 'ol', 'li', 'blockquote', 'section' ];

	public function createSkeleton( string $html ): string {
		if ( trim( $html ) === '' ) {
			return '';
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		// UTF-8 Hack
		$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		// 1. Remove References and Edit Sections
		// We remove <sup class="reference"> and <span class="mw-editsection">
		// This saves tokens and keeps the translation clean.
		$removals = $xpath->query( '//sup[contains(@class,"reference")] | //span[contains(@class,"mw-editsection")]' );
		foreach ( $removals as $node ) {
			$node->parentNode->removeChild( $node );
		}

		// 2. Process Text Nodes
		// We traverse the DOM to find text nodes that are not empty
		$this->processNode( $dom, $dom->documentElement );

		// 3. Return HTML
		return $dom->saveHTML();
	}

	private function processNode( DOMDocument $dom, $node ): void {
		if ( !$node ) {
			return;
		}

		foreach ( iterator_to_array( $node->childNodes ) as $child ) {
			// Remove garbage tags
			if ( $child instanceof DOMElement && in_array( strtolower( $child->nodeName ), self::IGNORE_TAGS ) ) {
				$node->removeChild( $child );
				continue;
			}

			// Handle Links (<a href="..">Label</a>)
			// We want to translate the Label, but keep the <a> wrapper.
			// The recursion below handles this automatically because <a> contains a TextNode.
			
			// Handle Text Nodes
			if ( $child instanceof DOMText ) {
				$text = trim( $child->textContent );
				// Ignore empty strings or just symbols
				if ( strlen( $text ) > 1 || preg_match( '/\p{L}/u', $text ) ) {
					$this->wrapTextNode( $dom, $child, $text );
				}
				continue;
			}

			// Recursion
			if ( $child->hasChildNodes() ) {
				$this->processNode( $dom, $child );
			}
		}
	}

	private function wrapTextNode( DOMDocument $dom, DOMText $textNode, string $content ): void {
		// Create the placeholder wrapper
		// <span class="gemini-token" data-source="Original Text"></span>
		// We encode the content to ensure HTML safety in the attribute
		
		$span = $dom->createElement( 'span' );
		$span->setAttribute( 'class', 'gemini-token' );
		// We use base64 for data-source to avoid any quote escaping issues in the DOM
		$span->setAttribute( 'data-source', base64_encode( $content ) );
		
		// Add a visible spinner/loading state inside the span
		// The CSS will handle the spinning
		$span->setAttribute( 'style', 'display:inline-block; min-width: 20px; background: #f0f0f0; border-radius: 3px; color: transparent; cursor: wait;' );
		$span->textContent = '...'; 

		// Replace the original text node with our wrapper
		$textNode->parentNode->replaceChild( $span, $textNode );
	}
}
