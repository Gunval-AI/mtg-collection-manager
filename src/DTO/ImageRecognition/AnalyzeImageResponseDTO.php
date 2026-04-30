<?php

namespace App\DTO\ImageRecognition;

class AnalyzeImageResponseDTO
{
    public function __construct(
        // Recognition flow used to produce the result (exact_footer, fallback_title)
        public string $resultType,
        public ?string $detectedTitle,
        public ?string $detectedCollectorNumber,
        public ?string $detectedSetCode,
        public ?string $detectedLanguage,
        public bool $footerComplete,
        public ImageRecognitionResolutionDTO $resolution,
        // Recognized print when the result is only one
        public ?array $recognizedPrint,
        // List of candidate prints when manual selection is required
        public array $candidatePrints = []
    ) {
    }
}