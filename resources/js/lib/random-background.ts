import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

export function useRandomBackground(): string | null {
    const { stockBackgrounds } = usePage().props;

    return useMemo(() => {
        if (!stockBackgrounds?.length) {
            return null;
        }

        return stockBackgrounds[
            Math.floor(Math.random() * stockBackgrounds.length)
        ];
    }, [stockBackgrounds]);
}
