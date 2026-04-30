<?php

namespace App\Services\ImageRecognition;

use CURLFile;
use InvalidArgumentException;
use RuntimeException;

// Client responsible for communicating with the external image recognition service.
class RecognitionApiClient
{
    private string $baseUrl;

    public function __construct(?string $baseUrl = null)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function analyzeCardImage(
        string $imagePath,
        string $mimeType,
        string $originalFilename
    ): array {
        if (!file_exists($imagePath)) {
            throw new InvalidArgumentException("El archivo de imagen no existe: {$imagePath}");
        }

        if (trim($mimeType) === '') {
            throw new InvalidArgumentException('El tipo MIME es obligatorio.');
        }

        if (trim($originalFilename) === '') {
            throw new InvalidArgumentException('El nombre original del archivo es obligatorio.');
        }

        $url = $this->baseUrl . '/analyze-card';

        $ch = curl_init();

        $postFields = [
            'image' => new CURLFile($imagePath, $mimeType, $originalFilename)
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            curl_close($ch);

            throw new RuntimeException("Error llamando al microservicio de reconocimiento: {$error}");
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($rawResponse, true);

        if ($httpCode >= 400) {
            throw new RuntimeException("El microservicio de reconocimiento devolvió HTTP {$httpCode}");
        }

        if (!is_array($data)) {
            throw new RuntimeException('Respuesta JSON inválida del microservicio de reconocimiento.');
        }

        return $data;
    }
}