<?php
/**
 * License Client SDK for PHP (cPanel / SyberPanel)
 *
 * Integrasi lisensi aplikasi untuk hosting panel.
 *
 * Usage:
 *   $client = new LicenseClient('https://license-server.com', 'YOUR_API_KEY');
 *   $result = $client->activate('SP-XXXX-XXXX-XXXX', 'domain.com');
 */

class LicenseClient
{
    protected string $serverUrl;
    protected string $apiKey;
    protected ?string $token = null;

    public function __construct(string $serverUrl, string $apiKey)
    {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->apiKey = $apiKey;
    }

    /**
     * Set the license token after activation/verification.
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Get the stored token.
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * Generate a fingerprint from domain + cPanel username.
     */
    public function generateFingerprint(string $domain, ?string $username = null): string
    {
        return hash('sha256', $domain . '|' . ($username ?? ''));
    }

    /**
     * Activate a license for the given domain.
     *
     * @return array{status: string, code: int, message: string, data?: array}
     */
    public function activate(string $licenseKey, string $domain, ?string $username = null, array $deviceInfo = []): array
    {
        $fingerprint = $this->generateFingerprint($domain, $username);

        $response = $this->request('POST', '/api/v1/activate', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'platform' => 'hosting',
            'domain' => $domain,
            'device_info' => array_merge($deviceInfo, [
                'hosting_panel' => $this->detectPanel(),
                'username' => $username,
            ]),
        ]);

        if (isset($response['data']['token'])) {
            $this->token = $response['data']['token'];
        }

        return $response;
    }

    /**
     * Verify the license periodically.
     */
    public function verify(string $licenseKey, string $domain, ?string $username = null, array $deviceInfo = []): array
    {
        $fingerprint = $this->generateFingerprint($domain, $username);

        $response = $this->request('POST', '/api/v1/verify', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'platform' => 'hosting',
            'domain' => $domain,
            'device_info' => array_merge($deviceInfo, [
                'hosting_panel' => $this->detectPanel(),
                'username' => $username,
            ]),
        ]);

        if (isset($response['data']['token'])) {
            $this->token = $response['data']['token'];
        }

        return $response;
    }

    /**
     * Deactivate the license for this domain.
     */
    public function deactivate(string $licenseKey, string $domain, ?string $username = null): array
    {
        $fingerprint = $this->generateFingerprint($domain, $username);

        return $this->request('POST', '/api/v1/deactivate', [
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'platform' => 'hosting',
        ]);
    }

    /**
     * Check the license status.
     */
    public function status(string $licenseKey): array
    {
        return $this->request('GET', '/api/v1/license/' . urlencode($licenseKey));
    }

    /**
     * Ping the license server.
     */
    public function ping(): array
    {
        return $this->request('POST', '/api/v1/ping');
    }

    /**
     * Detect the hosting panel type.
     */
    protected function detectPanel(): string
    {
        if (defined('CPANEL')) {
            return 'cpanel';
        }
        if (function_exists('syberpanel_info')) {
            return 'syberpanel';
        }
        return 'unknown';
    }

    /**
     * Make an HTTP request to the license server.
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->serverUrl . $endpoint;

        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->token) {
            $headers[] = 'X-Authorization: ' . $this->token;
        }

        $ch = curl_init();

        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'status' => 'error',
                'code' => 0,
                'message' => 'Connection error: ' . $error,
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'code' => $httpCode,
                'message' => 'Invalid response from server',
            ];
        }

        return $decoded;
    }
}