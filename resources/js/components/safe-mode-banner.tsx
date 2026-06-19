import { usePage } from '@inertiajs/react';

type SharedProps = {
    safeMode?: { label: string } | null;
};

// ribbon shown on non-acting (demo / training / uat) instances; driven by the server-shared safeMode prop (config/velsa.php)
export default function SafeModeBanner() {
    const { safeMode } = usePage<SharedProps>().props;

    if (!safeMode) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed bottom-3 left-1/2 z-[60] -translate-x-1/2">
            <div className="rounded-full bg-amber-500 px-3 py-1 text-[11px] font-semibold tracking-wide text-amber-950 uppercase shadow-md">
                {safeMode.label} · practice environment - emails, e-signatures
                &amp; payments disabled
            </div>
        </div>
    );
}
