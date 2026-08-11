import Layout from '../Layout';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function Settings({ settings }) {
    const { flash, errors } = usePage().props;
    const [showApiKey, setShowApiKey] = useState(false);
    const [form, setForm] = useState({
        verify_ttl_hours: settings.verify_ttl_hours,
        grace_period_days: settings.grace_period_days,
        license_key_prefix: settings.license_key_prefix,
        api_enabled: settings.api_enabled === '1' || settings.api_enabled === 1,
        whmcs_enabled: settings.whmcs_enabled === '1' || settings.whmcs_enabled === 1,
        whmcs_url: settings.whmcs_url,
        whmcs_api_identifier: settings.whmcs_api_identifier,
        whmcs_api_secret: settings.whmcs_api_secret,
    });

    const submit = (e) => {
        e.preventDefault();
        router.put('/admin/settings', form);
    };

    const regenerateApiKey = () => {
        if (confirm('Regenerate API key? This will invalidate all existing clients.')) {
            router.post('/admin/settings/regenerate-api-key');
        }
    };

    return (
        <Layout>
            <h1 className="text-2xl font-bold mb-6">Settings</h1>

            {flash.success && (
                <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {flash.success}
                </div>
            )}
            {flash.error && (
                <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {flash.error}
                </div>
            )}

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* License settings */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h2 className="text-lg font-semibold mb-4">License Settings</h2>
                    <form onSubmit={submit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Verify TTL (Hours)
                            </label>
                            <input
                                type="number"
                                min="1"
                                max="720"
                                value={form.verify_ttl_hours}
                                onChange={(e) => setForm({ ...form, verify_ttl_hours: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${errors?.verify_ttl_hours ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {errors?.verify_ttl_hours && (
                                <p className="text-red-500 text-xs mt-1">{errors.verify_ttl_hours}</p>
                            )}
                            <p className="text-xs text-gray-500 mt-1">How often clients must verify with the server.</p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Grace Period (Days)
                            </label>
                            <input
                                type="number"
                                min="0"
                                max="90"
                                value={form.grace_period_days}
                                onChange={(e) => setForm({ ...form, grace_period_days: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${errors?.grace_period_days ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {errors?.grace_period_days && (
                                <p className="text-red-500 text-xs mt-1">{errors.grace_period_days}</p>
                            )}
                            <p className="text-xs text-gray-500 mt-1">Days before stale activations are purged.</p>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                License Key Prefix
                            </label>
                            <input
                                type="text"
                                maxLength="10"
                                value={form.license_key_prefix}
                                onChange={(e) => setForm({ ...form, license_key_prefix: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${errors?.license_key_prefix ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {errors?.license_key_prefix && (
                                <p className="text-red-500 text-xs mt-1">{errors.license_key_prefix}</p>
                            )}
                        </div>

                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                checked={form.api_enabled}
                                onChange={(e) => setForm({ ...form, api_enabled: e.target.checked })}
                                className="h-4 w-4 text-blue-600 border-gray-300 rounded"
                            />
                            <label className="ml-2 text-sm text-gray-700">Enable API</label>
                        </div>

                        <button type="submit" className="w-full px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Save Settings
                        </button>
                    </form>
                </div>

                {/* API Key */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h2 className="text-lg font-semibold mb-4">API Key</h2>
                    <div className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Current API Key</label>
                            <div className="flex items-center space-x-2">
                                <code className="flex-1 px-3 py-2 bg-gray-100 border border-gray-300 rounded-md text-sm break-all">
                                    {showApiKey ? settings.api_key : settings.api_key ? settings.api_key.substring(0, 8) + '••••••••' : '-'}
                                </code>
                                <button
                                    type="button"
                                    onClick={() => setShowApiKey(!showApiKey)}
                                    className="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap cursor-pointer"
                                >
                                    {showApiKey ? 'Hide' : 'Show'}
                                </button>
                                <button
                                    onClick={regenerateApiKey}
                                    className="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm whitespace-nowrap cursor-pointer"
                                >
                                    Regenerate
                                </button>
                            </div>
                            <p className="text-xs text-gray-500 mt-1">
                                Clients send this via X-API-Key header. Regenerating invalidates all existing clients.
                            </p>
                        </div>
                    </div>
                </div>

                {/* WHMCS Bridge */}
                <div className="bg-white rounded-lg shadow p-6 lg:col-span-2">
                    <h2 className="text-lg font-semibold mb-4">WHMCS Billing Bridge</h2>
                    <p className="text-sm text-gray-500 mb-4">
                        Configure WHMCS integration for billing. This is optional and can be enabled later.
                    </p>
                    <form onSubmit={submit} className="space-y-4">
                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                checked={form.whmcs_enabled}
                                onChange={(e) => setForm({ ...form, whmcs_enabled: e.target.checked })}
                                className="h-4 w-4 text-blue-600 border-gray-300 rounded"
                            />
                            <label className="ml-2 text-sm text-gray-700">Enable WHMCS Integration</label>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">WHMCS URL</label>
                                <input
                                    type="url"
                                    value={form.whmcs_url}
                                    onChange={(e) => setForm({ ...form, whmcs_url: e.target.value })}
                                    placeholder="https://your-whmcs.com"
                                    className={`w-full px-3 py-2 border rounded-md ${errors?.whmcs_url ? 'border-red-500' : 'border-gray-300'}`}
                                />
                                {errors?.whmcs_url && (
                                    <p className="text-red-500 text-xs mt-1">{errors.whmcs_url}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">API Identifier</label>
                                <input
                                    type="text"
                                    value={form.whmcs_api_identifier}
                                    onChange={(e) => setForm({ ...form, whmcs_api_identifier: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${errors?.whmcs_api_identifier ? 'border-red-500' : 'border-gray-300'}`}
                                />
                                {errors?.whmcs_api_identifier && (
                                    <p className="text-red-500 text-xs mt-1">{errors.whmcs_api_identifier}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">API Secret</label>
                                <input
                                    type="password"
                                    value={form.whmcs_api_secret}
                                    onChange={(e) => setForm({ ...form, whmcs_api_secret: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${errors?.whmcs_api_secret ? 'border-red-500' : 'border-gray-300'}`}
                                />
                                {errors?.whmcs_api_secret && (
                                    <p className="text-red-500 text-xs mt-1">{errors.whmcs_api_secret}</p>
                                )}
                            </div>
                        </div>

                        <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Save WHMCS Settings
                        </button>
                    </form>
                </div>
            </div>
        </Layout>
    );
}