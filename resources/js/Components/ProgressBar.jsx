/**
 * Thin progress bar: value vs max. Turns amber near the cap and red beyond it.
 * Renders nothing when max is 0 (no allowance to compare against).
 */
export default function ProgressBar({ value, max, className = '' }) {
    if (!max || max <= 0) return null;

    const pct = Math.min((value / max) * 100, 100);
    const over = value > max + 0.005;
    const colour = over ? 'bg-red-500' : pct >= 90 ? 'bg-amber-500' : 'bg-emerald-500';

    return (
        <div className={`h-1.5 w-full overflow-hidden rounded-full bg-gray-100 ${className}`}>
            <div className={`h-full rounded-full ${colour} transition-all`} style={{ width: `${pct}%` }} />
        </div>
    );
}
