<?php

namespace App\Services\WHMCS;

use App\Models\ActivationLog;
use App\Models\License;
use App\Models\WhmcsSyncLog;
use Illuminate\Support\Facades\Log;

/**
 * WHMCS Billing Service - Stub implementation.
 *
 * Prepares the integration structure for WHMCS billing.
 * Can be activated later by configuring WHMCS credentials in settings.
 */
class WhmcsBillingService
{
    public function __construct(
        protected WhmcsApiClient $client
    ) {}

    /**
     * Sync a license to WHMCS when it's created/updated.
     */
    public function syncLicenseToWhmcs(License $license): void
    {
        if (!$this->client->isEnabled()) {
            return; // WHMCS not enabled, skip
        }

        try {
            $response = $this->client->syncLicenseToWhmcs([
                'name' => $license->license_key,
                'description' => $license->notes ?? 'License synced from License Manager',
            ]);

            $this->log($license, 'sync_license', 'success', [], $response);
        } catch (\Exception $e) {
            $this->log($license, 'sync_license', 'failed', [], [], $e->getMessage());
            Log::error('Failed to sync license to WHMCS', [
                'license_id' => $license->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check invoice status and update license status accordingly.
     */
    public function checkInvoiceStatus(License $license, int $invoiceId): void
    {
        if (!$this->client->isEnabled()) {
            return;
        }

        try {
            $response = $this->client->checkWhmcsInvoice($invoiceId);
            $status = $response['invoice']['status'] ?? null;

            if ($status === 'Paid') {
                $license->update(['status' => 'active']);
                ActivationLog::create([
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'action' => 'reactivate',
                    'notes' => 'License reactivated via WHMCS invoice payment',
                ]);
            } elseif (in_array($status, ['Unpaid', 'Overdue'])) {
                $license->update(['status' => 'suspended']);
                ActivationLog::create([
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'action' => 'suspend',
                    'notes' => 'License suspended due to unpaid WHMCS invoice',
                ]);
            }

            $this->log($license, 'check_invoice', 'success', ['invoice_id' => $invoiceId], $response);
        } catch (\Exception $e) {
            $this->log($license, 'check_invoice', 'failed', ['invoice_id' => $invoiceId], [], $e->getMessage());
        }
    }

    /**
     * Handle incoming WHMCS webhook.
     */
    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? null;

        switch ($event) {
            case 'InvoicePaid':
                // Find license by metadata whmcs_invoice_id and activate it
                $invoiceId = $payload['invoiceid'] ?? null;
                if ($invoiceId) {
                    $this->activateByWhmcsInvoiceId($invoiceId);
                }
                break;

            case 'InvoiceUnpaid':
                $invoiceId = $payload['invoiceid'] ?? null;
                if ($invoiceId) {
                    $this->suspendByWhmcsInvoiceId($invoiceId);
                }
                break;

            default:
                Log::info('Unhandled WHMCS webhook event', ['event' => $event, 'payload' => $payload]);
        }

        return [
            'event' => $event,
            'processed' => true,
        ];
    }

    /**
     * Push usage/activation data to WHMCS.
     */
    public function pushUsageToWhmcs(License $license): void
    {
        if (!$this->client->isEnabled()) {
            return;
        }

        try {
            $response = $this->client->pushUsageToWhmcs([
                'license_key' => $license->license_key,
                'activations' => $license->current_activations,
                'last_verified_at' => $license->last_verified_at?->toDateTimeString(),
            ]);

            $this->log($license, 'push_usage', 'success', [], $response);
        } catch (\Exception $e) {
            $this->log($license, 'push_usage', 'failed', [], [], $e->getMessage());
        }
    }

    /**
     * Activate licenses linked to a WHMCS invoice ID.
     */
    protected function activateByWhmcsInvoiceId(int $invoiceId): void
    {
        License::where('metadata->whmcs_invoice_id', $invoiceId)
            ->orWhere('metadata->whmcs_order_id', $invoiceId)
            ->get()
            ->each(function (License $license) {
                $license->update(['status' => 'active']);
            });
    }

    /**
     * Suspend licenses linked to a WHMCS invoice ID.
     */
    protected function suspendByWhmcsInvoiceId(int $invoiceId): void
    {
        License::where('metadata->whmcs_invoice_id', $invoiceId)
            ->orWhere('metadata->whmcs_order_id', $invoiceId)
            ->get()
            ->each(function (License $license) {
                $license->update(['status' => 'suspended']);
            });
    }

    /**
     * Log a WHMCS sync operation.
     */
    protected function log(License $license, string $action, string $status, array $requestData = [], array $responseData = [], ?string $error = null): void
    {
        WhmcsSyncLog::create([
            'license_id' => $license->id,
            'action' => $action,
            'status' => $status,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'error' => $error,
        ]);
    }
}