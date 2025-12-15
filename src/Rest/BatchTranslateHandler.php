<?php

namespace MediaWiki\Extension\GeminiTranslator\Rest;

use MediaWiki\Extension\GeminiTranslator\PageTranslator;
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

		// --- LOGGING (Wrapped to prevent 503 crashes) ---
		// We use wfDebugLog which maps directly to $wgDebugLogGroups['GeminiTranslator']
		try {
			$totalChars = 0;
			foreach ( $strings as $str ) {
				if ( is_string( $str ) ) {
					$totalChars += mb_strlen( $str );
				}
			}

			$request = $this->getRequest();
			// Defensive check for authority/user
			$authority = $this->getAuthority();
			$user = $authority ? $authority->getUser() : null;
			
			$logData = [
				'event' => 'batch_request',
				'ip' => $request->getIP(),
				'target_lang' => $targetLang,
				'string_count' => count( $strings ),
				'total_chars' => $totalChars,
				'user_id' => $user ? $user->getId() : 0,
				'user_name' => $user ? $user->getName() : 'Unknown',
				'timestamp' => date( 'c' )
			];

			// Write to the file defined in $wgDebugLogGroups['GeminiTranslator']
			wfDebugLog( 'GeminiTranslator', json_encode( $logData ) );

		} catch ( \Throwable $e ) {
			// If logging fails, write to the main server error log but DO NOT stop execution
			error_log( 'GeminiTranslator Logging Failed: ' . $e->getMessage() );
		}

		// --- TRANSLATION LOGIC ---

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
			// Return a 500 error so the JS .fail() block triggers
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
