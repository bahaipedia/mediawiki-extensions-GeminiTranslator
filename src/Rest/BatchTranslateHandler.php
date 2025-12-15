<?php

namespace MediaWiki\Extension\GeminiTranslator\Rest;

use MediaWiki\Extension\GeminiTranslator\PageTranslator;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Rest\SimpleHandler;
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

		// --- LOGGING (Correct LoggerFactory implementation) ---
		// This logs to the channel 'GeminiTranslator'.
		// If using Monolog (standard in MW), this usually routes to specific files or the main log.
		try {
			$authority = $this->getAuthority();
			$user = $authority ? $authority->getUser() : null;

			LoggerFactory::getInstance( 'GeminiTranslator' )->info(
				'Batch request received',
				[
					'ip' => $request->getIP(),
					'target_lang' => $targetLang,
					'count' => count( $strings ),
					'user' => $user ? $user->getName() : 'Unknown',
					'user_agent' => $request->getHeader( 'User-Agent' )
				]
			);
		} catch ( \Throwable $e ) {
			// Fail silently to error_log if the Logger service itself is broken
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
			
			// Log the specific API failure via LoggerFactory
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
