<?php

namespace App\Controllers\ImageRecognition;

use App\DTO\ImageRecognition\AnalyzeImageRequestDTO;
use App\DTO\ImageRecognition\AnalyzeImageResponseDTO;
use App\Services\ImageRecognition\ImageRecognitionService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Throwable;

class ImageRecognitionController
{
    public function __construct(private ImageRecognitionService $service)
    {
    }

    public function analyze(Request $request, Response $response): Response
    {
        try {
            $uploadedFiles = $request->getUploadedFiles();
            $parsedBody = $request->getParsedBody() ?? [];

            $image = $uploadedFiles['image'] ?? null;

            if ($image === null) {
                throw new RuntimeException('La imagen es obligatoria.');
            }

            if ($image->getError() !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Error al subir la imagen. Código: ' . $image->getError());
            }

            $stream = $image->getStream();
            $metadata = $stream->getMetadata();
            $tempFilePath = $metadata['uri'] ?? '';

            $dto = new AnalyzeImageRequestDTO(
                $tempFilePath,
                $image->getClientFilename() ?? '',
                $image->getClientMediaType() ?? '',
                isset($parsedBody['collectionId']) ? (int) $parsedBody['collectionId'] : 0,
                isset($parsedBody['conditionId']) ? (int) $parsedBody['conditionId'] : 0
            );

            $result = $this->service->analyze($dto);

            $response->getBody()->write(json_encode([
                'success' => true,
                'data' => $this->mapAnalyzeResponse($result)
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json');
        } catch (RuntimeException $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ], JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        } catch (Throwable $e) {
            $response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Error interno al reconocer la carta.'
            ], JSON_UNESCAPED_UNICODE));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500);
        }
    }

    private function mapAnalyzeResponse(AnalyzeImageResponseDTO $result): array
    {
        return [
            'resultType' => $result->resultType,
            'detectedTitle' => $result->detectedTitle,
            'detectedCollectorNumber' => $result->detectedCollectorNumber,
            'detectedSetCode' => $result->detectedSetCode,
            'detectedLanguage' => $result->detectedLanguage,
            'footerComplete' => $result->footerComplete,
            'resolution' => [
                'status' => $result->resolution->status,
                'cardId' => $result->resolution->cardId,
                'printId' => $result->resolution->printId,
            ],
            'recognizedPrint' => $result->recognizedPrint,
            'candidatePrints' => $result->candidatePrints,
        ];
    }
}