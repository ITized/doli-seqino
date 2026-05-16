<?php

declare(strict_types=1);

require_once __DIR__.'/SeqinoQueue.class.php';
require_once __DIR__.'/SeqinoSyncService.class.php';
require_once __DIR__.'/api/SeqinoPdpClient.class.php';

/**
 * Dolibarr cron entry points for Seqino.
 */
class SeqinoCronJobs
{
    private const DEFAULT_BATCH_SIZE = 50;

    private DoliDB $db;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function runOutbound(): int
    {
        return $this->runForType('outbound_invoice');
    }

    public function runInbound(): int
    {
        return $this->runForType('inbound_invoice');
    }

    public function runEreporting(): int
    {
        return $this->runForType('e_reporting');
    }

    private function runForType(string $payloadType): int
    {
        try {
            $service = $this->buildService();
            $batchSize = function_exists('getDolGlobalInt') ? getDolGlobalInt('SEQINO_CRON_BATCH_SIZE') : self::DEFAULT_BATCH_SIZE;
            if ($batchSize < 1) {
                $batchSize = self::DEFAULT_BATCH_SIZE;
            }
            $stats = $service->run($payloadType, $batchSize);

            if (function_exists('dol_syslog')) {
                dol_syslog('SeqinoCronJobs '.$payloadType.' processed='.$stats['processed'].' success='.$stats['success'].' error='.$stats['error']);
            }

            return (int) $stats['success'];
        } catch (Throwable $exception) {
            if (function_exists('dol_syslog')) {
                dol_syslog('SeqinoCronJobs '.$payloadType.' failed: '.$exception->getMessage(), LOG_ERR);
            }

            return 0;
        }
    }

    private function buildService(): SeqinoSyncService
    {
        $environment = function_exists('getDolGlobalString') ? getDolGlobalString('SEQINO_ENVIRONMENT') : 'sandbox';
        $token = function_exists('getDolGlobalString') ? getDolGlobalString('SEQINO_API_TOKEN') : '';
        $timeout = function_exists('getDolGlobalInt') ? getDolGlobalInt('SEQINO_API_TIMEOUT') : 30;

        $urls = array(
            'sandbox' => function_exists('getDolGlobalString') ? getDolGlobalString('SEQINO_API_BASE_URL_SANDBOX') : '',
            'production' => function_exists('getDolGlobalString') ? getDolGlobalString('SEQINO_API_BASE_URL_PRODUCTION') : '',
        );

        global $conf;
        $entity = isset($conf->entity) ? (int) $conf->entity : 1;

        $queue = new SeqinoQueue($this->db, $entity);
        $client = new SeqinoPdpClient($token, $environment !== '' ? $environment : 'sandbox', $urls, max(1, $timeout));

        return new SeqinoSyncService($queue, $client);
    }
}
