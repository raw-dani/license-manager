import Layout from '../Layout';
import { Link, router, usePage } from '@inertiajs/react';

export default function ShowLicense({ license }) {
    const { flash } = usePage().props;

    const statusColors = {
        active: 'bg-green-100 text-green-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        expired: 'bg-red-100 text-red-800',
        terminated: 'bg-gray-100 text-gray-800',
        pending: 'bg-blue-100 text-blue-800',
    };

    const actionColors = {
        activate: 'bg-green-100 text-green-800',
        verify: 'bg-blue-100 text-blue-800',
        deactivate: 'bg-yellow-100 text-yellow-800',
        auto_expire: 'bg-red-100 text-red-800',
        suspend: 'bg-orange-100 text-orange-800',
        terminate: 'bg-gray-100 text-gray-800',
        reactivate: 'bg-purple-100 text-purple-800',
    };

    const platformLabels = {
        desktop: 'Desktop',
        hosting: 'Hosting',
        server: 'Server',
        android: 'Android',
    };

    const actionLabels = {
        activate: 'Activate',
        verify: 'Verify',
        deactivate: 'Deactivate',
        auto_expire: 'Auto Expire',
        suspend: 'Suspend',
        terminate: 'Terminate',
        reactivate: 'Reactivate',
    };

    const suspend = () => {
        if (confirm('Suspend this license?')) {
            router.post(`/admin/licenses/${license.id}/suspend`);
        }
    };

    const activate = () => {
        if (confirm('Activate this license?')) {
            router.post(`/admin/licenses/${license.id}/activate`);
        }
    };

    const terminate = () => {
        if (confirm('Terminate this license?')) {
            router.post(`/admin/licenses/${license.id}/terminate`);
        }
    };

    const destroy = () => {
        if (confirm('Delete this license permanently?')) {
            router.delete(`/admin/licenses/${license.id}`);
        }
    };

    const edit = () => {
        router.get(`/admin/licenses/${license.id}/edit`);
    };

    const revokeActivation = (activationId) => {
        if (confirm('Revoke this device activation? This will free up one activation slot.')) {
            router.delete(`/admin/licenses/${license.id}/activations/${activationId}`);
        }
    };

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold">{license.license_key}</h1>
                    <p className="text-sm text-gray-500">{license.product?.name}</p>
                </div>
                <Link href="/admin/licenses" className="text-blue-600 hover:text-blue-900">
                    ← Back to Licenses
                </Link>
            </div>

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

            {/* Actions */}
            <div className="flex space-x-3 mb-6">
                {license.status === 'active' && (
                    <button onClick={suspend} className="px-4 py-2 bg-yellow-500 text-white rounded-md hover:bg-yellow-600">
                        Suspend
                    </button>
                )}
                {license.status === 'suspended' && (
                    <button onClick={activate} className="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Activate
                    </button>
                )}
                {license.status !== 'terminated' && (
                    <button onClick={terminate} className="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700">
                        Terminate
                    </button>
                )}
                <button onClick={edit} className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                    Edit
                </button>
                <button onClick={destroy} className="px-4 py-2 border border-red-300 text-red-600 rounded-md hover:bg-red-50">
                    Delete
                </button>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* License details */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h2 className="text-lg font-semibold mb-4">License Details</h2>
                    <dl className="space-y-3">
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Status</dt>
                            <dd>
                                <span className={`px-2 py-1 text-xs rounded-full ${statusColors[license.status]}`}>
                                    {license.status}
                                </span>
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Product</dt>
                            <dd className="text-sm">{license.product?.name}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Platform</dt>
                            <dd className="text-sm">{platformLabels[license.product?.platform]}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Customer</dt>
                            <dd className="text-sm">{license.customer_name || '-'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Email</dt>
                            <dd className="text-sm">{license.customer_email || '-'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Activations</dt>
                            <dd className="text-sm">{license.current_activations} / {license.max_activations}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Activated At</dt>
                            <dd className="text-sm">{license.activated_at ? new Date(license.activated_at).toLocaleString() : '-'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Expires At</dt>
                            <dd className="text-sm">{license.expires_at ? new Date(license.expires_at).toLocaleString() : 'Never'}</dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-sm text-gray-500">Last Verified</dt>
                            <dd className="text-sm">{license.last_verified_at ? new Date(license.last_verified_at).toLocaleString() : '-'}</dd>
                        </div>
                        {license.status === 'suspended' && (
                            <div className="flex justify-between">
                                <dt className="text-sm text-gray-500">Suspended At</dt>
                                <dd className="text-sm text-red-600">{license.suspended_at ? new Date(license.suspended_at).toLocaleString() : '-'}</dd>
                            </div>
                        )}
                        {license.notes && (
                            <div>
                                <dt className="text-sm text-gray-500 mb-1">Notes</dt>
                                <dd className="text-sm bg-gray-50 p-3 rounded">{license.notes}</dd>
                            </div>
                        )}
                    </dl>
                </div>

                {/* Activations */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h2 className="text-lg font-semibold mb-4">Device Activations</h2>
                    <div className="space-y-3">
                        {license.activations.map((activation) => (
                            <div key={activation.id} className="border border-gray-200 rounded-lg p-4">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium">{platformLabels[activation.platform]}</span>
                                    <span className={`px-2 py-0.5 text-xs rounded-full ${activation.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                        {activation.status}
                                    </span>
                                </div>
                                <p className="text-xs text-gray-500 font-mono break-all mb-1">{activation.fingerprint}</p>
                                {activation.domain && <p className="text-xs text-gray-500">Domain: {activation.domain}</p>}
                                {activation.ip_address && <p className="text-xs text-gray-500">IP: {activation.ip_address}</p>}
                                <p className="text-xs text-gray-400 mt-1">
                                    Last verified: {activation.last_verified_at ? new Date(activation.last_verified_at).toLocaleString() : 'Never'}
                                </p>
                                <div className="mt-3 pt-3 border-t border-gray-100 flex justify-end">
                                    <button
                                        onClick={() => revokeActivation(activation.id)}
                                        className="text-xs text-red-600 hover:text-red-900"
                                    >
                                        Revoke Activation
                                    </button>
                                </div>
                            </div>
                        ))}
                        {license.activations.length === 0 && (
                            <p className="text-sm text-gray-500">No device activations yet.</p>
                        )}
                    </div>
                </div>
            </div>

            {/* Activity log */}
            <div className="bg-white rounded-lg shadow p-6 mt-6">
                <h2 className="text-lg font-semibold mb-4">Activity Log</h2>
                <div className="overflow-hidden border border-gray-200 rounded-lg">
                    <table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50">
                            <tr>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                                <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP</th>
                            </tr>
                        </thead>
                        <tbody className="bg-white divide-y divide-gray-200">
                            {license.logs.map((log) => (
                                <tr key={log.id}>
                                    <td className="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                        {new Date(log.created_at).toLocaleString()}
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap">
                                        <span className={`px-2 py-0.5 text-xs rounded-full ${actionColors[log.action] || 'bg-gray-100 text-gray-800'}`}>
                                            {actionLabels[log.action] || log.action}
                                        </span>
                                    </td>
                                    <td className="px-4 py-2 text-xs text-gray-600 max-w-xs truncate">
                                        {log.notes || '-'}
                                    </td>
                                    <td className="px-4 py-2 whitespace-nowrap text-xs text-gray-500">
                                        {log.ip_address || '-'}
                                    </td>
                                </tr>
                            ))}
                            {license.logs.length === 0 && (
                                <tr>
                                    <td colSpan="4" className="px-4 py-4 text-center text-sm text-gray-500">No activity recorded.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </Layout>
    );
}