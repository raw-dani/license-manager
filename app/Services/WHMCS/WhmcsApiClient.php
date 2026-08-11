<?php

namespace App\Services\WHMCS;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WHMCS API Client - Stub implementation.
 *
 * This is a bridge for future WHMCS billing integration.
 * It is NOT active by default and requires WHMCS credentials to be configured.
 */
class WhmcsApiClient
{
    protected ?string $url;
    protected ?string $identifier;
    protected ?string $secret;

    public function __construct()
    {
        $this->url = Setting::get('whmcs_url');
        $this->identifier = Setting::get('whmcs_api_identifier');
        $this->secret = Setting::get('whmcs_api_secret');
    }

    /**
     * Check if WHMCS integration is configured and enabled.
     */
    public function isEnabled(): bool
    {
        return Setting::get('whmcs_enabled', false)
            && !empty($this->url)
            && !empty($this->identifier)
            && !empty($this->secret);
    }

    /**
     * Make a request to the WHMCS API.
     */
    protected function call(string $action, array $params = []): array
    {
        if (!$this->isEnabled()) {
            throw new \RuntimeException('WHMCS integration is not configured');
        }

        $response = Http::asForm()->post($this->url . '/includes/api.php', array_merge([
            'identifier' => $this->identifier,
            'secret' => $this->secret,
            'action' => $action,
            'responsetype' => 'json',
        ], $params));

        if ($response->failed()) {
            Log::error('WHMCS API request failed', [
                'action' => $action,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('WHMCS API request failed');
        }

        return $response->json();
    }

    /**
     * Sync a license to WHMCS (create/update).
     */
    public function syncLicenseToWhmcs(array $licenseData): array
    {
        return $this->call('AddProduct', $licenseData);
    }

    /**
     * Check invoice status in WHMCS.
     */
    public function checkWhmcsInvoice(int $invoiceId): array
    {
        return $this->call('GetInvoice', ['invoiceid' => $invoiceId]);
    }

    /**
     * Handle WHMCS webhook callback.
     */
    public function handleWhmcsWebhook(array $payload): array
    {
        // Process webhook events (e.g., InvoicePaid, InvoiceUnpaid)
        $event = $payload['event'] ?? null;

        return [
            'event' => $event,
            'processed' => true,
        ];
    }

    /**
     * Push usage data to WHMCS.
     */
    public function pushUsageToWhmcs(array $usageData): array
    {
        return $this->call('UpdateClientProduct', $usageData);
    }
}