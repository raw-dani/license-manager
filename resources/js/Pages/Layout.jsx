import { Link, usePage, router } from '@inertiajs/react';
import { useState } from 'react';

const Icons = {
    Dashboard: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
        </svg>
    ),
    Products: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
        </svg>
    ),
    Licenses: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.874c-.748-.41-1.47-.91-2.122-1.495M9.75 13.125a3 3 0 11-6 0 3 3 0 016 0zm6.375 0a6 6 0 11-12 0 6 6 0 0112 0z" />
        </svg>
    ),
    Logs: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
        </svg>
    ),
    Settings: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    ),
    Users: () => (
        <svg className="w-5 h-5" fill="none" stroke="currentColor" strokeWidth="2" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
        </svg>
    ),
};

export default function Layout({ children }) {
    const { auth } = usePage().props;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [userDropdownOpen, setUserDropdownOpen] = useState(false);

    const navigation = [
        { name: 'Dashboard', href: '/admin', icon: Icons.Dashboard },
        { name: 'Products', href: '/admin/products', icon: Icons.Products },
        { name: 'Licenses', href: '/admin/licenses', icon: Icons.Licenses },
        { name: 'Logs', href: '/admin/logs', icon: Icons.Logs },
        { name: 'Settings', href: '/admin/settings', icon: Icons.Settings },
        { name: 'Users', href: '/admin/users', icon: Icons.Users },
    ];

    const handleLogout = (e) => {
        e.preventDefault();
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-gray-100">
            {/* Sidebar */}
            <aside className={`fixed inset-y-0 left-0 z-50 bg-gray-900 text-white transform transition-all duration-300 lg:translate-x-0 ${sidebarOpen ? 'translate-x-0' : '-translate-x-full'} ${sidebarCollapsed ? 'w-16' : 'w-64'}`}>
                <div className="flex items-center justify-between px-6 py-4 border-b border-gray-800">
                    {!sidebarCollapsed && <h1 className="text-xl font-bold">License Manager</h1>}
                    <div className="flex items-center space-x-2">
                        <button onClick={() => setSidebarCollapsed(!sidebarCollapsed)} className="hidden lg:block text-gray-400 hover:text-white">
                            {sidebarCollapsed ? '→' : '←'}
                        </button>
                        <button onClick={() => setSidebarOpen(false)} className="lg:hidden text-gray-400 hover:text-white">
                            ✕
                        </button>
                    </div>
                </div>
                <nav className="mt-4 px-4 space-y-1">
                    {navigation.map((item) => (
                        <Link
                            key={item.name}
                            href={item.href}
                            className="flex items-center px-4 py-3 text-sm font-medium rounded-lg hover:bg-gray-800 transition-colors"
                            title={sidebarCollapsed ? item.name : undefined}
                        >
                            <span className={`${sidebarCollapsed ? 'mx-auto' : 'mr-3'}`}><item.icon /></span>
                            {!sidebarCollapsed && <span>{item.name}</span>}
                        </Link>
                    ))}
                </nav>
            </aside>

            {/* Main content */}
            <div className="lg:pl-64">
                {/* Top bar */}
                <header className="bg-white shadow-sm sticky top-0 z-40">
                    <div className="flex items-center justify-between px-6 py-4">
                        <div className="flex items-center space-x-4">
                            <button onClick={() => setSidebarOpen(true)} className="lg:hidden text-gray-600">
                                ☰
                            </button>
                            <h2 className="text-lg font-semibold text-gray-800">
                                {usePage().component.replace('Pages/', '').replace('.jsx', '')}
                            </h2>
                        </div>
                        <div className="flex items-center space-x-4">
                            <div className="relative">
                                <button
                                    onClick={() => setUserDropdownOpen(!userDropdownOpen)}
                                    className="flex items-center space-x-2 text-sm text-gray-600 hover:text-gray-800"
                                >
                                    <div className="w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-semibold">
                                        {auth.user?.name?.charAt(0).toUpperCase()}
                                    </div>
                                    <span className="hidden md:block">{auth.user?.name}</span>
                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                {userDropdownOpen && (
                                    <div className="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2">
                                        <div className="px-4 py-2 border-b border-gray-200">
                                            <p className="text-sm font-medium text-gray-800">{auth.user?.name}</p>
                                            <p className="text-xs text-gray-500">{auth.user?.email}</p>
                                        </div>
                                        <Link
                                            href="/admin/settings"
                                            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                            onClick={() => setUserDropdownOpen(false)}
                                        >
                                            Settings
                                        </Link>
                                        <Link
                                            href="/admin/profile/password"
                                            className="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
                                            onClick={() => setUserDropdownOpen(false)}
                                        >
                                            Reset Password
                                        </Link>
                                        <button
                                            onClick={(e) => {
                                                setUserDropdownOpen(false);
                                                handleLogout(e);
                                            }}
                                            className="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-gray-100"
                                        >
                                            Logout
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </header>

                {/* Page content */}
                <main className={`p-6 transition-all duration-300 ${sidebarCollapsed ? 'lg:ml-16' : ''}`}>
                    {children}
                </main>
            </div>
        </div>
    );
}
