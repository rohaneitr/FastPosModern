import React from 'react';

export default function SuperadminMonitoring() {
  const systemMetrics = [
    { label: 'API Uptime', value: '99.97%', status: 'healthy', icon: '🟢' },
    { label: 'Avg Response Time', value: '142ms', status: 'healthy', icon: '⚡' },
    { label: 'Active Connections', value: '2,841', status: 'healthy', icon: '🔗' },
    { label: 'Error Rate (24h)', value: '0.03%', status: 'healthy', icon: '✅' },
  ];

  const recentEvents = [
    { time: '2 min ago', event: 'Tenant "TechHaven Retail" performed database backup', type: 'info' },
    { time: '18 min ago', event: 'New tenant registration: "Local Cafe Co."', type: 'success' },
    { time: '1 hr ago', event: 'Rate limit triggered on API gateway (IP: 203.0.113.42)', type: 'warning' },
    { time: '3 hrs ago', event: 'SSL certificate renewal completed successfully', type: 'success' },
    { time: '6 hrs ago', event: 'Scheduled maintenance window completed', type: 'info' },
  ];

  return (
    <div className="flex flex-col gap-8 animate-in fade-in duration-500 pb-12">
      <div>
        <h1 className="text-3xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-rose-400 to-orange-500">
          System Monitoring
        </h1>
        <p className="text-text-muted mt-1">Real-time platform health and system events.</p>
      </div>

      {/* System Health */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {systemMetrics.map((metric) => (
          <div key={metric.label} className="bg-surface/30 border border-border p-6 rounded-2xl">
            <div className="flex items-center justify-between mb-2">
              <p className="text-text-muted text-sm font-medium">{metric.label}</p>
              <span className="text-lg">{metric.icon}</span>
            </div>
            <h2 className="text-3xl font-bold text-white">{metric.value}</h2>
          </div>
        ))}
      </div>

      {/* Recent Events */}
      <div className="bg-surface/30 border border-border rounded-2xl p-6">
        <h3 className="text-xl font-bold text-white mb-4">System Event Log</h3>
        <div className="flex flex-col gap-3">
          {recentEvents.map((evt, i) => (
            <div key={i} className="flex items-start gap-4 p-3 rounded-lg hover:bg-surface/30 transition-colors">
              <span className={`mt-1 w-2 h-2 rounded-full shrink-0 ${
                evt.type === 'success' ? 'bg-success' : evt.type === 'warning' ? 'bg-warning' : 'bg-primary'
              }`} />
              <div className="flex-1 min-w-0">
                <p className="text-sm text-white">{evt.event}</p>
                <p className="text-xs text-text-muted mt-1">{evt.time}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
