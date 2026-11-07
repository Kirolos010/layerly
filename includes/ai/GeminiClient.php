<?php
/**
 * AI Design Studio - Google Gemini Client
 * Handles text and multimodal vision requests to the Gemini API.
 */

defined('APP_INIT') or define('APP_INIT', true);

class GeminiClient {

    /**
     * Call Google Gemini API (generateContent) with structured JSON or text prompt
     */
    public static function callText(
        string $systemPrompt,
        string $userPrompt,
        ?array $schema = null,
        bool $jsonMode = true,
        ?string $overrideKey = null,
        ?string $overrideModel = null
    ): array {
        $apiKey = $overrideKey !== null ? $overrideKey : (defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '');
        if (empty($apiKey)) {
            return ['success' => false, 'code' => 'NO_KEY', 'error' => t('ai_missing_key')];
        }

        $baseUrl = defined('GEMINI_API_BASE_URL') ? rtrim(GEMINI_API_BASE_URL, '/') : 'https://generativelanguage.googleapis.com/v1beta';
        $configuredModel = defined('GEMINI_MODEL') && GEMINI_MODEL ? trim(GEMINI_MODEL) : 'gemini-3-flash-preview';
        $timeout = defined('GEMINI_TIMEOUT') ? (int)GEMINI_TIMEOUT : (defined('AI_CURL_TIMEOUT') ? (int)AI_CURL_TIMEOUT : 25);
        $connectTimeout = defined('GEMINI_CONNECT_TIMEOUT') ? (int)GEMINI_CONNECT_TIMEOUT : 7;

        $modelsToTry = $overrideModel !== null ? [$overrideModel] : [
            $configuredModel,
            'gemini-3.8-flash',
            'gemini-flash-latest'
        ];
        $modelsToTry = array_unique(array_filter($modelsToTry));

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        ['text' => $userPrompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.4,
                'topP'            => 0.95,
                'maxOutputTokens' => 8192,
            ]
        ];

        if (!empty($systemPrompt)) {
            $payload['systemInstruction'] = [
                'role'  => 'system',
                'parts' => [
                    ['text' => $systemPrompt]
                ]
            ];
        }

        if ($jsonMode) {
            $payload['generationConfig']['responseMimeType'] = 'application/json';
            if ($schema !== null) {
                $payload['generationConfig']['responseSchema'] = $schema;
            }
        }

        $lastError = null;

        foreach ($modelsToTry as $model) {
            $url = "{$baseUrl}/models/{$model}:generateContent?key=" . urlencode($apiKey);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8'
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            $curlErrNo = curl_errno($ch);
            curl_close($ch);

            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
                app_log("Gemini ({$model}) cURL timeout ({$timeout}s)", 'WARNING', 'ai.log');
                $lastError = ['success' => false, 'code' => 'TIMEOUT', 'error' => t('ai_timeout')];
                continue;
            }

            if ($curlErr) {
                app_log("Gemini ({$model}) cURL error: {$curlErr}", 'ERROR', 'ai.log');
                $lastError = ['success' => false, 'code' => 'CURL_ERR', 'error' => $curlErr];
                continue;
            }

            $res = json_decode((string)$raw, true);

            if ($httpCode === 429) {
                app_log("Gemini ({$model}) 429 Quota Exceeded.", 'WARNING', 'ai.log');
                return ['success' => false, 'code' => 'QUOTA_EXCEEDED', 'error' => t('ai_quota_exceeded')];
            }

            if ($httpCode >= 400 || !empty($res['error'])) {
                $msg = $res['error']['message'] ?? "HTTP error {$httpCode}";
                app_log("Gemini API ({$model}) error ({$httpCode}): {$msg}", 'WARNING', 'ai.log');
                $lastError = ['success' => false, 'code' => "HTTP_{$httpCode}", 'error' => $msg];
                continue;
            }

            $content = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($content === null) {
                app_log("Gemini ({$model}) empty candidates: {$raw}", 'ERROR', 'ai.log');
                $lastError = ['success' => false, 'code' => 'EMPTY_RESPONSE', 'error' => t('ai_invalid_response')];
                continue;
            }

