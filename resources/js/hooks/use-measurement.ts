import { usePage } from '@inertiajs/react';

// area-unit helpers; area is stored canonically in sqft, display unit (ft2/m2)
// comes from the shared measurement prop
export function useMeasurement() {
    const measurement = usePage().props.measurement;
    const unit = measurement?.unit ?? 'ft²';
    const rawSqftPerUnit = measurement?.sqft_per_unit ?? 1;
    // guard against 0/negative config so we never divide by zero
    const sqftPerUnit = rawSqftPerUnit > 0 ? rawSqftPerUnit : 1;

    /** sqft -> display unit (rounded) */
    const fromSqft = (sqft: number) => Math.round(sqft / sqftPerUnit);

    /** display unit -> sqft (rounded) */
    const toSqft = (display: number) => Math.round(display * sqftPerUnit);

    /** formatted area, "-" when null */
    const formatArea = (sqft: number | null | undefined) =>
        sqft == null ? '-' : `${fromSqft(sqft).toLocaleString()} ${unit}`;

    return {
        unit,
        metric: measurement?.metric ?? false,
        fromSqft,
        toSqft,
        formatArea,
    };
}
