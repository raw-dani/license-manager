import Layout from './Layout';

export default function Dashboard({ stats, licensesByPlatform, recentLogs }) {
    const statCards = [
        { label: 'Total Licenses', value: stats.total_licenses, color: 'bg-blue-500' },
        { label: 'Active', value: stats.active_licenses, color: 'bg-green-500' },
        { label: 'Suspended', value: stats.suspended_licenses, color: 'bg-yellow-500' },
        { label: 'Expired', value: stats.expired_licenses, color: 'bg-red-500' },
        { label: 'Products', value: stats.total_products, color: 'bg-purple-500' },
    ];

    const platformLabels = {
        desktop: 'Desktop',
        hosting: 'Hosting',
        server: 'Server',
        android: 'Android',
    };

    return (
        <Layout>
            <h1 className="text-2xl font-bold mb-6">Dashboard</h1>

            {/* Stats cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
                {statCards.map((card) => (
                    <div key={card.label} className="bg-white rounded-lg shadow p-6">
                        <div className={`w-12 h-12 ${card.color} rounded-lg flex items-center justify-center text-white text-xl font-bold mb-3`}>
                            {card.value}
                        </div>
                        <p className="text-sm text-gray-600">{card.label}</p>
                    </div>
                ))}
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Licenses by platform */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h2 className="text-lg font-semibold mb-4">Licenses by Platform</h2>
                    <div className="space-y-3">
                        {Object.entries(licensesByPlatform).map(([platform, count]) => (
                            <div key={platform} className="flex items-center justify-between">
                                <span className="text-sm text-gray-600">
                                    {platformLabels[platform] || platform}
                                </span>
                                <div className="flex items-center space-x-3">
                                    <div className="w-32 bg-gray-200 rounded-full h-2">
                                        <div
                                            className="bg-blue-500 h-2 rounded-full"
                                            style={{ width: `${Math.min((count / stats.total_licenses) * 100, 100)}%` }}
                                        />
                                    </div>
                                    <span className="text-sm font-semibold">{count}</span>
                                </div>
                            </div>
                        ))}
                        {Object.keys(licensesByPlatform).length === 0 && (
                            <p className="text-sm text-gray-500">No licenses yet.</p>
                        )}
                    </div>
                </div>

                {/* Recent activity */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h2 className="text-lg font-semibold mb-4">Recent Activity</h2>
                    <div className="space-y-4">
                        {recentLogs.map((log) => (
                            <div key={log.id} className="flex items-start space-x-3">
                                <div className="flex-1">
                                    <p className="text-sm">
                                        <span className="font-semibold">{log.license_key}</span>
                                        <span className="text-gray-500"> - {log.action}</span>
                                    </p>
                                    <p className="text-xs text-gray-500">
                                        {log.created_at} • {log.ip_address || 'N/A'}
                                    </p>
                                </div>
                            </div>
                        ))}
                        {recentLogs.length === 0 && (
                            <p className="text-sm text-gray-500">No recent activity.</p>
                        )}
                    </div>
                </div>
            </div>
        </Layout>
    );
}