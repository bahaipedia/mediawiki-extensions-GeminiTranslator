CREATE TABLE /*_*/gemini_translation_blocks (
  gtb_source_hash VARBINARY(64) NOT NULL,
  gtb_lang VARBINARY(20) NOT NULL,
  gtb_content MEDIUMBLOB NOT NULL,
  gtb_last_touched BINARY(14) NOT NULL,
  PRIMARY KEY (gtb_source_hash, gtb_lang)
) /*$wgDBTableOptions*/;
