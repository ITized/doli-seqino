<?php

declare(strict_types=1);

require_once __DIR__.'/SeqinoApiException.php';

/**
 * Secure base REST client for Seqino PDP API.
 */
abstract class AbstractSeqinoApiClient
{
    protected const DEFAULT_TIMEOUT_SECONDS = 30;

    private string $apiToken;

    private string $baseUrl;

    private int $timeoutSeconds;

    /**
     * @param array<string, string> $environmentUrls
     */
    public function __construct(string $apiToken, string $environment, array $environmentUrls, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS)
    {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new InvalidArgumentException('Seqino API token cannot be empty.');
        }

        if (!isset($environmentUrls[$environment])) {
            throw new InvalidArgumentException(sprintf('Unknown Seqino environment "%s".', $environment));
        }

        $baseUrl = rtrim(trim($environmentUrls[$environment]), '/');
        if ($baseUrl === '' || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Seqino base URL is invalid.');
        }

        if ($timeoutSeconds < 1) {
            throw new InvalidArgumentException('Timeout must be greater than 0 second.');
        }

        $this->apiToken = $apiToken;
        $this->baseUrl = $baseUrl;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    /**
     * @param array<string,mixed>|null $body
     * @param array<string,string> $headers
     *
     * @return array<string,mixed>
     */
    protected function request(string $method, string $path, ?array $body = null, array $headers = array()): array
    {
        $method = strtoupper(trim($method));
        if (!in_array($method, array('GET', 'POST', 'PUT', 'PATCH', 'DELETE'), true)) {
            throw new InvalidArgumentException(sprintf('Unsupported HTTP method "%s".', $method));
        }

        $path = '/'.ltrim(trim($path), '/');
        $url = $this->baseUrl.$path;

        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_THROW_ON_ERROR);
        }

        $defaultHeaders = array(
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$this->apiToken,
        );
        $finalHeaders = $headers + $defaultHeaders;

        $response = $this->sendHttpRequest($method, $url, $payload, $finalHeaders, $this->timeoutSeconds);
        $statusCode = $response['status_code'] ?? 0;
        $rawBody = $response['body'] ?? '';

        if (!is_int($statusCode) || $statusCode < 100) {
            throw new SeqinoApiException('Seqino API response status is invalid.', 0, null, $this->buildSafeContext($method, $url, $statusCode, $rawBody));
        }

        if ($rawBody === '' || $rawBody === null) {
            if ($statusCode >= 200 && $statusCode < 300) {
                return array();
            }

            throw new SeqinoApiException('Seqino API returned an empty response body.', $statusCode, null, $this->buildSafeContext($method, $url, $statusCode, ''));
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SeqinoApiException('Seqino API returned invalid JSON.', $statusCode, $exception, $this->buildSafeContext($method, $url, $statusCode, $rawBody));
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = 'Seqino API request failed.';
            if (is_array($decoded) && isset($decoded['message']) && is_string($decoded['message'])) {
                $message = 'Seqino API request failed: '.$decoded['message'];
            }

            throw new SeqinoApiException($message, $statusCode, null, $this->buildSafeContext($method, $url, $statusCode, $rawBody));
        }

        return is_array($decoded) ? $decoded : array('data' => $decoded);
    }

    /**
     * @param array<string,string> $headers
     *
     * @return array{status_code:int,body:string}
     */
    protected function sendHttpRequest(string $method, string $url, ?string $payload, array $headers, int $timeoutSeconds): array
    {
        $ch = curl_init();
        if ($ch === false) {
            throw new SeqinoApiException('Unable to initialize curl request.');
        }

        $headerLines = array();
        foreach ($headers as $name => $value) {
            $headerLines[] = $name.': '.$value;
        }

        $curlOptions = array(
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headerLines,
        );

        if ($payload !== null) {
            $curlOptions[CURLOPT_POSTFIELDS] = $payload;
        }

        curl_setopt_array($ch, $curlOptions);
        $body = curl_exec($ch);

        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new SeqinoApiException('Transport error while calling Seqino API: '.$error);
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return array(
            'status_code' => $statusCode,
            'body' => (string) $body,
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSafeContext(string $method, string $url, int $statusCode, string $body): array
    {
        return array(
            'method' => $method,
            'url' => $url,
            'status_code' => $statusCode,
            'body_preview' => mb_substr($body, 0, 1000),
        );
    }
}