            return ['success' => true, 'content' => $content, 'model' => $model, 'code' => null, 'error' => null];
        }

        return $lastError ?: ['success' => false, 'code' => 'ALL_MODELS_FAILED', 'error' => t('ai_invalid_response')];
    }

    /**
     * Call Google Gemini Vision API with an image and prompt
     */
    public static function callVision(
        string $base64Data,
        string $mimeType,
        string $userPrompt,
        ?array $schema = null,
        ?string $overrideKey = null,
        ?string $overrideModel = null
    ): array {
        $apiKey = $overrideKey !== null ? $overrideKey : (defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '');
        if (empty($apiKey)) {
            return ['success' => false, 'code' => 'NO_KEY', 'error' => t('ai_missing_key')];
        }

        $baseUrl = defined('GEMINI_API_BASE_URL') ? rtrim(GEMINI_API_BASE_URL, '/') : 'https://generativelanguage.googleapis.com/v1beta';
        $configuredModel = defined('GEMINI_MODEL') && GEMINI_MODEL ? trim(GEMINI_MODEL) : 'gemini-3-flash-preview';
        $timeout = defined('GEMINI_TIMEOUT') ? (int)GEMINI_TIMEOUT : (defined('AI_CURL_TIMEOUT') ? (int)AI_CURL_TIMEOUT : 35);
        $connectTimeout = defined('GEMINI_CONNECT_TIMEOUT') ? (int)GEMINI_CONNECT_TIMEOUT : 8;

        $modelsToTry = $overrideModel !== null ? [$overrideModel] : [
            $configuredModel,
            'gemini-3.8-flash',
            'gemini-flash-latest'
        ];
        $modelsToTry = array_unique(array_filter($modelsToTry));

        $payload = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'inlineData' => [
                                'mimeType' => $mimeType,
                                'data'     => $base64Data
                            ]
                        ],
                        [
                            'text' => $userPrompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature'        => 0.2,
                'topP'               => 0.9,
                'maxOutputTokens'    => 8192,
                'responseMimeType'   => 'application/json'
            ]
        ];

        if ($schema !== null) {
            $payload['generationConfig']['responseSchema'] = $schema;
        }

        $lastError = null;

        foreach ($modelsToTry as $model) {
            $url = "{$baseUrl}/models/{$model}:generateContent?key=" . urlencode($apiKey);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json; charset=utf-8'
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => $timeout,
                CURLOPT_CONNECTTIMEOUT => $connectTimeout,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr = curl_error($ch);
            $curlErrNo = curl_errno($ch);
            curl_close($ch);

            if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
                app_log("Gemini Vision ({$model}) timeout ({$timeout}s)", 'WARNING', 'ai.log');
                $lastError = ['success' => false, 'code' => 'TIMEOUT', 'error' => t('ai_timeout')];
                continue;
            }

            if ($curlErr) {
                app_log("Gemini Vision ({$model}) cURL error: {$curlErr}", 'ERROR', 'ai.log');
                $lastError = ['success' => false, 'code' => 'CURL_ERR', 'error' => $curlErr];
                continue;
            }

            $res = json_decode((string)$raw, true);

            if ($httpCode === 429) {
                app_log("Gemini Vision ({$model}) 429 Quota Exceeded.", 'WARNING', 'ai.log');
                return ['success' => false, 'code' => 'QUOTA_EXCEEDED', 'error' => t('ai_quota_exceeded')];
            }

            if ($httpCode >= 400 || !empty($res['error'])) {
                $msg = $res['error']['message'] ?? "HTTP error {$httpCode}";
                app_log("Gemini Vision ({$model}) error ({$httpCode}): {$msg}", 'WARNING', 'ai.log');
                $lastError = ['success' => false, 'code' => "HTTP_{$httpCode}", 'error' => $msg];
                continue;
            }

            $content = $res['candidates'][0]['content']['parts'][0]['text'] ?? null;
            if ($content === null) {
                app_log("Gemini Vision ({$model}) empty candidates: {$raw}", 'ERROR', 'ai.log');
                $lastError = ['success' => false, 'code' => 'EMPTY_RESPONSE', 'error' => t('ai_invalid_response')];
                continue;
            }

            return ['success' => true, 'content' => $content, 'model' => $model, 'code' => null, 'error' => null];
        }

        return $lastError ?: ['success' => false, 'code' => 'ALL_MODELS_FAILED', 'error' => t('ai_invalid_response')];
    }

    /**
     * Validate configured Gemini model against ListModels API or fall back safely
     */
    public static function validateModel(string $type = 'text'): string {
        $apiKey = defined('GEMINI_API_KEY') ? trim(GEMINI_API_KEY) : '';
        $default = ($type === 'image')
            ? (defined('GEMINI_IMAGE_MODEL') && GEMINI_IMAGE_MODEL ? GEMINI_IMAGE_MODEL : 'gemini-2.5-flash-image')
            : (defined('GEMINI_MODEL') && GEMINI_MODEL ? GEMINI_MODEL : 'gemini-3-flash-preview');

        if (empty($apiKey)) return $default;

        $cacheFile = dirname(__DIR__, 2) . "/storage/logs/gemini_models_cache.json";
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400)) {
            $cached = json_decode((string)file_get_contents($cacheFile), true);
            if (is_array($cached) && in_array($default, $cached, true)) {
                return $default;
            }
        }

        $baseUrl = defined('GEMINI_API_BASE_URL') ? rtrim(GEMINI_API_BASE_URL, '/') : 'https://generativelanguage.googleapis.com/v1beta';
        $url = "{$baseUrl}/models?key=" . urlencode($apiKey);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $raw = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $raw) {
            $data = json_decode($raw, true);
            if (!empty($data['models'])) {
                $names = [];
                foreach ($data['models'] as $m) {
                    $clean = str_replace('models/', '', $m['name'] ?? '');
                    if ($clean) $names[] = $clean;
                }
                @file_put_contents($cacheFile, json_encode($names));
                if (in_array($default, $names, true)) {
                    return $default;
                }
            }
        }

        return $default;
    }
}

