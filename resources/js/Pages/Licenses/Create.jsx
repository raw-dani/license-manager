import Layout from '../Layout';
import { useState } from 'react';
import { Link, router, usePage } from '@inertiajs/react';

export default function CreateLicense({ products, generated_key }) {
    const { flash, errors } = usePage().props;
    const [form, setForm] = useState({
        license_key: generated_key,
        product_id: products[0]?.id || '',
        customer_name: '',
        customer_email: '',
        max_activations: 1,
        expires_at: '',
        notes: '',
    });

    const platformLabels = {
        desktop: 'Desktop',
        hosting: 'Hosting',
        server: 'Server',
        android: 'Android',
    };

    const getFieldError = (field) => errors?.[field];

    const submit = (e) => {
        e.preventDefault();
        router.post('/admin/licenses', form);
    };

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold">Create License</h1>
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

            <div className="bg-white rounded-lg shadow p-6 max-w-2xl">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">License Key</label>
                        <div className="flex space-x-2">
                            <input
                                type="text"
                                value={form.license_key}
                                onChange={(e) => setForm({ ...form, license_key: e.target.value })}
                                className={`flex-1 px-3 py-2 border rounded-md font-mono ${getFieldError('license_key') ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            />
                            <button
                                type="button"
                                onClick={async () => {
                                    try {
                                        const response = await fetch('/admin/licenses/generate-key');
                                        const data = await response.json();
                                        setForm({ ...form, license_key: data.key });
                                    } catch (error) {
                                        console.error('Failed to generate key:', error);
                                    }
                                }}
                                className="px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                            >
                                Regenerate
                            </button>
                        </div>
                        {getFieldError('license_key') && (
                            <p className="text-red-500 text-xs mt-1">{getFieldError('license_key')}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Product</label>
                        <select
                            value={form.product_id}
                            onChange={(e) => setForm({ ...form, product_id: e.target.value })}
                            className={`w-full px-3 py-2 border rounded-md ${getFieldError('product_id') ? 'border-red-500' : 'border-gray-300'}`}
                            required
                        >
                            {products.map((product) => (
                                <option key={product.id} value={product.id}>
                                    {product.name} ({platformLabels[product.platform]})
                                </option>
                            ))}
                        </select>
                        {getFieldError('product_id') && (
                            <p className="text-red-500 text-xs mt-1">{getFieldError('product_id')}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Customer Name</label>
                            <input
                                type="text"
                                value={form.customer_name}
                                onChange={(e) => setForm({ ...form, customer_name: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${getFieldError('customer_name') ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {getFieldError('customer_name') && (
                                <p className="text-red-500 text-xs mt-1">{getFieldError('customer_name')}</p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Customer Email</label>
                            <input
                                type="email"
                                value={form.customer_email}
                                onChange={(e) => setForm({ ...form, customer_email: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${getFieldError('customer_email') ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {getFieldError('customer_email') && (
                                <p className="text-red-500 text-xs mt-1">{getFieldError('customer_email')}</p>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Max Activations</label>
                            <input
                                type="number"
                                min="1"
                                max="100"
                                value={form.max_activations}
                                onChange={(e) => setForm({ ...form, max_activations: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${getFieldError('max_activations') ? 'border-red-500' : 'border-gray-300'}`}
                                required
                            />
                            {getFieldError('max_activations') && (
                                <p className="text-red-500 text-xs mt-1">{getFieldError('max_activations')}</p>
                            )}
                        </div>
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">Expires At</label>
                            <input
                                type="date"
                                value={form.expires_at}
                                onChange={(e) => setForm({ ...form, expires_at: e.target.value })}
                                className={`w-full px-3 py-2 border rounded-md ${getFieldError('expires_at') ? 'border-red-500' : 'border-gray-300'}`}
                            />
                            {getFieldError('expires_at') && (
                                <p className="text-red-500 text-xs mt-1">{getFieldError('expires_at')}</p>
                            )}
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                        <textarea
                            value={form.notes}
                            onChange={(e) => setForm({ ...form, notes: e.target.value })}
                            className={`w-full px-3 py-2 border rounded-md ${getFieldError('notes') ? 'border-red-500' : 'border-gray-300'}`}
                            rows="3"
                        />
                        {getFieldError('notes') && (
                            <p className="text-red-500 text-xs mt-1">{getFieldError('notes')}</p>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3 pt-4">
                        <Link href="/admin/licenses" className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancel
                        </Link>
                        <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Create License
                        </button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}