<?php
/**
 * AI Design Studio - AI Provider Chain
 * Manages ordered failover across AI providers with deadline budgets, 429 flood control,
 * and single-pass JSON repair.
 */

defined('APP_INIT') or define('APP_INIT', true);

require_once __DIR__ . '/GeminiClient.php';

class ProviderChain {

    /**
     * Dispatch an AI request through the ordered provider chain
     * 
     * @param string $systemPrompt System instructions
     * @param string $userPrompt User prompt or data
     * @param array|null $schema Optional response schema
     * @param bool $jsonMode Whether JSON response is requested
     * @return array ['success' => bool, 'content' => ?string, 'provider' => string, 'model' => ?string, 'code' => ?string, 'error' => ?string]
     */
    public static function dispatch(string $systemPrompt, string $userPrompt, ?array $schema = null, bool $jsonMode = true): array {
        $startTime = microtime(true);
        $deadlineBudget = defined('AI_REQUEST_DEADLINE') ? (float)AI_REQUEST_DEADLINE : 30.0;

        $geminiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
        $fallbackKey = defined('FALLBACK_API_KEY') ? trim(FALLBACK_API_KEY) : '';

        if (empty($geminiKey) && empty($fallbackKey)) {
            app_log("No AI API keys configured in config.php", 'ERROR', 'ai.log');
            return [
                'success'  => false,
                'content'  => null,
                'provider' => 'none',
                'model'    => null,
                'code'     => 'NO_KEY',
                'error'    => t('ai_missing_key')
            ];
        }

        $geminiRes = null;
        $fallbackRes = null;

        // 1. Try Gemini API first if key exists
        if (!empty($geminiKey)) {
            // Check deadline
            if ((microtime(true) - $startTime) >= $deadlineBudget) {
                return ['success' => false, 'content' => null, 'provider' => 'none', 'model' => null, 'code' => 'TIMEOUT', 'error' => t('ai_timeout')];
            }

            $geminiRes = GeminiClient::callText($systemPrompt, $userPrompt, $schema, $jsonMode);
            if ($geminiRes['success']) {
                return [
                    'success'  => true,
                    'content'  => $geminiRes['content'],
                    'provider' => 'gemini',
                    'model'    => $geminiRes['model'] ?? (defined('GEMINI_MODEL') ? GEMINI_MODEL : 'gemini-3-flash-preview'),
                    'code'     => null,
                    'error'    => null
                ];
            }

            app_log("Gemini failed ({$geminiRes['code']}). Attempting fallback provider if configured...", 'WARNING', 'ai.log');
        }

        // 2. Try Fallback Provider (Groq / OpenRouter) if configured
        if (!empty($fallbackKey)) {
            $elapsed = microtime(true) - $startTime;
            $remaining = (int)max(3, $deadlineBudget - $elapsed);

            if ($remaining <= 2) {
                return ['success' => false, 'content' => null, 'provider' => 'none', 'model' => null, 'code' => 'TIMEOUT', 'error' => t('ai_timeout')];
            }

            $fallbackRes = self::callFallback($systemPrompt, $userPrompt, $jsonMode, $remaining);
            if ($fallbackRes['success']) {
                return [
                    'success'  => true,
                    'content'  => $fallbackRes['content'],
                    'provider' => defined('FALLBACK_PROVIDER') ? FALLBACK_PROVIDER : 'fallback',
                    'model'    => $fallbackRes['model'] ?? (defined('FALLBACK_MODEL') ? FALLBACK_MODEL : 'llama-3.3-70b-versatile'),
                    'code'     => null,
                    'error'    => null
                ];
            }

            app_log("Fallback provider also failed ({$fallbackRes['code']}).", 'ERROR', 'ai.log');
        }

        // Return honest bilingual error with real failure code
        $primaryErr = $geminiRes['error'] ?? t('ai_invalid_response');
        $primaryCode = $geminiRes['code'] ?? 'AI_FAILED';

        if (!empty($fallbackKey) && !empty($fallbackRes['error'])) {
            $errMessage = t('ai_fallback_failed') . " (" . $fallbackRes['error'] . ")";
            $errCode = $fallbackRes['code'] ?? $primaryCode;
        } else {
            $errMessage = $primaryErr;
            $errCode = $primaryCode;
        }

        return [
            'success'  => false,
            'content'  => null,
            'provider' => 'none',
            'model'    => null,
            'code'     => $errCode,
            'error'    => $errMessage
        ];
    }

