<?php
/**
 * AI Design Studio - Pollinations.ai Client
 * Builds URLs and proxies for text-free background illustration generation.
 */

defined('APP_INIT') or define('APP_INIT', true);

class PollinationsClient {

    /**
     * Build direct Pollinations.ai image URL with anti-text and sizing parameters
     */
    public static function buildImageUrl(string $prompt, int $width = 1080, int $height = 1080, bool $noLogo = true): string {
        $baseUrl = defined('POLLINATIONS_BASE_URL') ? rtrim(POLLINATIONS_BASE_URL, '/') : 'https://image.pollinations.ai';
        $sanitizedPrompt = trim($prompt);
        if (!str_contains($sanitizedPrompt, 'no text')) {
            $sanitizedPrompt .= ', no text, no letters, no logos, clean digital art';
        }
        $encodedPrompt = urlencode($sanitizedPrompt);
        $logoParam = $noLogo ? '&nologo=true' : '';
        return "{$baseUrl}/prompt/{$encodedPrompt}?width={$width}&height={$height}{$logoParam}";
    }

    /**
     * Build same-origin proxy URL for canvas embedding (avoids CORS / tainted canvas issues)
     */
    public static function buildProxyUrl(string $prompt, int $width = 1080, int $height = 1080): string {
        $directUrl = self::buildImageUrl($prompt, $width, $height, true);
        return 'api/proxy-image.php?url=' . urlencode($directUrl);
    }
}

