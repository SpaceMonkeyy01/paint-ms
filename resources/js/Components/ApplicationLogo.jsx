// Epic Craftings mark — stylized geometric eagle head in the brand orange/navy.
// (Recreated as SVG; drop the real raster logo in public/brand/ to swap it in.)
export default function ApplicationLogo({ className = '', navy = '#1e293b', orange = '#f59e0b' }) {
    return (
        <svg className={className} viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
            {/* crest feathers */}
            <path fill={navy} d="M3 19 L15 15 L8 6 L22 12 L19 3 L30 11 L24 16 Z" />
            {/* head */}
            <path fill={orange} d="M7 21 Q13 11 27 11 Q39 12 43 19 L34 21 Q36 25 32 28 Q23 35 12 30 Q6 27 7 21 Z" />
            {/* beak */}
            <path fill={navy} d="M41 17 L48 21 L39 26 L36 21 Z" />
            {/* eye */}
            <circle cx="29" cy="18" r="2.4" fill={navy} />
        </svg>
    );
}

/** The "EPIC CRAFTINGS" wordmark: orange EPIC + white-on-orange chip. */
export function BrandWordmark({ size = 'md' }) {
    const text = size === 'lg' ? 'text-2xl' : 'text-sm';
    const chip = size === 'lg' ? 'text-lg px-2 py-0.5' : 'text-[11px] px-1.5 py-px';

    return (
        <span className={`inline-flex items-center gap-1.5 font-extrabold tracking-tight ${text}`}>
            <span className="text-amber-500">EPIC</span>
            <span className={`rounded-md bg-amber-500 font-bold uppercase text-white ${chip}`}>Craftings</span>
        </span>
    );
}