    /**
     * Call OpenAI-compatible fallback provider (Groq or OpenRouter)
     */
    public static function callFallback(string $systemPrompt, string $userPrompt, bool $jsonMode = true, ?int $customTimeout = null): array {
        $provider = defined('FALLBACK_PROVIDER') ? strtolower(trim(FALLBACK_PROVIDER)) : '';
        $apiKey   = defined('FALLBACK_API_KEY') ? trim(FALLBACK_API_KEY) : '';
        $model    = defined('FALLBACK_MODEL') && FALLBACK_MODEL ? trim(FALLBACK_MODEL) : '';

        if (empty($provider) || empty($apiKey)) {
            return ['success' => false, 'code' => 'NO_FALLBACK', 'error' => 'No fallback provider configured.'];
        }

        $url = defined('FALLBACK_API_BASE_URL') && FALLBACK_API_BASE_URL
            ? FALLBACK_API_BASE_URL
            : (($provider === 'openrouter') ? 'https://openrouter.ai/api/v1/chat/completions' : 'https://api.groq.com/openai/v1/chat/completions');

        if (empty($model)) {
            $model = ($provider === 'openrouter') ? 'meta-llama/llama-3.3-70b-instruct:free' : 'llama-3.3-70b-versatile';
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => 0.4,
        ];

        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            "Authorization: Bearer {$apiKey}"
        ];

        if ($provider === 'openrouter') {
            $headers[] = 'HTTP-Referer: http://localhost';
            $headers[] = 'X-Title: AI Design Studio';
        }

        $timeout = $customTimeout !== null ? $customTimeout : (defined('FALLBACK_TIMEOUT') ? (int)FALLBACK_TIMEOUT : 20);
        $connectTimeout = defined('FALLBACK_CONNECT_TIMEOUT') ? (int)FALLBACK_CONNECT_TIMEOUT : 5;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            app_log("Fallback ({$provider}) cURL error: {$curlErr}", 'ERROR', 'ai.log');
            return ['success' => false, 'code' => 'FALLBACK_CURL_ERR', 'error' => $curlErr];
        }

        $res = json_decode((string)$raw, true);

        if ($httpCode === 429) {
            app_log("Fallback ({$provider}) 429 Quota Exceeded.", 'WARNING', 'ai.log');
            return ['success' => false, 'code' => 'QUOTA_EXCEEDED', 'error' => t('ai_quota_exceeded')];
        }

        if ($httpCode >= 400 || !empty($res['error'])) {
            $msg = $res['error']['message'] ?? "Fallback HTTP error {$httpCode}";
            app_log("Fallback ({$provider}) API error ({$httpCode}): {$msg}", 'ERROR', 'ai.log');
            return ['success' => false, 'code' => "FALLBACK_HTTP_{$httpCode}", 'error' => $msg];
        }

        $content = $res['choices'][0]['message']['content'] ?? null;
        if ($content === null) {
            app_log("Fallback ({$provider}) empty response", 'ERROR', 'ai.log');
            return ['success' => false, 'code' => 'FALLBACK_EMPTY', 'error' => t('ai_invalid_response')];
        }

        return ['success' => true, 'content' => $content, 'model' => $model, 'code' => null, 'error' => null];
    }

    /**
     * Attempt single-pass JSON repair
     */
    public static function repairJson(string $brokenContent, string $systemPrompt, ?array $schema = null): ?array {
        $repairPrompt = "The previous response produced malformed or invalid JSON:\n\"\"\"{$brokenContent}\"\"\"\n\nPlease fix the JSON syntax and return ONLY the complete valid JSON object conforming to the schema:";
        $repairRes = self::dispatch($systemPrompt, $repairPrompt, $schema, true);
        if ($repairRes['success'] && !empty($repairRes['content'])) {
            return extract_and_decode_json($repairRes['content']);
        }
        return null;
    }
}

