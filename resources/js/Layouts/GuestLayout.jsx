import ApplicationLogo, { BrandWordmark } from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4">
            {/* soft brand glows */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-amber-500/25 blur-3xl" />
                <div className="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-orange-600/15 blur-3xl" />
                <div className="absolute bottom-1/3 left-1/2 h-64 w-64 rounded-full bg-slate-500/10 blur-3xl" />
            </div>

            <div className="relative w-full sm:max-w-md">
                <Link href="/" className="mb-8 flex flex-col items-center gap-4">
                    <span className="flex h-20 w-20 items-center justify-center rounded-2xl bg-white shadow-lg shadow-amber-950/40">
                        <ApplicationLogo className="h-12 w-12" />
                    </span>
                    <span className="flex flex-col items-center gap-1.5 text-center">
                        <BrandWordmark size="lg" />
                        <span className="text-xs font-medium uppercase tracking-[0.25em] text-amber-200/60">
                            Paint Management System
                        </span>
                    </span>
                </Link>

                <div className="overflow-hidden rounded-2xl bg-white px-6 py-6 shadow-2xl">
                    {children}
                </div>

                <div className="mt-6 h-1 rounded-full bg-gradient-to-r from-amber-500 via-orange-500 to-slate-500 opacity-70" />
            </div>
        </div>
    );
}
