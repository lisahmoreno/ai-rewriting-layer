<?php

declare(strict_types=1);

namespace App\Service\AiRewriting\Provider;

use App\Service\AiRewriting\LlmApiException;
use App\Service\AiRewriting\LlmResponse;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

class AnthropicProvider implements LlmProviderInterface
{
    private const string API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly LoggerInterface $logger,
    ) {}

    public function generate(array $messages, float $temperature = 0.7): LlmResponse
    {
        // Anthropic separates the system prompt from the message array.
        $systemPrompt = '';
        $apiMessages  = [];

        foreach ($messages as $m) {
            $m['role'] === 'system'
                ? $systemPrompt = $m['content']
                : $apiMessages[] = $m;
        }

        try {
            $payload = [
                'model'       => $this->model,
                'max_tokens'  => 1024,
                'temperature' => $temperature,
                'messages'    => $apiMessages,
            ];

            if ($systemPrompt !== '') {
                $payload['system'] = $systemPrompt;
            }

            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ],
                'json'    => $payload,
                'timeout' => 30,
            ]);

            $body = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            return new LlmResponse($body['content'][0]['text'] ?? '');

        } catch (GuzzleException $e) {
            throw new LlmApiException(
                'Anthropic API request failed: ' . $e->getMessage(),
                $this->getName(),
                0,
                $e
            );
        }
    }

    public function getName(): string
    {
        return 'anthropic';
    }
}
