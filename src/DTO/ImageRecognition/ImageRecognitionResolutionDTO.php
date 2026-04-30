<?php

namespace App\DTO\ImageRecognition;

class ImageRecognitionResolutionDTO
{
    public function __construct(
        // Functional resolution status (matched, needs_confirmation, ambiguous_card, not_found)
        public string $status,
        public ?int $cardId,
        public ?int $printId
    ) {
    }
}