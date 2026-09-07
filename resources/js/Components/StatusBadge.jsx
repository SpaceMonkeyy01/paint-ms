const STYLES = {
    OK: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    LOW: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    OUT: 'bg-red-50 text-red-700 ring-red-600/20',
    variance: 'bg-red-50 text-red-700 ring-red-600/20',
    rework: 'bg-orange-50 text-orange-700 ring-orange-600/20',
    reissue: 'bg-sky-50 text-sky-700 ring-sky-600/20',
    closed: 'bg-gray-100 text-gray-600 ring-gray-500/20',
    open: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
};

export default function StatusBadge({ status, children }) {
    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                STYLES[status] ?? 'bg-gray-100 text-gray-600 ring-gray-500/20'
            }`}
        >
            {children ?? status}
        </span>
    );
}
