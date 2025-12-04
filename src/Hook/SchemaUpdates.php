<?php

namespace MediaWiki\Extension\GeminiTranslator\Hook;

use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SchemaUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$dir = dirname( __DIR__, 2 );
		$updater->addExtensionTable(
			'gemini_translation_blocks',
			"$dir/sql/mysql/tables.sql"
		);
	}
}
