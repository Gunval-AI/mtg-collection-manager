<?php

namespace App\DTO\ImageRecognition;

class AnalyzeImageRequestDTO
{
    public function __construct(
        public string $tempFilePath,
        public string $originalFilename,
        public string $mimeType,
        public int $collectionId,
        public int $conditionId
    ) {
    }
}