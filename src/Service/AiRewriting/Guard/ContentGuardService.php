<?php

declare(strict_types=1);

namespace App\Service\AiRewriting\Guard;

use App\Service\AiRewriting\GuardResult;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class ContentGuardService
{
    /** @param string[] $blocklist Terms loaded from YAML config */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $openAiModerationApiKey,
        private readonly array $blocklist = [],
    ) {}

    public function check(string $title, string $description): GuardResult
    {
        $text = $title . ' ' . $description;

        $blocklistResult = $this->checkBlocklist($text);
        if (!$blocklistResult->isAccepted()) {
            return $blocklistResult;
        }

        if ($this->openAiModerationApiKey !== '') {
            $moderationResult = $this->checkModeration($text);
            if (!$moderationResult->isAccepted()) {
                return $moderationResult;
            }
        }

        return GuardResult::accepted();
    }

    private function checkBlocklist(string $text): GuardResult
    {
        $lower = mb_strtolower($text);

        foreach ($this->blocklist as $term) {
            if (str_contains($lower, mb_strtolower($term))) {
                return GuardResult::rejected("blocklist_match: {$term}");
            }
        }

        return GuardResult::accepted();
    }

    private function checkModeration(string $text): GuardResult
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/moderations', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAiModerationApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json'    => ['input' => $text],
                'timeout' => 10,
            ]);

            $body = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

            if ($body['results'][0]['flagged'] ?? false) {
                $categories = array_keys(array_filter($body['results'][0]['categories'] ?? []));
                return GuardResult::rejected('moderation_flagged: ' . implode(', ', $categories));
            }

            return GuardResult::accepted();

        } catch (GuzzleException|\JsonException $e) {
            // Fail closed: moderation API error = reject, not silently accept.
            return GuardResult::rejected('moderation_api_error: ' . $e->getMessage());
        }
    }
}
