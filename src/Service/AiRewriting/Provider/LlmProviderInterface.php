<?php

declare(strict_types=1);

namespace App\Service\AiRewriting\Provider;

use App\Service\AiRewriting\LlmApiException;
use App\Service\AiRewriting\LlmResponse;

interface LlmProviderInterface
{
    /**
     * @param array<array{role: string, content: string}> $messages
     * @throws LlmApiException
     */
    public function generate(array $messages, float $temperature = 0.7): LlmResponse;

    public function getName(): string;
}
