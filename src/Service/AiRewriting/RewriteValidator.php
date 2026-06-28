<?php

declare(strict_types=1);

namespace App\Service\AiRewriting;

class RewriteValidator
{
    private const int MAX_TITLE_LENGTH = 35;
    private const int MIN_INTRO_LENGTH = 200;
    private const int MAX_INTRO_LENGTH = 500;

    public function validate(array $response, string $hotelName): ValidationResult
    {
        $errors = [];

        if (!isset($response['angebotstitel'], $response['einleitung'])) {
            return new ValidationResult(valid: false, errors: ['missing_required_fields']);
        }

        $title = $response['angebotstitel'];
        $intro = $response['einleitung'];

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            $errors[] = 'title_too_long';
        }

        if ($title === '') {
            $errors[] = 'title_empty';
        }

        if ($hotelName !== '' && str_contains(mb_strtolower($title), mb_strtolower($hotelName))) {
            $errors[] = 'title_contains_hotel_name';
        }

        $len = mb_strlen($intro);
        if ($len < self::MIN_INTRO_LENGTH || $len > self::MAX_INTRO_LENGTH) {
            $errors[] = 'intro_length_invalid';
        }

        return new ValidationResult(valid: $errors === [], errors: $errors);
    }
}
