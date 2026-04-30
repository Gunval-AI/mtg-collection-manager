<?php

namespace App\Services\ImageRecognition;

use App\DTO\ImageRecognition\AnalyzeImageRequestDTO;
use App\DTO\ImageRecognition\AnalyzeImageResponseDTO;
use App\DTO\ImageRecognition\ImageRecognitionResolutionDTO;
use App\Repositories\PrintRepository;
use RuntimeException;

class ImageRecognitionService
{
    public function __construct(
        private RecognitionApiClient $recognitionApiClient,
        private PrintRepository $printRepository
    ) {
    }

    public function analyze(AnalyzeImageRequestDTO $dto): AnalyzeImageResponseDTO
    {
        $this->validateRequest($dto);

        $pythonResult = $this->recognitionApiClient->analyzeCardImage(
            $dto->tempFilePath,
            $dto->mimeType,
            $dto->originalFilename
        );

        if (($pythonResult['success'] ?? false) !== true) {
            throw new RuntimeException('El microservicio no pudo reconocer la imagen.');
        }

        $title = $pythonResult['title'] ?? null;

        $footerDetection = $pythonResult['footer_detection'] ?? [];
        $collectorNumber = $footerDetection['collector_number'] ?? null;
        $footerSetCode = $footerDetection['set_code'] ?? null;

        $language = $this->normalizeLanguageForCopy(
            $footerDetection['language'] ?? null
        );

        $footerComplete = (bool) ($footerDetection['is_complete'] ?? false);

        if ($footerComplete) {
            return $this->analyzeByExactFooter(
                $title,
                $collectorNumber,
                $footerSetCode,
                $language,
                $footerComplete
            );
        }

        return $this->analyzeByTitleFallback(
            $title,
            $collectorNumber,
            $footerSetCode,
            $language,
            $footerComplete
        );
    }

    private function analyzeByExactFooter(
        ?string $title,
        ?string $collectorNumber,
        ?string $setCode,
        ?string $language,
        bool $footerComplete
    ): AnalyzeImageResponseDTO {
        $print = $this->resolveByExactFooter($collectorNumber, $setCode, $language);

        if ($print === null) {
            return new AnalyzeImageResponseDTO(
                'exact_footer',
                $title,
                $collectorNumber,
                $setCode,
                $language,
                $footerComplete,
                new ImageRecognitionResolutionDTO('not_found', null, null),
                null,
                []
            );
        }

        return new AnalyzeImageResponseDTO(
            'exact_footer',
            $title,
            $collectorNumber,
            $setCode,
            $language,
            $footerComplete,
            new ImageRecognitionResolutionDTO(
                'matched',
                (int) $print['card']['id'],
                (int) $print['print']['id']
            ),
            $print,
            []
        );
    }

    private function analyzeByTitleFallback(
        ?string $title,
        ?string $collectorNumber,
        ?string $setCode,
        ?string $language,
        bool $footerComplete
    ): AnalyzeImageResponseDTO {
        if ($title === null || trim($title) === '') {
            return new AnalyzeImageResponseDTO(
                'fallback_title',
                $title,
                $collectorNumber,
                $setCode,
                $language,
                $footerComplete,
                new ImageRecognitionResolutionDTO('not_found', null, null),
                null,
                []
            );
        }

        $cardResolution = $this->resolveCardByTitle($title);

        if ($cardResolution['status'] === 'not_found') {
            return new AnalyzeImageResponseDTO(
                'fallback_title',
                $title,
                $collectorNumber,
                $setCode,
                $language,
                $footerComplete,
                new ImageRecognitionResolutionDTO('not_found', null, null),
                null,
                []
            );
        }

        if ($cardResolution['status'] === 'ambiguous_card') {
            return new AnalyzeImageResponseDTO(
                'fallback_title',
                $title,
                $collectorNumber,
                $setCode,
                $language,
                $footerComplete,
                new ImageRecognitionResolutionDTO('ambiguous_card', null, null),
                null,
                []
            );
        }

        $cardId = (int) $cardResolution['cardId'];
        $prints = $this->printRepository->findFullPrintsByCardId($cardId);

        if (count($prints) === 0) {
            return new AnalyzeImageResponseDTO(
                'fallback_title',
                $title,
                $collectorNumber,
                $setCode,
                $language,
                $footerComplete,
                new ImageRecognitionResolutionDTO('not_found', $cardId, null),
                null,
                []
            );
        }

        $mappedPrints = array_map(
            fn (array $print) => $this->mapPrintRowToFrontendResponse($print, $language),
            $prints
        );

        if (count($mappedPrints) === 1) {
            $recognizedPrint = $mappedPrints[0];

            return new AnalyzeImageResponseDTO(
                'fallback_title',
                $title,
                $collectorNumber,
                $setCode,
                $language,
                $footerComplete,
                new ImageRecognitionResolutionDTO(
                    'matched',
                    (int) $recognizedPrint['card']['id'],
                    (int) $recognizedPrint['print']['id']
                ),
                $recognizedPrint,
                []
            );
        }

        return new AnalyzeImageResponseDTO(
            'fallback_title',
            $title,
            $collectorNumber,
            $setCode,
            $language,
            $footerComplete,
            new ImageRecognitionResolutionDTO(
                'needs_confirmation',
                $cardId,
                null
            ),
            null,
            $mappedPrints
        );
    }

