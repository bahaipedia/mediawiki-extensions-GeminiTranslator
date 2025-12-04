<?php

use MediaWiki\Extension\GeminiTranslator\PageTranslator;
use MediaWiki\Extension\GeminiTranslator\Services\GeminiClient;
use MediaWiki\MediaWikiServices;

return [
	'GeminiTranslator.GeminiClient' => static function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		return new GeminiClient(
			$config->get( 'GeminiApiKey' ),
			$config->get( 'GeminiModel' ),
			$services->getHttpRequestFactory()
		);
	},

	'GeminiTranslator.PageTranslator' => static function ( MediaWikiServices $services ) {
		return new PageTranslator(
			$services->getService( 'GeminiTranslator.GeminiClient' ),
			$services->getDBLoadBalancer(),
			$services->getMainConfig()
		);
	},
];
