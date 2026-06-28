<?php

declare(strict_types=1);

namespace App\Service\AiRewriting\Provider;

use Psr\Log\LoggerInterface;

class LlmProviderFactory
{
    public function __construct(
        private readonly string $providerName,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $openAiBaseUrl,
        private readonly LoggerInterface $logger,
    ) {}

    public function create(): LlmProviderInterface
    {
        $http = new \GuzzleHttp\Client();

        return match ($this->providerName) {
            'openai'    => new OpenAiProvider($http, $this->apiKey, $this->model, $this->openAiBaseUrl, $this->logger),
            'anthropic' => new AnthropicProvider($http, $this->apiKey, $this->model, $this->logger),
            'gemini'    => new GeminiProvider($http, $this->apiKey, $this->model, $this->logger),
            default     => throw new \InvalidArgumentException("Unknown LLM provider: \"{$this->providerName}\""),
        };
    }
}