    private function resolveByExactFooter(
        ?string $collectorNumber,
        ?string $setCode,
        ?string $language
    ): ?array {
        if ($collectorNumber === null || trim($collectorNumber) === '') {
            return null;
        }

        if ($setCode === null || trim($setCode) === '') {
            return null;
        }

        $print = $this->printRepository->findBySetCodeAndCollectorNumber(
            $setCode,
            $collectorNumber
        );

        if ($print === null) {
            return null;
        }

        return $this->mapPrintRowToFrontendResponse($print, $language);
    }

    private function resolveCardByTitle(string $title): array
    {
        $candidates = $this->printRepository->findCardCandidatesByTitle($title);

        if (count($candidates) === 0) {
            return ['status' => 'not_found'];
        }

        $normalizedDetectedTitle = $this->normalizeForSimilarity($title);

        $matches = [];
        $minimumScore = 85.0;

        foreach ($candidates as $candidate) {
            $scoreEn = $this->calculateSimilarity(
                $normalizedDetectedTitle,
                $this->normalizeForSimilarity($candidate['nombre_en'] ?? '')
            );

            $scoreEs = $this->calculateSimilarity(
                $normalizedDetectedTitle,
                $this->normalizeForSimilarity($candidate['nombre_es'] ?? '')
            );

            $score = max($scoreEn, $scoreEs);

            if ($score >= $minimumScore) {
                $matches[] = [
                    'cardId' => (int) $candidate['id_carta'],
                    'score' => $score,
                ];
            }
        }

        if (count($matches) === 0) {
            return ['status' => 'not_found'];
        }

        usort($matches, fn ($a, $b) => $b['score'] <=> $a['score']);

        $bestScore = $matches[0]['score'];
        $bestMatches = array_filter(
            $matches,
            fn ($match) => abs($match['score'] - $bestScore) < 3.0
        );

        if (count($bestMatches) > 1) {
            return ['status' => 'ambiguous_card'];
        }

        return [
            'status' => 'matched',
            'cardId' => $matches[0]['cardId'],
            'score' => $matches[0]['score'],
        ];
    }

    private function mapPrintRowToFrontendResponse(array $row, ?string $detectedLanguage): array
    {
        return [
            'card' => [
                'id' => (int) $row['id_carta'],
                'nameEn' => $row['nombre_en'] ?? null,
                'nameEs' => $row['nombre_es'] ?? null,
            ],
            'print' => [
                'id' => (int) $row['id_impresion'],
                'setCode' => $row['codigo_edicion'] ?? null,
                'setName' => $row['nombre_edicion'] ?? null,
                'collectorNumber' => $row['numero_coleccion'] ?? null,
                'rarity' => $row['rareza'] ?? null,
                'imageSmall' => $row['imagen_small'] ?? null,
                'imageNormal' => $row['imagen_normal'] ?? null,
                'scryfallUri' => $row['scryfall_uri'] ?? null,
            ],
            'copyDefaults' => [
                'language' => $detectedLanguage,
            ]
        ];
    }

    private function normalizeLanguageForCopy(?string $language): ?string
    {
        if ($language === null || trim($language) === '') {
            return null;
        }

        return match (strtoupper(trim($language))) {
            'SP', 'ES' => 'ES',
            'EN' => 'EN',
            default => null,
        };
    }

    private function normalizeForSimilarity(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = trim($value);
        $value = mb_strtolower($value, 'UTF-8');

        $search = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
        $replace = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];

        $value = str_replace($search, $replace, $value);
        $value = preg_replace('/[^a-z0-9\s]/u', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function calculateSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return (float) $percent;
    }

    private function validateRequest(AnalyzeImageRequestDTO $dto): void
    {
        if (trim($dto->tempFilePath) === '') {
            throw new RuntimeException('La ruta temporal de la imagen es obligatoria.');
        }

        if (!file_exists($dto->tempFilePath)) {
            throw new RuntimeException('El archivo de imagen no existe.');
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($dto->mimeType, $allowedMimeTypes, true)) {
            throw new RuntimeException('El tipo de archivo no es válido. Solo se permiten JPG, PNG y WEBP.');
        }

        if ($dto->collectionId <= 0) {
            throw new RuntimeException('La colección es obligatoria.');
        }

        if ($dto->conditionId <= 0) {
            throw new RuntimeException('La condición es obligatoria.');
        }
    }
}