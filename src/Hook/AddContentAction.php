<?php

namespace MediaWiki\Extension\GeminiTranslator\Hook;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Title\Title;

class AddContentAction implements
	SkinTemplateNavigation__UniversalHook,
	BeforePageDisplayHook
{
	/**
	 * Adds the Javascript module if the user is logged in
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$user = $skin->getUser();
		if ( $user->isRegistered() ) {
			$out->addModules( [ 'ext.geminitranslator.bootstrap' ] );
		}
	}

	/**
	 * Adds the Menu Item / Button
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		$user = $sktemplate->getUser();
		$title = $sktemplate->getTitle();

		// Don't show on special pages or edit mode
		if ( !$title->exists() || $title->isSpecialPage() ) {
			return;
		}

		$text = $sktemplate->msg( 'geminitranslator-ca-translate' )->text();

		if ( $user->isAnon() ) {
			// Anon User: Link to Help Page
			$helpTitle = Title::newFromText( 'Help:GeminiTranslator' );
			$href = $helpTitle ? $helpTitle->getLinkURL() : '#';
			
			$links['actions']['gemini-translate'] = [
				'class' => '',
				'text' => $text,
				'href' => $href,
				'position' => 30,
			];
		} else {
			// Logged In: JS Trigger
			// We give it a specific ID that bootstrap.js looks for
			$links['actions']['gemini-translate'] = [
				'class' => '',
				'text' => $text,
				'href' => '#',
				'id' => 'ca-gemini-translate', // JS hooks into this ID
				'position' => 30,
			];
		}
	}
}
