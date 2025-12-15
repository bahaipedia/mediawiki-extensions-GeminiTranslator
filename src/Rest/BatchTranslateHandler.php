<?php

namespace MediaWiki\Extension\GeminiTranslator\Rest;

use MediaWiki\Extension\GeminiTranslator\PageTranslator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Context\RequestContext; // Added to get the real IP
use Wikimedia\ParamValidator\ParamValidator;

class BatchTranslateHandler extends SimpleHandler {

	private PageTranslator $translator;

	public function __construct( PageTranslator $translator ) {
		$this->translator = $translator;
	}

	public function execute() {
		$body = $this->getValidatedBody();
		$strings = $body['strings'] ?? [];
		$targetLang = $body['targetLang'];
		$request = $this->getRequest();

		// --- LOGGING ---
		try {
			// FIX: Use RequestContext to get the IP. 
			// The REST $request object does not have getIP(), but the global context does.
			$realIp = RequestContext::getMain()->getRequest()->getIP();
			
			$authority = $this->getAuthority();
			$user = $authority ? $authority->getUser() : null;

			LoggerFactory::getInstance( 'GeminiTranslator' )->info(
				'Batch request received',
				[
					'ip' => $realIp,
					'target_lang' => $targetLang,
					'count' => count( $strings ),
					'user' => $user ? $user->getName() : 'Unknown',
					'user_agent' => $request->getHeader( 'User-Agent' )
				]
			);
		} catch ( \Throwable $e ) {
			// If logger fails, use error_log so we don't crash the translation
			error_log( 'GeminiTranslator Logger Error: ' . $e->getMessage() );
		}

		// --- PROCESSING ---

		// Limit batch size for safety
		if ( count( $strings ) > 50 ) {
			$strings = array_slice( $strings, 0, 50 );
		}

		try {
			$translations = $this->translator->translateStrings( $strings, $targetLang );
			
			return $this->getResponseFactory()->createJson( [
				'translations' => $translations
			] );

		} catch ( \RuntimeException $e ) {
			
			// Log API failures
			LoggerFactory::getInstance( 'GeminiTranslator' )->error(
				'API Failure',
				[ 'error' => $e->getMessage() ]
			);

			return $this->getResponseFactory()->createJson( [
				'error' => $e->getMessage()
			], 500 );
		}
	}

	public function getBodyParamSettings(): array {
		return [
			'strings' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'targetLang' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
