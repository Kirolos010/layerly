<?php
/**
 * AI Design Studio - Arabic Translations
 * Auto-loads from canonical ar.json for single-source-of-truth parity.
 */
return json_decode(file_get_contents(__DIR__ . '/ar.json'), true) ?: [];
