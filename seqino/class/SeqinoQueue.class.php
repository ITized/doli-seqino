<?php

declare(strict_types=1);

/**
 * Queue gateway for llx_seqino_queue.
 */
class SeqinoQueue
{
    private const ALLOWED_DIRECTIONS = array('inbound', 'outbound');

    private const ALLOWED_TYPES = array('outbound_invoice', 'inbound_invoice', 'e_reporting');
    private const MAX_ERROR_LENGTH = 1000;

    private DoliDB $db;

    private int $entity;

    public function __construct(DoliDB $db, int $entity)
    {
        $this->db = $db;
        $this->entity = $entity;
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function enqueue(string $direction, string $payloadType, string $payloadId, array $payload): int
    {
        $direction = $this->normalizeDirection($direction);
        $payloadType = $this->normalizeType($payloadType);
        $escapedPayload = $this->escape($this->encodePayload($payload));

        $sql = sprintf(
            "INSERT INTO %sseqino_queue(entity, direction, payload_type, payload_id, payload, status, retries, datec) VALUES (%d, '%s', '%s', '%s', '%s', 'queued', 0, '%s')",
            MAIN_DB_PREFIX,
            (int) $this->entity,
            $this->escape($direction),
            $this->escape($payloadType),
            $this->escape($payloadId),
            $escapedPayload,
            $this->db->idate(dol_now())
        );

        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to enqueue Seqino payload: '.$this->db->lasterror());
        }

        return (int) $this->db->last_insert_id(MAIN_DB_PREFIX.'seqino_queue');
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetchQueuedByType(string $payloadType, int $limit = 25): array
    {
        $payloadType = $this->normalizeType($payloadType);
        $limit = max(1, $limit);

        $sql = 'SELECT rowid, direction, payload_type, payload_id, payload, retries FROM '.MAIN_DB_PREFIX."seqino_queue WHERE entity = ".((int) $this->entity)." AND status = 'queued' AND payload_type = '".$this->escape($payloadType)."' ORDER BY datec ASC LIMIT ".$limit;
        $resql = $this->db->query($sql);
        if (!$resql) {
            throw new RuntimeException('Unable to fetch Seqino queue: '.$this->db->lasterror());
        }

        $items = array();
        while ($obj = $this->db->fetch_object($resql)) {
            $items[] = array(
                'rowid' => (int) $obj->rowid,
                'direction' => (string) $obj->direction,
                'payload_type' => (string) $obj->payload_type,
                'payload_id' => (string) $obj->payload_id,
                'payload' => $this->decodePayload((string) $obj->payload),
                'retries' => (int) $obj->retries,
            );
        }

        return $items;
    }

    public function markDone(int $rowid, string $externalId = ''): void
    {
        $externalIdClause = $externalId !== '' ? ", external_id = '".$this->escape($externalId)."'" : '';
        $sql = 'UPDATE '.MAIN_DB_PREFIX."seqino_queue SET status = 'done', last_error = NULL".$externalIdClause." WHERE entity = ".((int) $this->entity).' AND rowid = '.((int) $rowid);
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to mark Seqino queue row as done: '.$this->db->lasterror());
        }
    }

    public function markError(int $rowid, string $errorMessage): void
    {
        $errorMessage = trim($errorMessage);
        if (strlen($errorMessage) > self::MAX_ERROR_LENGTH) {
            $errorMessage = substr($errorMessage, 0, self::MAX_ERROR_LENGTH - 12).' [truncated]';
        }
        $sql = 'UPDATE '.MAIN_DB_PREFIX."seqino_queue SET status = 'error', retries = retries + 1, last_error = '".$this->escape($errorMessage)."' WHERE entity = ".((int) $this->entity).' AND rowid = '.((int) $rowid);
        if (!$this->db->query($sql)) {
            throw new RuntimeException('Unable to mark Seqino queue row in error: '.$this->db->lasterror());
        }
    }

    private function normalizeDirection(string $direction): string
    {
        $direction = strtolower(trim($direction));
        if (!in_array($direction, self::ALLOWED_DIRECTIONS, true)) {
            throw new InvalidArgumentException('Unsupported queue direction: '.$direction);
        }

        return $direction;
    }

    private function normalizeType(string $payloadType): string
    {
        $payloadType = strtolower(trim($payloadType));
        if (!in_array($payloadType, self::ALLOWED_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported queue payload type: '.$payloadType);
        }

        return $payloadType;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string,mixed>
     */
    private function decodePayload(string $payload): array
    {
        if ($payload === '') {
            return array();
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : array();
    }

    private function escape(string $value): string
    {
        return $this->db->escape($value);
    }
}
