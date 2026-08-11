import Layout from '../Layout';
import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

export default function Licenses({ licenses, filters, products }) {
    const { flash } = usePage().props;
    const [search, setSearch] = useState(filters.search || '');
    const [status, setStatus] = useState(filters.status || '');
    const [platform, setPlatform] = useState(filters.platform || '');

    const statusColors = {
        active: 'bg-green-100 text-green-800',
        suspended: 'bg-yellow-100 text-yellow-800',
        expired: 'bg-red-100 text-red-800',
        terminated: 'bg-gray-100 text-gray-800',
        pending: 'bg-blue-100 text-blue-800',
    };

    const platformLabels = {
        desktop: 'Desktop',
        hosting: 'Hosting',
        server: 'Server',
        android: 'Android',
    };

    const applyFilters = () => {
        router.get('/admin/licenses', { search, status, platform }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatus('');
        setPlatform('');
        router.get('/admin/licenses', {}, { preserveState: true });
    };

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold">Licenses</h1>
                <Link
                    href="/admin/licenses/create"
                    className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                >
                    + Create License
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

            {/* Filters */}
            <div className="bg-white rounded-lg shadow p-4 mb-4">
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search license key, customer..."
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    />
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="suspended">Suspended</option>
                        <option value="expired">Expired</option>
                        <option value="terminated">Terminated</option>
                        <option value="pending">Pending</option>
                    </select>
                    <select
                        value={platform}
                        onChange={(e) => setPlatform(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    >
                        <option value="">All Platforms</option>
                        {Object.entries(platformLabels).map(([value, label]) => (
                            <option key={value} value={value}>{label}</option>
                        ))}
                    </select>
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

            {/* Licenses table */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License Key</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Product</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activations</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Expires</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {licenses.data.map((license) => (
                            <tr key={license.id}>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <Link href={`/admin/licenses/${license.id}`} className="text-sm font-medium text-blue-600 hover:text-blue-900">
                                        {license.license_key}
                                    </Link>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {license.product?.name || '-'}
                                    <span className="ml-2 px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">
                                        {platformLabels[license.product?.platform] || '-'}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {license.customer_name || '-'}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className={`px-2 py-1 text-xs rounded-full ${statusColors[license.status]}`}>
                                        {license.status}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {license.current_activations} / {license.max_activations}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {license.expires_at ? new Date(license.expires_at).toLocaleDateString() : 'Never'}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <Link href={`/admin/licenses/${license.id}`} className="text-blue-600 hover:text-blue-900 mr-3">View</Link>
                                    <Link href={`/admin/licenses/${license.id}/edit`} className="text-green-600 hover:text-green-900 mr-3">Edit</Link>
                                    <button onClick={() => {
                                        if (confirm(`Delete license "${license.license_key}"?`)) {
                                            router.delete(`/admin/licenses/${license.id}`);
                                        }
                                    }} className="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        ))}
                        {licenses.data.length === 0 && (
                            <tr>
                                <td colSpan="7" className="px-6 py-4 text-center text-sm text-gray-500">No licenses found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {licenses.links && licenses.links.length > 3 && (
                <div className="mt-4 flex justify-center">
                    {licenses.links.map((link, i) => (
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