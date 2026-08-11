import Layout from '../Layout';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function Products({ products, filters }) {
    const { flash, errors } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState(filters.search || '');
    const [platform, setPlatform] = useState(filters.platform || '');
    const [status, setStatus] = useState(filters.status || '');
    const [form, setForm] = useState({
        name: '',
        platform: 'desktop',
        version: '',
        description: '',
        status: true,
    });

    const platformLabels = {
        desktop: 'Desktop',
        hosting: 'Hosting',
        server: 'Server',
        android: 'Android',
    };

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', platform: 'desktop', version: '', description: '', status: true });
        setShowModal(true);
    };

    const openEdit = (product) => {
        setEditing(product);
        setForm({
            name: product.name,
            platform: product.platform,
            version: product.version,
            description: product.description,
            status: product.status,
        });
        setShowModal(true);
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            router.put(`/admin/products/${editing.id}`, form);
        } else {
            router.post('/admin/products', form);
        }
        setShowModal(false);
    };

    const destroy = (product) => {
        if (confirm(`Delete product "${product.name}"?`)) {
            router.delete(`/admin/products/${product.id}`);
        }
    };

    const applyFilters = () => {
        router.get('/admin/products', { search, platform, status }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setPlatform('');
        setStatus('');
        router.get('/admin/products', {}, { preserveState: true });
    };

    const getFieldError = (field) => errors?.[field];

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold">Products</h1>
                <button
                    onClick={openCreate}
                    className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                >
                    + Add Product
                </button>
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
                        placeholder="Search product name..."
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    />
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
                    <select
                        value={status}
                        onChange={(e) => setStatus(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
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

            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Platform</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Version</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Licenses</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {products.data.map((product) => (
                            <tr key={product.id}>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{product.name}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <span className="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">
                                        {platformLabels[product.platform]}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{product.version || '-'}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{product.licenses_count}</td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className={`px-2 py-1 text-xs rounded-full ${product.status ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                                        {product.status ? 'Active' : 'Inactive'}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onClick={() => openEdit(product)} className="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                    <button onClick={() => destroy(product)} className="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        ))}
                        {products.data.length === 0 && (
                            <tr>
                                <td colSpan="6" className="px-6 py-4 text-center text-sm text-gray-500">No products found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {products.links && products.links.length > 3 && (
                <div className="mt-4 flex justify-center">
                    {products.links.map((link, i) => (
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

            {/* Modal */}
            {showModal && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 w-full max-w-lg">
                        <h2 className="text-xl font-bold mb-4">{editing ? 'Edit Product' : 'Add Product'}</h2>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                <input
                                    type="text"
                                    value={form.name}
                                    onChange={(e) => setForm({ ...form, name: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('name') ? 'border-red-500' : 'border-gray-300'}`}
                                    required
                                />
                                {getFieldError('name') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('name')}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Platform</label>
                                <select
                                    value={form.platform}
                                    onChange={(e) => setForm({ ...form, platform: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('platform') ? 'border-red-500' : 'border-gray-300'}`}
                                >
                                    {Object.entries(platformLabels).map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                {getFieldError('platform') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('platform')}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Version</label>
                                <input
                                    type="text"
                                    value={form.version}
                                    onChange={(e) => setForm({ ...form, version: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('version') ? 'border-red-500' : 'border-gray-300'}`}
                                />
                                {getFieldError('version') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('version')}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Description</label>
                                <textarea
                                    value={form.description}
                                    onChange={(e) => setForm({ ...form, description: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('description') ? 'border-red-500' : 'border-gray-300'}`}
                                    rows="3"
                                />
                                {getFieldError('description') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('description')}</p>
                                )}
                            </div>
                            <div className="flex items-center">
                                <input
                                    type="checkbox"
                                    checked={form.status}
                                    onChange={(e) => setForm({ ...form, status: e.target.checked })}
                                    className="h-4 w-4 text-blue-600 border-gray-300 rounded"
                                />
                                <label className="ml-2 text-sm text-gray-700">Active</label>
                            </div>
                            {getFieldError('status') && (
                                <p className="text-red-500 text-xs">{getFieldError('status')}</p>
                            )}
                            <div className="flex justify-end space-x-3">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    {editing ? 'Save Changes' : 'Create'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </Layout>
    );
}