<?php
/**
 * AI Design Studio - English Translations
 * Auto-loads from canonical en.json for single-source-of-truth parity.
 */
return json_decode(file_get_contents(__DIR__ . '/en.json'), true) ?: [];
