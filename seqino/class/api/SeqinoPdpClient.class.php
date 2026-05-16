<?php

declare(strict_types=1);

require_once __DIR__.'/AbstractSeqinoApiClient.php';

/**
 * V1 Seqino PDP API client methods.
 */
class SeqinoPdpClient extends AbstractSeqinoApiClient
{
    /**
     * @param array<string,mixed> $invoicePayload
     *
     * @return array<string,mixed>
     */
    public function submitOutboundInvoice(array $invoicePayload): array
    {
        return $this->request('POST', '/api/invoices/outbound', $invoicePayload);
    }

    /**
     * @param array<string,mixed> $invoicePayload
     *
     * @return array<string,mixed>
     */
    public function createInboundInvoiceDraft(array $invoicePayload): array
    {
        return $this->request('POST', '/api/invoices/inbound/drafts', $invoicePayload);
    }

    /**
     * @param array<string,mixed> $reportingPayload
     *
     * @return array<string,mixed>
     */
    public function submitEreportingPayload(array $reportingPayload): array
    {
        return $this->request('POST', '/api/ereporting', $reportingPayload);
    }
}
