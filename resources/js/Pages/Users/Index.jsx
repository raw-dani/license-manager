import Layout from '../Layout';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';

export default function Users({ users, roles, filters }) {
    const { flash, errors } = usePage().props;
    const [showModal, setShowModal] = useState(false);
    const [editing, setEditing] = useState(null);
    const [search, setSearch] = useState(filters.search || '');
    const [role, setRole] = useState(filters.role || '');
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        role: roles[0]?.name || 'admin',
    });

    const openCreate = () => {
        setEditing(null);
        setForm({ name: '', email: '', password: '', role: roles[0]?.name || 'admin' });
        setShowModal(true);
    };

    const openEdit = (user) => {
        setEditing(user);
        setForm({
            name: user.name,
            email: user.email,
            password: '',
            role: user.roles[0]?.name || 'admin',
        });
        setShowModal(true);
    };

    const submit = (e) => {
        e.preventDefault();
        if (editing) {
            router.put(`/admin/users/${editing.id}`, form);
        } else {
            router.post('/admin/users', form);
        }
        setShowModal(false);
    };

    const destroy = (user) => {
        if (confirm(`Delete user "${user.name}"?`)) {
            router.delete(`/admin/users/${user.id}`);
        }
    };

    const applyFilters = () => {
        router.get('/admin/users', { search, role }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setRole('');
        router.get('/admin/users', {}, { preserveState: true });
    };

    const getFieldError = (field) => errors?.[field];

    return (
        <Layout>
            <div className="flex items-center justify-between mb-6">
                <h1 className="text-2xl font-bold">Users</h1>
                <button
                    onClick={openCreate}
                    className="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700"
                >
                    + Add User
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
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <input
                        type="text"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search name or email..."
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    />
                    <select
                        value={role}
                        onChange={(e) => setRole(e.target.value)}
                        className="px-3 py-2 border border-gray-300 rounded-md"
                    >
                        <option value="">All Roles</option>
                        {roles.map((r) => (
                            <option key={r.id} value={r.name}>{r.name}</option>
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

            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {users.data.map((user) => (
                            <tr key={user.id}>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{user.name}</td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{user.email}</td>
                                <td className="px-6 py-4 whitespace-nowrap">
                                    <span className="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">
                                        {user.roles[0]?.name || 'No role'}
                                    </span>
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {new Date(user.created_at).toLocaleDateString()}
                                </td>
                                <td className="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onClick={() => openEdit(user)} className="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                    <button onClick={() => destroy(user)} className="text-red-600 hover:text-red-900">Delete</button>
                                </td>
                            </tr>
                        ))}
                        {users.data.length === 0 && (
                            <tr>
                                <td colSpan="5" className="px-6 py-4 text-center text-sm text-gray-500">No users found.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* Pagination */}
            {users.links && users.links.length > 3 && (
                <div className="mt-4 flex justify-center">
                    {users.links.map((link, i) => (
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
                        <h2 className="text-xl font-bold mb-4">{editing ? 'Edit User' : 'Add User'}</h2>
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
                                <label className="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                <input
                                    type="email"
                                    value={form.email}
                                    onChange={(e) => setForm({ ...form, email: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('email') ? 'border-red-500' : 'border-gray-300'}`}
                                    required
                                />
                                {getFieldError('email') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('email')}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Password {editing && '(leave blank to keep current)'}
                                </label>
                                <input
                                    type="password"
                                    value={form.password}
                                    onChange={(e) => setForm({ ...form, password: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('password') ? 'border-red-500' : 'border-gray-300'}`}
                                    required={!editing}
                                />
                                {getFieldError('password') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('password')}</p>
                                )}
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">Role</label>
                                <select
                                    value={form.role}
                                    onChange={(e) => setForm({ ...form, role: e.target.value })}
                                    className={`w-full px-3 py-2 border rounded-md ${getFieldError('role') ? 'border-red-500' : 'border-gray-300'}`}
                                >
                                    {roles.map((role) => (
                                        <option key={role.id} value={role.name}>{role.name}</option>
                                    ))}
                                </select>
                                {getFieldError('role') && (
                                    <p className="text-red-500 text-xs mt-1">{getFieldError('role')}</p>
                                )}
                            </div>
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