import Layout from '../Layout';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function Logs({ logs, filters }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [action, setAction] = useState(filters.action || '');
    const [from, setFrom] = useState(filters.from || '');
    const [to, setTo] = useState(filters.to || '');

    const actionLabels = {
        activate: 'Activate',
        verify: 'Verify',
        deactivate: 'Deactivate',
        auto_expire: 'Auto Expire',
        suspend: 'Suspend',
        terminate: 'Terminate',
        reactivate: 'Reactivate',
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

    const applyFilters = () => {
        router.get('/admin/logs', { search, action, from, to }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setAction('');
        setFrom('');
        setTo('');
        router.get('/admin/logs', {}, { preserveState: true });
    };

    return (
        <Layout>
            <h1 className="text-2xl font-bold mb-6">Activation Logs</h1>

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

            {/* Filters */}
            <div className="bg-white rounded-lg shadow p-4 mb-4">
                <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search license key..."
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    />
                    <select
                        value={action}
                        onChange={(e) => setAction(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    >
                        <option value="">All Actions</option>
                        {Object.entries(actionLabels).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
                    <input
                        type="date"
                        value={from}
                        onChange={(e) => setFrom(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    />
                    <input
                        type="date"
                        value={to}
                        onChange={(e) => setTo(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    />
                    <div className="flex space-x-2">
                        <button onClick={applyFilters} className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Filter
                        </button>
                        <button onClick={clearFilters} className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Clear
                        </button>
                    </div>
                </div>
            </div>

            {/* Logs table */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License Key</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {logs.data.map((log) => (
                            <tr key={log.id}>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {new Date(log.created_at).toLocaleString()}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {log.license_key}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className={`px-2 py-1 text-xs rounded-full ${actionColors[log.action] || 'bg-gray-100 text-gray-800'}`}>
                                        {actionLabels[log.action] || log.action}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {log.platform || '-'}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {log.ip_address || '-'}
                                </td>
                                <td className="px-6 py-4 text-sm text-gray-500">
                                    {log.notes || '-'}
                                </td>
                            </tr>
                        ))}
                        {logs.data.length === 0 && (
                            <tr>
                                <td colSpan="6" className="px-6 py-4 text-center text-sm text-gray-500">No logs found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {logs.links && logs.links.length > 3 && (
                <div className="mt-4 flex justify-center">
                    {logs.links.map((link, i) => (
                        <span key={i}>
                            {link.url ? (
                                <button
                                    onClick={() => router.get(link.url)}
                                    className={`mx-1 px-3 py-1 rounded text-sm ${link.active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 border'}`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ) : (
                                <span className="mx-1 px-3 py-1 rounded text-sm text-gray-400" dangerouslySetInnerHTML={{ __html: link.label }} />
                            )}
                        </span>
                    ))}
                </div>
            )}
        </Layout>
    );
}