<?php

declare(strict_types=1);

namespace App\Service\AiRewriting;

use App\Service\AiRewriting\Guard\ContentGuardService;
use App\Service\AiRewriting\Guard\SemanticGuardService;
use App\Service\AiRewriting\Provider\LlmProviderInterface;
use Psr\Log\LoggerInterface;

class ArrangementRewriteService
{
    private const int MAX_ATTEMPTS = 2;
    private const float INITIAL_TEMPERATURE = 0.7;
    private const float RETRY_TEMPERATURE = 0.3;

    public function __construct(
        private readonly LlmProviderInterface $llmProvider,
        private readonly RewriteValidator $validator,
        private readonly ContentGuardService $contentGuardService,
        private readonly SemanticGuardService $semanticGuardService,
        private readonly LoggerInterface $logger,
        private readonly string $promptVersion = 'v1.0',
    ) {}

    public function rewrite(
        string $originalTitle,
        string $originalDescription,
        string $hotelName,
        string $city,
        string $region = '',
        array $hotelAttributes = [],
    ): RewriteResult {
        $messages = [
            ['role' => 'system', 'content' => $this->getSystemPrompt()],
            ['role' => 'user', 'content' => $this->buildUserPrompt(
                $originalTitle,
                $originalDescription,
                $hotelName,
                $city,
                $region,
                $hotelAttributes
            )],
        ];

        $parsed = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $temperature = $attempt === 1 ? self::INITIAL_TEMPERATURE : self::RETRY_TEMPERATURE;

            try {
                $response = $this->llmProvider->generate($messages, $temperature);
            } catch (LlmApiException $e) {
                $this->logger->error('LLM API call failed', [
                    'attempt' => $attempt,
                    'provider' => $e->getProviderName(),
                ]);
                return RewriteResult::failed('llm_api_error: ' . $e->getMessage());
            }

            $parsed = $this->parseResponse($response);
            if ($parsed === null) {
                return RewriteResult::failed('json_parse_error');
            }

            $validation = $this->validator->validate($parsed, $hotelName);
            if ($validation->isValid()) {
                break;
            }

            if ($attempt === self::MAX_ATTEMPTS) {
                return RewriteResult::failed(
                    'validation_failed_after_retry: ' . implode(', ', $validation->getErrors())
                );
            }

            // Self-correction: feed the errors back to the model for one more attempt.
            $messages[] = ['role' => 'assistant', 'content' => $response->getRawContent()];
            $messages[] = ['role' => 'user', 'content' => $this->buildCorrectionPrompt($parsed, $validation, $hotelName)];
        }

        // Stage 2: content safety (blocklist + moderation API)
        $contentGuard = $this->contentGuardService->check($parsed['angebotstitel'], $parsed['einleitung']);
        if (!$contentGuard->isAccepted()) {
            return RewriteResult::rejected($contentGuard->getReason());
        }

        // Stage 3: semantic validation incl. fact-check against the source material
        $sourceMaterial = $originalTitle . ' ' . $originalDescription . ' ' . implode(' ', $hotelAttributes);
        $semanticGuard = $this->semanticGuardService->check(
            $parsed['angebotstitel'],
            $parsed['einleitung'],
            $originalTitle,
            $hotelName,
            $city,
            $sourceMaterial
        );
        if (!$semanticGuard->isAccepted()) {
            return RewriteResult::rejected($semanticGuard->getReason());
        }

        return RewriteResult::approved($parsed['angebotstitel'], $parsed['einleitung']);
    }

    /** SHA-256 over the source fields — drives idempotency (skip if unchanged). */
    public function computeSourceHash(string $title, string $description, string $hotelName, string $city): string
    {
        return hash('sha256', implode('|', [$title, $description, $hotelName, $city]));
    }

    private function getSystemPrompt(): string
    {
        return <<<PROMPT
Du bist ein erfahrener Content- und SEO-Experte, spezialisiert auf Reise-Content.
Deine Aufgabe ist es, zwei Content-Elemente eines Reiseangebots komplett neu zu schreiben,
um Duplicate Content zwischen zwei Portalen zu vermeiden.

1. Angebotstitel (max. 35 Zeichen):
   KEIN Hotelname (wird vom Portal angehängt)
   Ort/Region MUSS enthalten sein
   Reisedauer beibehalten
   Hauptkeyword am Anfang

2. Einleitung (200-500 Zeichen):
   erste 155 Zeichen = Meta-Description
   wichtigste Keywords zuerst
   gleiche Fakten wie Original aber komplett andere Formulierung
   KEINE Fakten/Features erfinden
   KEINE Preisangaben

Keyword-Priorität: Land < Region < Ort < Hotelname (nur in Einleitung)

Tone of Voice: Du-Form, beratend, hochwertig, keine Emojis, keine Preise.

Output-Format (JSON): {"angebotstitel": "...", "einleitung": "..."}
PROMPT;
    }

    private function buildUserPrompt(
        string $title,
        string $description,
        string $hotelName,
        string $city,
        string $region,
        array $attributes
    ): string {
        $attrList = $attributes ? implode(', ', $attributes) : 'keine Angaben';

        return <<<PROMPT
Hotel: {$hotelName}
Ort: {$city}
Region: {$region}
Ausstattung: {$attrList}

Originaltitel: {$title}
Originaleinleitung: {$description}

Schreibe Titel und Einleitung komplett neu. Antworte nur mit dem JSON-Objekt.
PROMPT;
    }

    private function buildCorrectionPrompt(array $parsed, ValidationResult $validation, string $hotelName): string
    {
        $errors = implode(', ', $validation->getErrors());
        $title  = $parsed['angebotstitel'] ?? '';
        $intro  = $parsed['einleitung'] ?? '';

        return <<<PROMPT
Deine letzte Antwort enthält Fehler: {$errors}

Bitte korrigiere:
- Titel: "{$title}"
- Einleitung: "{$intro}"

Hotel: {$hotelName}. Antworte nur mit dem korrigierten JSON-Objekt.
PROMPT;
    }

    private function parseResponse(LlmResponse $response): ?array
    {
        $content = trim($response->getRawContent());
        // Strip markdown code fences if present.
        $content = preg_replace('/^```(?:json)?\n?/i', '', $content);
        $content = preg_replace('/\n?```$/', '', $content);

        try {
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
