<?php

declare(strict_types=1);

require_once __DIR__.'/SeqinoQueue.class.php';
require_once __DIR__.'/api/SeqinoPdpClient.class.php';
require_once __DIR__.'/api/SeqinoApiException.php';

/**
 * V1 queue processor service.
 */
class SeqinoSyncService
{
    private SeqinoQueue $queue;

    private SeqinoPdpClient $client;

    public function __construct(SeqinoQueue $queue, SeqinoPdpClient $client)
    {
        $this->queue = $queue;
        $this->client = $client;
    }

    /**
     * @return array{processed:int,success:int,error:int}
     */
    public function run(string $payloadType, int $limit = 25): array
    {
        $rows = $this->queue->fetchQueuedByType($payloadType, $limit);
        $stats = array('processed' => 0, 'success' => 0, 'error' => 0);

        foreach ($rows as $row) {
            $stats['processed']++;
            try {
                $response = $this->dispatch($payloadType, $row['payload']);
                $externalId = '';
                if (isset($response['id']) && is_scalar($response['id'])) {
                    $externalId = (string) $response['id'];
                }
                $this->queue->markDone((int) $row['rowid'], $externalId);
                $this->incrementTokenUsageCounter();
                $stats['success']++;
            } catch (Throwable $exception) {
                $this->queue->markError((int) $row['rowid'], $exception->getMessage());
                $stats['error']++;
            }
        }

        return $stats;
    }

    /**
     * @param array<string,mixed> $payload
     *
     * @return array<string,mixed>
     */
    private function dispatch(string $payloadType, array $payload): array
    {
        if ($payloadType === 'outbound_invoice') {
            return $this->client->submitOutboundInvoice($payload);
        }

        if ($payloadType === 'inbound_invoice') {
            return $this->client->createInboundInvoiceDraft($payload);
        }

        if ($payloadType === 'e_reporting') {
            return $this->client->submitEreportingPayload($payload);
        }

        throw new InvalidArgumentException('Unsupported payload type: '.$payloadType);
    }

    private function incrementTokenUsageCounter(): void
    {
        if (!function_exists('getDolGlobalInt') || !function_exists('dolibarr_set_const')) {
            return;
        }

        global $db, $conf;

        $current = (int) getDolGlobalInt('SEQINO_TOKEN_USED_COUNT');
        dolibarr_set_const($db, 'SEQINO_TOKEN_USED_COUNT', (string) ($current + 1), 'integer', 0, '', $conf->entity);
    }
}
