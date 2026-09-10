<?php
if (!defined('VALKYRIN_EXEC')) {
    exit('Direct access forbidden.');
}

class GroqService {
    private string $apiKey;
    private string $model;

    public function __construct(string $model = 'llama-3.3-70b-versatile') {
        $this->apiKey = $_ENV['GROQ_API'] ?? getenv('GROQ_API') ?? '';
        $this->model = $model;
    }

    /**
     * Send a prompt to Groq API and return the response text
     */
    public function generateResponse(string $systemPrompt, string $userPrompt): ?string {
        if (empty($this->apiKey)) {
            error_log('Groq API key missing in environment.');
            return null;
        }

        $url = 'https://api.groq.com/openai/v1/chat/completions';
        
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return $data['choices'][0]['message']['content'] ?? null;
        }

        error_log("Groq API Error [HTTP $httpCode]: " . $response);
        return null;
    }
}