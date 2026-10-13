/** Dashboard status buckets. Keys match PortalMetrics::dashboard(). */
export const STATUS_META: Record<string, { label: string; color: string; icon: string; tone: string }> = {
    validated: { label: 'Validated', color: '#14b8a6', icon: 'validated', tone: 'ok' },
    for_validation: { label: 'For Validation', color: '#f59e0b', icon: 'For Validation', tone: 'warn' },
    returned: { label: 'Returned', color: '#ef4444', icon: 'returned', tone: 'bad' },
    in_progress: { label: 'In progress', color: '#3b82f6', icon: 'In progress', tone: 'info' },
    not_started: { label: 'Not started', color: '#cbd5e1', icon: 'Not started', tone: 'todo' },
};

export const statusColor = (key: string) => STATUS_META[key]?.color ?? '#94a3b8';
