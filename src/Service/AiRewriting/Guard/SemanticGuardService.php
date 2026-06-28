<?php

declare(strict_types=1);

namespace App\Service\AiRewriting\Guard;

use App\Service\AiRewriting\GuardResult;

class SemanticGuardService
{
    /**
     * Amenity keywords to check for hallucination.
     * If a term appears in the generated text but not in the source material, it's flagged.
     */
    private const array AMENITY_KEYWORDS = [
        'pool', 'schwimmbad', 'hallenbad', 'freibad',
        'sauna', 'dampfbad', 'whirlpool',
        'spa', 'wellness', 'therme',
        'fitness', 'golf', 'tennis',
        'strand', 'skigebiet',
        'kinderbetreuung', 'restaurant',
        'parkplatz', 'hundefreundlich', 'barrierefrei',
        // ... extend as needed
    ];

    public function check(
        string $genTitle,
        string $genDesc,
        string $origTitle,
        string $hotelName,
        string $city,
        string $sourceMaterial = '',
    ): GuardResult {
        $errors = [];

        // City must appear in the generated description.
        if ($city !== '' && !str_contains(mb_strtolower($genDesc), mb_strtolower($city))) {
            $errors[] = 'city_missing_in_description';
        }

        // Output must be German.
        if (!$this->isLikelyGerman($genDesc)) {
            $errors[] = 'wrong_language';
        }

        // No star ratings in title.
        if (preg_match('/\d\s*Sterne?\b/i', $genTitle)) {
            $errors[] = 'star_rating_in_title';
        }

        // No prices in generated text.
        if (preg_match('/\d+[\.,]?\d*\s*€/', $genTitle . ' ' . $genDesc)) {
            $errors[] = 'price_in_generated_text';
        }

        // Hotel name must not appear in title (portal appends it).
        if ($hotelName !== '' && str_contains(mb_strtolower($genTitle), mb_strtolower($hotelName))) {
            $errors[] = 'hotel_name_in_title';
        }

        // Fact-check: amenity named in output but absent from source = likely hallucination.
        $hallucinated = $this->detectHallucinatedAmenities($genDesc, $sourceMaterial ?: $origTitle);
        if ($hallucinated !== []) {
            $errors[] = 'hallucinated_amenities: ' . implode(', ', $hallucinated);
        }

        return $errors === []
            ? GuardResult::accepted()
            : GuardResult::rejected('semantic: ' . implode(', ', $errors));
    }

    private function detectHallucinatedAmenities(string $genText, string $source): array
    {
        $genLower    = mb_strtolower($genText);
        $sourceLower = mb_strtolower($source);
        $hallucinated = [];

        foreach (self::AMENITY_KEYWORDS as $keyword) {
            if (str_contains($genLower, $keyword) && !str_contains($sourceLower, $keyword)) {
                $hallucinated[] = $keyword;
            }
        }

        return $hallucinated;
    }

    private function isLikelyGerman(string $text): bool
    {
        // Lightweight check: presence of common German function words.
        $germanMarkers = ['und', 'der', 'die', 'das', 'in', 'für', 'mit', 'im', 'ist', 'ein'];
        $lower = mb_strtolower($text);
        $hits  = 0;

        foreach ($germanMarkers as $marker) {
            if (str_contains($lower, " {$marker} ") || str_starts_with($lower, "{$marker} ")) {
                $hits++;
            }
        }

        return $hits >= 2;
    }
}
