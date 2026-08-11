import Layout from '../Layout';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function ChangePassword() {
    const { flash, errors } = usePage().props;
    const [form, setForm] = useState({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        router.put('/admin/profile/password', form);
    };

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold">Change Password</h1>
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
                        <label className="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input
                            type="password"
                            value={form.current_password}
                            onChange={(e) => setForm({ ...form, current_password: e.target.value })}
                            className={`w-full px-3 py-2 border rounded-md ${errors?.current_password ? 'border-red-500' : 'border-gray-300'}`}
                            required
                        />
                        {errors?.current_password && (
                            <p className="text-red-500 text-xs mt-1">{errors.current_password}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input
                            type="password"
                            value={form.password}
                            onChange={(e) => setForm({ ...form, password: e.target.value })}
                            className={`w-full px-3 py-2 border rounded-md ${errors?.password ? 'border-red-500' : 'border-gray-300'}`}
                            required
                        />
                        {errors?.password && (
                            <p className="text-red-500 text-xs mt-1">{errors.password}</p>
                        )}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input
                            type="password"
                            value={form.password_confirmation}
                            onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
                            className={`w-full px-3 py-2 border rounded-md ${errors?.password_confirmation ? 'border-red-500' : 'border-gray-300'}`}
                            required
                        />
                        {errors?.password_confirmation && (
                            <p className="text-red-500 text-xs mt-1">{errors.password_confirmation}</p>
                        )}
                    </div>

                    <div className="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            onClick={() => router.get('/admin/settings')}
                            className="px-4 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50"
                        >
                            Cancel
                        </button>
                        <button type="submit" className="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </Layout>
    );
}
