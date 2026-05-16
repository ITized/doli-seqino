<?php

declare(strict_types=1);

require_once __DIR__.'/../seqino/class/api/AbstractSeqinoApiClient.php';

final class FakeSeqinoClient extends AbstractSeqinoApiClient
{
    /** @var array{method:string,url:string,payload:?string,headers:array<string,string>,timeout:int}|null */
    public ?array $lastRequest = null;

    /** @var array{status_code:int,body:string} */
    public array $nextResponse = array('status_code' => 200, 'body' => '{}');

    public function call(string $method, string $path, ?array $body = null, array $headers = array()): array
    {
        return $this->request($method, $path, $body, $headers);
    }

    protected function sendHttpRequest(string $method, string $url, ?string $payload, array $headers, int $timeoutSeconds): array
    {
        $this->lastRequest = array(
            'method' => $method,
            'url' => $url,
            'payload' => $payload,
            'headers' => $headers,
            'timeout' => $timeoutSeconds,
        );

        return $this->nextResponse;
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' Expected: '.var_export($expected, true).' Got: '.var_export($actual, true));
    }
}

$client = new FakeSeqinoClient(
    'my-token',
    'sandbox',
    array(
        'sandbox' => 'https://pdp-sandbox.seqino.dev',
        'production' => 'https://pdp-api.seqino.dev',
    ),
    20
);

$client->nextResponse = array('status_code' => 200, 'body' => '{"ok":true}');
$result = $client->call('post', '/invoices', array('id' => 'INV-001'));

assertSame(true, $result['ok'], 'Response should decode JSON.');
assertSame('POST', $client->lastRequest['method'], 'Method must be uppercased.');
assertSame('https://pdp-sandbox.seqino.dev/invoices', $client->lastRequest['url'], 'URL must use sandbox base URL and normalized path.');
assertSame('{"id":"INV-001"}', $client->lastRequest['payload'], 'Payload must be JSON encoded.');
assertSame('Bearer my-token', $client->lastRequest['headers']['Authorization'], 'Authorization header must include bearer token.');

$client->nextResponse = array('status_code' => 204, 'body' => '');
$empty = $client->call('GET', '/status');
assertSame(array(), $empty, 'Empty success body must return empty array.');

$client->nextResponse = array('status_code' => 400, 'body' => '{"message":"bad request"}');
$failed = false;
try {
    $client->call('GET', '/status');
} catch (SeqinoApiException $exception) {
    $failed = true;
    assertTrue(str_contains($exception->getMessage(), 'bad request'), 'Exception message should include API message.');
    assertSame(400, $exception->getCode(), 'HTTP status must be used as exception code.');
}
assertTrue($failed, '4xx response must throw SeqinoApiException.');

echo "AbstractSeqinoApiClientTest: OK\n";
