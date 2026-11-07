<?php
/**
 * AI Design Studio - Hugging Face Client
 * Handles AI background removal using RMBG models on Hugging Face Inference API.
 */

defined('APP_INIT') or define('APP_INIT', true);

class HuggingFaceClient {

    /**
     * Remove image background using Hugging Face RMBG model
     * 
     * @param string $imageData Base64 data URL or raw binary
     * @return array ['success' => bool, 'image_url' => string, 'data_url' => string, 'model' => string, 'code' => ?string, 'error' => ?string]
     */
    public static function removeBackground(string $imageData): array {
        if (empty(trim($imageData))) {
            return ['success' => false, 'code' => 'EMPTY_IMAGE', 'error' => t('image_empty')];
        }

        // Parse base64 data URL if provided
        $binary = $imageData;
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $imageData, $matches)) {
            $binary = base64_decode($matches[2]);
            if ($binary === false || strlen($binary) === 0) {
                return ['success' => false, 'code' => 'INVALID_IMAGE', 'error' => t('image_empty')];
            }
        }

        $token = defined('HUGGINGFACE_API_TOKEN') ? trim(HUGGINGFACE_API_TOKEN) : '';
        if (empty($token)) {
            app_log("Hugging Face API token not configured", 'WARNING', 'ai.log');
            return ['success' => false, 'code' => 'NO_TOKEN', 'error' => t('rmbg_token_missing')];
        }

        $model = defined('HUGGINGFACE_RMBG_MODEL') && HUGGINGFACE_RMBG_MODEL ? trim(HUGGINGFACE_RMBG_MODEL) : 'briaai/RMBG-1.4';
        $baseUrl = defined('HUGGINGFACE_API_BASE_URL') ? rtrim(HUGGINGFACE_API_BASE_URL, '/') : 'https://api-inference.huggingface.co/models';
        $url = "{$baseUrl}/{$model}";

        $timeout = defined('HUGGINGFACE_TIMEOUT') ? (int)HUGGINGFACE_TIMEOUT : 25;
        $connectTimeout = defined('HUGGINGFACE_CONNECT_TIMEOUT') ? (int)HUGGINGFACE_CONNECT_TIMEOUT : 8;

        $headers = [
            'Content-Type: application/octet-stream',
            'Authorization: Bearer ' . $token
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $binary,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        $curlErrNo = curl_errno($ch);
        curl_close($ch);

        if ($curlErrNo === CURLE_OPERATION_TIMEDOUT) {
            app_log("Hugging Face RMBG timeout ({$timeout}s)", 'WARNING', 'ai.log');
            return ['success' => false, 'code' => 'TIMEOUT', 'error' => t('rmbg_failed')];
        }

        if ($curlErr) {
            app_log("Hugging Face cURL error: {$curlErr}", 'ERROR', 'ai.log');
            return ['success' => false, 'code' => 'CURL_ERR', 'error' => t('rmbg_failed')];
        }

        // Model loading
        if ($httpCode === 503) {
            app_log("Hugging Face RMBG 503 model loading: {$response}", 'WARNING', 'ai.log');
            return ['success' => false, 'code' => 'MODEL_LOADING', 'error' => t('rmbg_model_loading')];
        }

        // Rate limit
        if ($httpCode === 429) {
            app_log("Hugging Face RMBG 429 rate limit: {$response}", 'WARNING', 'ai.log');
            return ['success' => false, 'code' => 'RATE_LIMIT', 'error' => t('rmbg_rate_limit')];
        }

        // Auth required or token invalid
        if ($httpCode === 401 || $httpCode === 403) {
            app_log("Hugging Face RMBG auth failure ({$httpCode}): {$response}", 'WARNING', 'ai.log');
            return ['success' => false, 'code' => 'AUTH_REQUIRED', 'error' => t('rmbg_token_missing')];
        }

        if ($httpCode !== 200 || empty($response)) {
            app_log("Hugging Face RMBG error ({$httpCode}): {$response}", 'ERROR', 'ai.log');
            return ['success' => false, 'code' => "HTTP_{$httpCode}", 'error' => t('rmbg_failed')];
        }

        // Save cutout image
        $filename = 'cutout_' . time() . '_' . bin2hex(random_bytes(4)) . '.png';
        $uploadDir = dirname(__DIR__, 2) . '/storage/uploads/';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        $uploadPath = $uploadDir . $filename;
        file_put_contents($uploadPath, $response);

        return [
            'success'   => true,
            'image_url' => 'storage/uploads/' . $filename,
            'data_url'  => 'data:image/png;base64,' . base64_encode($response),
            'model'     => $model
        ];
    }
}

