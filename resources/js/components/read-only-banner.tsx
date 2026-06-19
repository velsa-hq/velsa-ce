import { usePage } from '@inertiajs/react';

type SharedProps = {
    readOnly?: boolean;
};

// ribbon shown while the instance is in read-only mode (operations.read_only); server-shared, not dismissable
export default function ReadOnlyBanner() {
    const { readOnly } = usePage<SharedProps>().props;

    if (!readOnly) {
        return null;
    }

    return (
        <div className="pointer-events-none fixed top-14 left-1/2 z-[60] -translate-x-1/2">
            <div className="rounded-full bg-rose-600 px-3 py-1 text-[11px] font-semibold tracking-wide text-white uppercase shadow-md">
                Read-only mode - changes are temporarily disabled
            </div>
        </div>
    );
}
