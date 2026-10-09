<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'verify_ttl_hours' => Setting::get('verify_ttl_hours', 24),
                'grace_period_days' => Setting::get('grace_period_days', 7),
                'license_key_prefix' => Setting::get('license_key_prefix', 'SP-'),
                'api_enabled' => Setting::get('api_enabled', 1),
                'api_key' => Setting::get('api_key', ''),
                'whmcs_enabled' => Setting::get('whmcs_enabled', 0),
                'whmcs_url' => Setting::get('whmcs_url', ''),
                'whmcs_api_identifier' => Setting::get('whmcs_api_identifier', ''),
                'whmcs_api_secret' => Setting::get('whmcs_api_secret', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'verify_ttl_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:90'],
            'license_key_prefix' => ['required', 'string', 'max:10'],
            'api_enabled' => ['boolean'],
            'whmcs_enabled' => ['boolean'],
            'whmcs_url' => ['nullable', 'url'],
            'whmcs_api_identifier' => ['nullable', 'string', 'max:255'],
            'whmcs_api_secret' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function regenerateApiKey(): RedirectResponse
    {
        $newKey = bin2hex(random_bytes(32));
        Setting::set('api_key', $newKey);

        return back()->with('success', 'API key regenerated successfully.');
    }

    /**
     * Download file atau paket SDK
     */
    public function downloadSdk(string $type): mixed
    {
        $sdkBasePath = base_path('sdk');

        switch ($type) {
            case 'php-guard':
                $file = $sdkBasePath . '/php-panel/src/LicenseGuard.php';
                if (!file_exists($file)) {
                    abort(404, 'File LicenseGuard.php tidak ditemukan.');
                }
                return response()->download($file, 'LicenseGuard.php', [
                    'Content-Type' => 'application/x-php',
                ]);

            case 'php-client':
                $file = $sdkBasePath . '/php-panel/src/LicenseClient.php';
                if (!file_exists($file)) {
                    abort(404, 'File LicenseClient.php tidak ditemukan.');
                }
                return response()->download($file, 'LicenseClient.php', [
                    'Content-Type' => 'application/x-php',
                ]);

            case 'bash':
                $file = $sdkBasePath . '/server/license_check.sh';
                if (!file_exists($file)) {
                    abort(404, 'File license_check.sh tidak ditemukan.');
                }
                return response()->download($file, 'license_check.sh', [
                    'Content-Type' => 'text/x-shellscript',
                ]);

            case 'python-zip':
            case 'php-zip':
            case 'all-zip':
                $zipFile = storage_path('app/temp_' . $type . '_' . time() . '.zip');
                $zip = new \ZipArchive();

                if ($zip->open($zipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
                    abort(500, 'Gagal membuat file arsip zip.');
                }

                if ($type === 'php-zip') {
                    $sourceDir = $sdkBasePath . '/php-panel';
                    $this->addFolderToZip($zip, $sourceDir, 'php-sdk');
                    $guide = base_path('docs/INTEGRATION_GUIDE.md');
                    if (file_exists($guide)) {
                        $zip->addFile($guide, 'php-sdk/INTEGRATION_GUIDE.md');
                    }
                } elseif ($type === 'python-zip') {
                    $sourceDir = $sdkBasePath . '/python';
                    $this->addFolderToZip($zip, $sourceDir, 'python-sdk');
                } else {
                    $this->addFolderToZip($zip, $sdkBasePath, 'license-sdk');
                    $guide = base_path('docs/INTEGRATION_GUIDE.md');
                    if (file_exists($guide)) {
                        $zip->addFile($guide, 'license-sdk/INTEGRATION_GUIDE.md');
                    }
                }

                $zip->close();

                $filename = ($type === 'php-zip') ? 'license-php-sdk.zip' : (($type === 'python-zip') ? 'license-python-sdk.zip' : 'license-all-sdk.zip');

                return response()->download($zipFile, $filename)->deleteFileAfterSend(true);

            default:
                abort(404, 'Jenis SDK tidak dikenali.');
        }
    }

    private function addFolderToZip(\ZipArchive $zip, string $folder, string $zipPath = ''): void
    {
        if (!is_dir($folder)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folder, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen(realpath($folder)) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);
            $targetPath = $zipPath ? ($zipPath . '/' . $relativePath) : $relativePath;

            if ($file->isDir()) {
                $zip->addEmptyDir($targetPath);
            } elseif ($file->isFile()) {
                $zip->addFile($filePath, $targetPath);
            }
        }
    }
}