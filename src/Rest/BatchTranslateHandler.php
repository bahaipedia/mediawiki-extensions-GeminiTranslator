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
		error_log("GEMINI CRASH TRACE: 1. Entered BatchTranslateHandler");

		$body = $this->getValidatedBody();
		$strings = $body['strings'] ?? [];
		$targetLang = $body['targetLang'];

		error_log("GEMINI CRASH TRACE: 2. Body parsed. Strings count: " . count($strings));

		// Limit batch size just in case
		if ( count( $strings ) > 50 ) {
			$strings = array_slice( $strings, 0, 50 );
		}

		// Check for encoding issues
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log("GEMINI CRASH TRACE: JSON Error in request body: " . json_last_error_msg());
		}

		error_log("GEMINI CRASH TRACE: 3. Calling translateStrings...");
		$translations = $this->translator->translateStrings( $strings, $targetLang );
		error_log("GEMINI CRASH TRACE: 8. Returned from translateStrings");

		return $this->getResponseFactory()->createJson( [
			'translations' => $translations
		] );
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
