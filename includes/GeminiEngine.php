<?php
/**
 * VALKYRIN Neural Core Engine - Gemini API Integration
 */

class GeminiEngine {
    private string $apiKey;
    private string $model;
    private string $apiEndpoint;

    public function __construct(?string $userApiKey = null, string $model = 'gemini-3.6-flash') {
        // Fall back to environment variable or server superglobal if empty
        $this->apiKey = trim($userApiKey ?: (getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '')));
        $this->model = !empty($model) ? $model : 'gemini-3.6-flash';
        $this->apiEndpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent";
    }

    /**
     * Generate response using system instruction and conversation history.
     */
    public function generateResponse(string $userPrompt, string $systemInstruction = '', array $history = []): array {
        if (empty($this->apiKey)) {
            return [
                'success' => false,
                'error'   => 'Gemini API key is unconfigured or missing from environment and database.'
            ];
        }

        $contents = [];

        // Format history nodes into Gemini API roles ('user' / 'model')
        foreach ($history as $msg) {
            $role = ($msg['role'] === 'assistant' || $msg['role'] === 'model') ? 'model' : 'user';
            $text = $msg['content'] ?? ($msg['text'] ?? '');
            
            if (!empty($text)) {
                $contents[] = [
                    'role'  => $role,
                    'parts' => [['text' => $text]]
                ];
            }
        }

        // Append current prompt
        $contents[] = [
            'role'  => 'user',
            'parts' => [['text' => $userPrompt]]
        ];

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 2048,
            ]
        ];

        if (!empty($systemInstruction)) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        return $this->executeCurl($payload);
    }

    /**
     * Execute cURL request to Google Generative Language REST API
     */
    private function executeCurl(array $payload): array {
        $url = $this->apiEndpoint . '?key=' . $this->apiKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $this->apiKey
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'error' => "cURL Error: " . $error];
        }

        $json = json_decode($response, true);

        if ($httpCode !== 200 || isset($json['error'])) {
            $msg = $json['error']['message'] ?? "HTTP Status {$httpCode}";
            error_log("VALKYRIN GeminiEngine API Error (HTTP {$httpCode}): " . $response);
            return ['success' => false, 'error' => "Gemini API Error: " . $msg];
        }

        // Extract text output
        $replyText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

        return [
            'success' => true,
            'text'    => $replyText,
            'raw'     => $json
        ];
    }
}