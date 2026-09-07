import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center bg-gray-950 px-4">
            {/* soft paint glows */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
                <div className="absolute -left-24 -top-24 h-96 w-96 rounded-full bg-indigo-600/30 blur-3xl" />
                <div className="absolute -bottom-32 -right-24 h-96 w-96 rounded-full bg-sky-500/20 blur-3xl" />
                <div className="absolute bottom-1/3 left-1/2 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl" />
            </div>

            <div className="relative w-full sm:max-w-md">
                <Link href="/" className="mb-8 flex flex-col items-center gap-3">
                    <span className="flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 shadow-lg shadow-indigo-900/50">
                        <ApplicationLogo className="h-9 w-9 fill-white" />
                    </span>
                    <span className="text-center">
                        <span className="block text-xl font-bold tracking-tight text-white">Paint Management System</span>
                        <span className="mt-1 block text-xs font-medium uppercase tracking-[0.2em] text-indigo-300/70">
                            BlueCascade · Signize
                        </span>
                    </span>
                </Link>

                <div className="overflow-hidden rounded-2xl bg-white px-6 py-6 shadow-2xl">
                    {children}
                </div>

                <div className="mt-6 h-1 rounded-full bg-gradient-to-r from-indigo-600 via-sky-500 via-emerald-500 via-amber-400 to-rose-500 opacity-60" />
            </div>
        </div>
    );
}
