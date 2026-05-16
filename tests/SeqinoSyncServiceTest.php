<?php

declare(strict_types=1);

require_once __DIR__.'/../seqino/class/SeqinoSyncService.class.php';

final class FakeQueueForSync extends SeqinoQueue
{
    /** @var array<int,array<string,mixed>> */
    public array $items = array();

    /** @var int[] */
    public array $done = array();

    /** @var array<int,string> */
    public array $errors = array();

    public function __construct()
    {
    }

    public function fetchQueuedByType(string $payloadType, int $limit = 25): array
    {
        $filtered = array_values(array_filter($this->items, static function (array $item) use ($payloadType): bool {
            return $item['payload_type'] === $payloadType;
        }));

        return array_slice($filtered, 0, max(1, $limit));
    }

    public function markDone(int $rowid, string $externalId = ''): void
    {
        $this->done[] = $rowid;
    }

    public function markError(int $rowid, string $errorMessage): void
    {
        $this->errors[$rowid] = $errorMessage;
    }
}

final class FakeClientForSync extends SeqinoPdpClient
{
    /** @var array<string,mixed> */
    public array $responses = array();

    /** @var string[] */
    public array $called = array();

    public function __construct()
    {
    }

    public function submitOutboundInvoice(array $invoicePayload): array
    {
        $this->called[] = 'outbound';

        return $this->next('outbound');
    }

    public function createInboundInvoiceDraft(array $invoicePayload): array
    {
        $this->called[] = 'inbound';

        return $this->next('inbound');
    }

    public function submitEreportingPayload(array $reportingPayload): array
    {
        $this->called[] = 'ereporting';

        return $this->next('ereporting');
    }

    /**
     * @return array<string,mixed>
     */
    private function next(string $key): array
    {
        if (!array_key_exists($key, $this->responses)) {
            return array('id' => 'ok-'.$key);
        }

        $value = $this->responses[$key];
        if ($value instanceof Throwable) {
            throw $value;
        }

        return is_array($value) ? $value : array();
    }
}

function assertTrueOrFail(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function assertSameOrFail(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message.' Expected: '.var_export($expected, true).' Got: '.var_export($actual, true));
    }
}

$queue = new FakeQueueForSync();
$queue->items = array(
    array('rowid' => 1, 'payload_type' => 'outbound_invoice', 'payload' => array('a' => 1)),
    array('rowid' => 2, 'payload_type' => 'outbound_invoice', 'payload' => array('b' => 2)),
    array('rowid' => 3, 'payload_type' => 'inbound_invoice', 'payload' => array('c' => 3)),
);

$client = new FakeClientForSync();
$client->responses['outbound'] = array('id' => 'ext-1');

$service = new SeqinoSyncService($queue, $client);
$stats = $service->run('outbound_invoice', 25);
assertSameOrFail(2, $stats['processed'], 'Should process all outbound rows.');
assertSameOrFail(2, $stats['success'], 'All outbound rows should succeed.');
assertSameOrFail(0, $stats['error'], 'No outbound errors expected.');
assertSameOrFail(array(1, 2), $queue->done, 'Outbound rows should be marked done.');

$queue2 = new FakeQueueForSync();
$queue2->items = array(
    array('rowid' => 10, 'payload_type' => 'e_reporting', 'payload' => array('x' => 1)),
);
$client2 = new FakeClientForSync();
$client2->responses['ereporting'] = new RuntimeException('Simulated transport failure');

$service2 = new SeqinoSyncService($queue2, $client2);
$stats2 = $service2->run('e_reporting', 25);
assertSameOrFail(1, $stats2['processed'], 'Should process one e-reporting row.');
assertSameOrFail(0, $stats2['success'], 'E-reporting should fail in this scenario.');
assertSameOrFail(1, $stats2['error'], 'One e-reporting error expected.');
assertTrueOrFail(isset($queue2->errors[10]), 'Error row should be marked in queue.');

echo "SeqinoSyncServiceTest: OK\n";
