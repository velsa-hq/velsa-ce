import type { FormDataConvertible } from '@inertiajs/core';
import { Head, router } from '@inertiajs/react';
import type Konva from 'konva';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Circle,
    Group,
    Image as KonvaImage,
    Layer,
    Line,
    Rect,
    Stage,
    Text,
} from 'react-konva';

import { Button } from '@/components/ui/button';
import { useMeasurement } from '@/hooks/use-measurement';
import {
    boxesOverlap,
    constraintAABB,
    floorPlanFileName,
} from '@/lib/diagram-geometry';
import type {
    Bounds,
    Constraint,
    ConstraintKind,
} from '@/lib/diagram-geometry';

type DiagramObject = {
    id: string;
    type: string;
    x: number;
    y: number;
    rotation?: number;
    // per-instance overrides of palette defaults; label adds a caption,
    // anything else (e.g. booth_number) flows through unchanged
    props?: {
        width_ft?: number;
        height_ft?: number;
        seats?: number;
        label?: string;
        [key: string]: FormDataConvertible;
    };
};

// merge palette defaults with per-instance props overrides
function effectiveMeta(
    obj: DiagramObject,
    meta: PaletteItem,
): {
    width_ft: number;
    height_ft: number;
    seats: number | null;
    label: string | null;
} {
    return {
        width_ft: obj.props?.width_ft ?? meta.width_ft,
        height_ft: obj.props?.height_ft ?? meta.height_ft,
        seats: obj.props?.seats ?? meta.seats ?? null,
        label: obj.props?.label ?? null,
    };
}

type Template = {
    id: number;
    name: string;
    category: string | null;
    description: string | null;
    object_count: number;
    seat_count: number;
    is_global: boolean;
};

// read-only constraint rendering; matches the admin editor palette
const CONSTRAINT_STYLE: Record<
    ConstraintKind,
    { fill: string; stroke: string; shape: 'rect' | 'circle' }
> = {
    wall: { fill: '#475569', stroke: '#1e293b', shape: 'rect' },
    door: { fill: '#b45309', stroke: '#78350f', shape: 'rect' },
    window: { fill: '#7dd3fc', stroke: '#0369a1', shape: 'rect' },
    column: { fill: '#64748b', stroke: '#1e293b', shape: 'rect' },
    post: { fill: '#64748b', stroke: '#1e293b', shape: 'rect' },
    outlet: { fill: '#facc15', stroke: '#92400e', shape: 'circle' },
};

type AutoLayoutOpts = {
    headcount: number;
    table_type: 'round_table_60' | 'round_table_72';
    buffer_ft: number;
    include_stage: boolean;
};

type Props = {
    booking: {
        id: number;
        reference: string;
        name: string;
        attendance_estimate: number | null;
    };
    space: {
        id: number;
        name: string;
        sqft: number | null;
        capacity: number | null;
        constraints: Constraint[];
        floorplan_url: string | null;
    };
    diagram: {
        id: number;
        name: string;
        scale_px_per_foot: number;
        version: number | null;
        is_locked: boolean;
    };
    objects: DiagramObject[];
    sibling_spaces: Array<{ id: number; name: string }>;
    templates: Template[];
};

type PaletteItem = {
    type: string;
    label: string;
    shape: 'circle' | 'rect';
    width_ft: number;
    height_ft: number;
    fill: string;
    stroke: string;
    seats?: number;
};

const PALETTE: PaletteItem[] = [
    {
        type: 'round_table_60',
        label: '60" Round · seats 8',
        shape: 'circle',
        width_ft: 5,
        height_ft: 5,
        fill: '#f0fdf4',
        stroke: '#166534',
        seats: 8,
    },
    {
        type: 'round_table_72',
        label: '72" Round · seats 10',
        shape: 'circle',
        width_ft: 6,
        height_ft: 6,
        fill: '#f0fdf4',
        stroke: '#166534',
        seats: 10,
    },
    {
        type: 'cocktail',
        label: 'Cocktail · 36"',
        shape: 'circle',
        width_ft: 3,
        height_ft: 3,
        fill: '#fef3c7',
        stroke: '#92400e',
        seats: 4,
    },
    {
        type: 'rect_table_6',
        label: "6' Rect · seats 6",
        shape: 'rect',
        width_ft: 6,
        height_ft: 2.5,
        fill: '#dbeafe',
        stroke: '#1d4ed8',
        seats: 6,
    },
    {
        type: 'rect_table_8',
        label: "8' Rect · seats 8",
        shape: 'rect',
        width_ft: 8,
        height_ft: 2.5,
        fill: '#dbeafe',
        stroke: '#1d4ed8',
        seats: 8,
    },
    {
        type: 'chair',
        label: 'Chair',
        shape: 'rect',
        width_ft: 1.5,
        height_ft: 1.5,
        fill: '#fce7f3',
        stroke: '#9d174d',
    },
    {
        type: 'stage_4x8',
        label: "Stage 4x8'",
        shape: 'rect',
        width_ft: 8,
        height_ft: 4,
        fill: '#ede9fe',
        stroke: '#5b21b6',
    },
    {
        type: 'booth_10x10',
        label: 'Booth 10x10',
        shape: 'rect',
        width_ft: 10,
        height_ft: 10,
        fill: '#fae8ff',
        stroke: '#86198f',
    },
    {
        type: 'tent_20x20',
        label: 'Tent 20x20',
        shape: 'rect',
        width_ft: 20,
        height_ft: 20,
        fill: '#ccfbf1',
        stroke: '#115e59',
    },
];

const STAGE_WIDTH = 1000;
const STAGE_HEIGHT = 600;
const GRID_FT = 1;
// px distance under which a drag snaps to another object's alignment
const SNAP_THRESHOLD_PX = 8;

function objectMeta(type: string): PaletteItem | undefined {
    return PALETTE.find((p) => p.type === type);
}

function objectBounds(obj: DiagramObject, ppf: number): Bounds | null {
    const meta = objectMeta(obj.type);

    if (!meta) {
        return null;
    }

    const eff = effectiveMeta(obj, meta);
    const w = eff.width_ft * ppf;
    const h = eff.height_ft * ppf;

    return {
        left: obj.x - w / 2,
        right: obj.x + w / 2,
        top: obj.y - h / 2,
        bottom: obj.y + h / 2,
        cx: obj.x,
        cy: obj.y,
    };
}

export default function DiagramPage({
    booking,
    space,
    diagram,
    objects: initialObjects,
    sibling_spaces,
    templates,
}: Props) {
    const { formatArea } = useMeasurement();
    const [floorPlanImg, setFloorPlanImg] = useState<HTMLImageElement | null>(
        null,
    );

    useEffect(() => {
        if (!space.floorplan_url) {
            setFloorPlanImg(null);

            return;
        }

        const img = new window.Image();
        img.src = space.floorplan_url;
        img.onload = () => setFloorPlanImg(img);
    }, [space.floorplan_url]);

    const uploadFloorPlan = (file: File | null) => {
        if (!file) {
            return;
        }

        router.post(
            `/spaces/${space.id}/floorplan`,
            { floorplan: file },
            { forceFormData: true, preserveScroll: true },
        );
    };

    const removeFloorPlan = () => {
        if (window.confirm('Remove the uploaded floor-plan backdrop?')) {
            router.delete(`/spaces/${space.id}/floorplan`, {
                preserveScroll: true,
            });
        }
    };

    const stageRef = useRef<Konva.Stage>(null);
    const [objects, setObjects] = useState<DiagramObject[]>(
        initialObjects ?? [],
    );
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [dirty, setDirty] = useState(false);
    const [snap, setSnap] = useState(true);
    const [snapGuides, setSnapGuides] = useState<{
        vx: number | null;
        hy: number | null;
    }>({ vx: null, hy: null });
    const [templatePickerOpen, setTemplatePickerOpen] = useState(false);
    const [saveAsOpen, setSaveAsOpen] = useState(false);
    const [autoLayoutOpen, setAutoLayoutOpen] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);
    const ppf = diagram.scale_px_per_foot;
    const gridPx = GRID_FT * ppf;

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.key === 'Delete' || e.key === 'Backspace') && selectedId) {
                e.preventDefault();
                setObjects((prev) => prev.filter((o) => o.id !== selectedId));
                setSelectedId(null);
                setDirty(true);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [selectedId]);

    const grid = useMemo(() => {
        const lines: Array<{ pts: number[]; key: string }> = [];

        for (let x = 0; x <= STAGE_WIDTH; x += gridPx) {
            lines.push({ pts: [x, 0, x, STAGE_HEIGHT], key: `v${x}` });
        }

        for (let y = 0; y <= STAGE_HEIGHT; y += gridPx) {
            lines.push({ pts: [0, y, STAGE_WIDTH, y], key: `h${y}` });
        }

        return lines;
    }, [gridPx]);

    function addFromPalette(item: PaletteItem) {
        const id = `obj_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
        setObjects((prev) => [
            ...prev,
            {
                id,
                type: item.type,
                x: STAGE_WIDTH / 2,
                y: STAGE_HEIGHT / 2,
                rotation: 0,
                props: item.seats ? { seats: item.seats } : undefined,
            },
        ]);
        setSelectedId(id);
        setDirty(true);
    }

    function onDragEnd(id: string, x: number, y: number) {
        // dragBoundFunc already applied the snap; commit final position, clear guides
        setObjects((prev) =>
            prev.map((o) => (o.id === id ? { ...o, x, y } : o)),
        );
        setDirty(true);
        setSnapGuides({ vx: null, hy: null });
    }

    function updateSelected(
        patch:
            | Partial<Pick<DiagramObject, 'rotation'>>
            | { props: DiagramObject['props'] },
    ) {
        if (!selectedId) {
            return;
        }

        setObjects((prev) =>
            prev.map((o) => {
                if (o.id !== selectedId) {
                    return o;
                }

                if ('props' in patch) {
                    return { ...o, props: { ...o.props, ...patch.props } };
                }

                return { ...o, ...patch };
            }),
        );
        setDirty(true);
    }

    function deleteSelected() {
        if (!selectedId) {
            return;
        }

        setObjects((prev) => prev.filter((o) => o.id !== selectedId));
        setSelectedId(null);
        setDirty(true);
    }

    const selectedObject = useMemo(
        () => objects.find((o) => o.id === selectedId) ?? null,
        [objects, selectedId],
    );

    // snap a dragged object to another object's edge/center within
    // SNAP_THRESHOLD_PX, else to the grid; returns position + guide coords
    function computeSnap(
        draggedId: string,
        proposedX: number,
        proposedY: number,
        draggedMeta: PaletteItem,
        dragged: DiagramObject,
    ): { x: number; y: number; vx: number | null; hy: number | null } {
        if (!snap) {
            return { x: proposedX, y: proposedY, vx: null, hy: null };
        }

        const eff = effectiveMeta(dragged, draggedMeta);
        const w = eff.width_ft * ppf;
        const h = eff.height_ft * ppf;
        const draggedBounds: Bounds = {
            left: proposedX - w / 2,
            right: proposedX + w / 2,
            top: proposedY - h / 2,
            bottom: proposedY + h / 2,
            cx: proposedX,
            cy: proposedY,
        };

        let bestX: { delta: number; guide: number } | null = null;
        let bestY: { delta: number; guide: number } | null = null;

        for (const other of objects) {
            if (other.id === draggedId) {
                continue;
            }

            const ob = objectBounds(other, ppf);

            if (!ob) {
                continue;
            }

            // vertical alignment candidates: dragged edge/center -> other edge/center
            const xPairs: Array<{
                drag: number;
                target: number;
                guide: number;
            }> = [
                { drag: draggedBounds.left, target: ob.left, guide: ob.left },
                { drag: draggedBounds.left, target: ob.right, guide: ob.right },
                { drag: draggedBounds.cx, target: ob.cx, guide: ob.cx },
                { drag: draggedBounds.right, target: ob.left, guide: ob.left },
                {
                    drag: draggedBounds.right,
                    target: ob.right,
                    guide: ob.right,
                },
            ];

            for (const p of xPairs) {
                const delta = p.target - p.drag;

                if (
                    Math.abs(delta) < SNAP_THRESHOLD_PX &&
                    (bestX === null || Math.abs(delta) < Math.abs(bestX.delta))
                ) {
                    bestX = { delta, guide: p.guide };
                }
            }

            const yPairs: Array<{
                drag: number;
                target: number;
                guide: number;
            }> = [
                { drag: draggedBounds.top, target: ob.top, guide: ob.top },
                {
                    drag: draggedBounds.top,
                    target: ob.bottom,
                    guide: ob.bottom,
                },
                { drag: draggedBounds.cy, target: ob.cy, guide: ob.cy },
                { drag: draggedBounds.bottom, target: ob.top, guide: ob.top },
                {
                    drag: draggedBounds.bottom,
                    target: ob.bottom,
                    guide: ob.bottom,
                },
            ];

            for (const p of yPairs) {
                const delta = p.target - p.drag;

                if (
                    Math.abs(delta) < SNAP_THRESHOLD_PX &&
                    (bestY === null || Math.abs(delta) < Math.abs(bestY.delta))
                ) {
                    bestY = { delta, guide: p.guide };
                }
            }
        }

        const x =
            bestX !== null
                ? proposedX + bestX.delta
                : Math.round(proposedX / gridPx) * gridPx;
        const y =
            bestY !== null
                ? proposedY + bestY.delta
                : Math.round(proposedY / gridPx) * gridPx;

        return {
            x,
            y,
            vx: bestX?.guide ?? null,
            hy: bestY?.guide ?? null,
        };
    }

    function save() {
        router.post(
            `/bookings/${booking.id}/diagram`,
            { space_id: space.id, objects },
            {
                preserveScroll: true,
                onSuccess: () => setDirty(false),
            },
        );
    }

    // rasterize at 2x for a crisp export
    function floorPlanDataUrl(): string | null {
        return stageRef.current?.toDataURL({ pixelRatio: 2 }) ?? null;
    }

    function exportPng() {
        const uri = floorPlanDataUrl();

        if (!uri) {
            return;
        }

        const link = document.createElement('a');
        link.download = floorPlanFileName(booking.reference);
        link.href = uri;
        link.click();
    }

    function printPlan() {
        const uri = floorPlanDataUrl();

        if (!uri) {
            return;
        }

        const w = window.open('', '_blank');

        if (!w) {
            return;
        }

        w.document.write(
            `<html><head><title>${booking.reference} - Floor plan</title></head>` +
                `<body style="margin:0"><img src="${uri}" style="max-width:100%" ` +
                `onload="window.focus();window.print();" /></body></html>`,
        );
        w.document.close();
    }

    const stats = useMemo(() => {
        let seats = 0;

        for (const o of objects) {
            const meta = objectMeta(o.type);

            if (!meta) {
                continue;
            }

            const eff = effectiveMeta(o, meta);

            if (eff.seats) {
                seats += eff.seats;
            }
        }

        return { seats, count: objects.length };
    }, [objects]);

    const capacityWarn =
        space.capacity !== null && stats.seats > space.capacity;
    const attendanceWarn =
        booking.attendance_estimate !== null &&
        stats.seats > 0 &&
        stats.seats < booking.attendance_estimate;

    // lay out headcount across rounds in a centered grid (pitch = diameter +
    // buffer), skipping slots that collide with a constraint; optional 4x8
    // stage at top. returns objects + placed-vs-requested counts
    function generateAutoLayout(opts: AutoLayoutOpts): {
        objects: DiagramObject[];
        requestedTables: number;
        placedTables: number;
    } {
        const tableMeta = objectMeta(opts.table_type);

        if (!tableMeta) {
            return { objects: [], requestedTables: 0, placedTables: 0 };
        }

        const seatsPerTable = tableMeta.seats ?? 8;
        const requestedTables = Math.max(
            1,
            Math.ceil(opts.headcount / seatsPerTable),
        );
        const diameterPx = tableMeta.width_ft * ppf;
        const radiusPx = diameterPx / 2;
        const pitchPx = (tableMeta.width_ft + opts.buffer_ft) * ppf;

        const sideMarginPx = 20;
        const topReservePx = (opts.include_stage ? 12 : 4) * ppf;
        const bottomMarginPx = 4 * ppf;

        const usableWidthPx = STAGE_WIDTH - 2 * sideMarginPx - 2 * radiusPx;
        const usableHeightPx =
            STAGE_HEIGHT - topReservePx - bottomMarginPx - 2 * radiusPx;

        // oversized grid so collided slots can drop and still hit requestedTables
        const maxCols = Math.max(1, Math.floor(usableWidthPx / pitchPx) + 1);
        const maxRows = Math.max(1, Math.floor(usableHeightPx / pitchPx) + 1);

        const gridWidthPx = (maxCols - 1) * pitchPx;
        const gridHeightPx = (maxRows - 1) * pitchPx;

        const originX = (STAGE_WIDTH - gridWidthPx) / 2;
        const originY =
            topReservePx +
            radiusPx +
            Math.max(0, (usableHeightPx - gridHeightPx) / 2);

        const constraintBoxes = (space.constraints ?? []).map((c) =>
            constraintAABB(c, ppf),
        );

        const tableHalf = radiusPx;
        const stamp = Date.now();
        const generated: DiagramObject[] = [];
        // track row membership so each row can be centered afterward
        // (the raw grid walk leaves a partial row left-anchored)
        const tablesByRow = new Map<number, DiagramObject[]>();
        let placed = 0;

        // walk columns center-outward so a budget-limited row fills
        // symmetrically instead of packing from the left
        const gridCenter = (maxCols - 1) / 2;
        const sortedCs: number[] = Array.from(
            { length: maxCols },
            (_, i) => i,
        ).sort((a, b) => {
            const distA = Math.abs(a - gridCenter);
            const distB = Math.abs(b - gridCenter);

            if (distA !== distB) {
                return distA - distB;
            }

            // tiebreak: prefer right-of-center so ties alternate around the axis
            return a - b;
        });

        for (let r = 0; r < maxRows && placed < requestedTables; r++) {
            for (const c of sortedCs) {
                if (placed >= requestedTables) {
                    break;
                }

                const x = originX + c * pitchPx;
                const y = originY + r * pitchPx;
                const tableBox: Bounds = {
                    left: x - tableHalf,
                    right: x + tableHalf,
                    top: y - tableHalf,
                    bottom: y + tableHalf,
                    cx: x,
                    cy: y,
                };

                if (constraintBoxes.some((cb) => boxesOverlap(tableBox, cb))) {
                    continue;
                }

                const table = {
                    id: `obj_auto_${stamp}_${placed}_${Math.random()
                        .toString(36)
                        .slice(2, 5)}`,
                    type: opts.table_type,
                    x,
                    y,
                    rotation: 0,
                    props: { seats: seatsPerTable },
                };
                generated.push(table);

                const rowList = tablesByRow.get(r) ?? [];
                rowList.push(table);
                tablesByRow.set(r, rowList);

                placed++;
            }
        }

        // center each row to STAGE_WIDTH/2, but skip the shift if it
        // would push a table into a constraint
        for (const rowTables of tablesByRow.values()) {
            if (rowTables.length < 1) {
                continue;
            }

            const xs = rowTables.map((t) => t.x);
            const rowCenter = (Math.min(...xs) + Math.max(...xs)) / 2;
            const shift = STAGE_WIDTH / 2 - rowCenter;

            if (Math.abs(shift) < 0.5) {
                continue;
            }

            const wouldCollide = rowTables.some((t) => {
                const shiftedBox: Bounds = {
                    left: t.x + shift - tableHalf,
                    right: t.x + shift + tableHalf,
                    top: t.y - tableHalf,
                    bottom: t.y + tableHalf,
                    cx: t.x + shift,
                    cy: t.y,
                };

                return constraintBoxes.some((cb) =>
                    boxesOverlap(shiftedBox, cb),
                );
            });

            if (wouldCollide) {
                continue;
            }

            for (const t of rowTables) {
                t.x += shift;
            }
        }

        if (opts.include_stage) {
            // centered 4x8 stage, only if clear of every constraint
            const stageMeta = objectMeta('stage_4x8');

            if (stageMeta) {
                const sx = STAGE_WIDTH / 2;
                const sy = 6 * ppf;
                const sw = stageMeta.width_ft * ppf;
                const sh = stageMeta.height_ft * ppf;
                const stageBox: Bounds = {
                    left: sx - sw / 2,
                    right: sx + sw / 2,
                    top: sy - sh / 2,
                    bottom: sy + sh / 2,
                    cx: sx,
                    cy: sy,
                };

                if (!constraintBoxes.some((cb) => boxesOverlap(stageBox, cb))) {
                    generated.push({
                        id: `obj_auto_${stamp}_stage`,
                        type: 'stage_4x8',
                        x: sx,
                        y: sy,
                        rotation: 0,
                    });
                }
            }
        }

        return { objects: generated, requestedTables, placedTables: placed };
    }

    const [autoLayoutResult, setAutoLayoutResult] = useState<{
        placed: number;
        requested: number;
    } | null>(null);

    function applyAutoLayout(opts: AutoLayoutOpts, mode: 'replace' | 'append') {
        const {
            objects: generated,
            placedTables,
            requestedTables,
        } = generateAutoLayout(opts);

        if (generated.length === 0) {
            return;
        }

        setObjects((prev) =>
            mode === 'replace' ? generated : [...prev, ...generated],
        );
        setDirty(true);
        setAutoLayoutOpen(false);
        setAutoLayoutResult({
            placed: placedTables,
            requested: requestedTables,
        });
    }

    function applyTemplate(template: Template, mode: 'replace' | 'append') {
        // fetch (not router.post) so we can read the merged objects back;
        // append otherwise leaves the canvas stale until a manual reload
        fetch(`/diagrams/${diagram.id}/apply-template/${template.id}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement | null
                    )?.content ?? '',
            },
            body: JSON.stringify({ mode }),
        })
            .then((res) => {
                if (!res.ok) {
                    throw new Error(`Apply failed (${res.status})`);
                }

                return res.json();
            })
            .then((data: { objects: DiagramObject[] }) => {
                setObjects(data.objects);
                setSelectedId(null);
                setDirty(false);
                setTemplatePickerOpen(false);
            })
            .catch((err) => console.error('apply-template:', err));
    }

    function saveAsTemplate(form: {
        name: string;
        category: string;
        description: string;
    }) {
        fetch(`/diagrams/${diagram.id}/save-as-template`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement | null
                    )?.content ?? '',
            },
            body: JSON.stringify({ ...form, objects }),
        }).then((res) => {
            if (res.ok) {
                setSaveAsOpen(false);
                router.reload({ only: ['templates'] });
            }
        });
    }

    return (
        <>
            <Head title={`${booking.reference} · Floor plan`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {booking.name} · {space.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {booking.reference} ·{' '}
                            {space.sqft ? formatArea(space.sqft) : ''}
                            {diagram.version
                                ? ` · v${diagram.version}`
                                : ' · unsaved'}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        {sibling_spaces.length > 1 ? (
                            <select
                                onChange={(e) =>
                                    router.get(
                                        `/bookings/${booking.id}/diagram`,
                                        { space_id: e.target.value },
                                    )
                                }
                                defaultValue={space.id}
                                className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 text-sm dark:border-sidebar-border"
                            >
                                {sibling_spaces.map((s) => (
                                    <option key={s.id} value={s.id}>
                                        {s.name}
                                    </option>
                                ))}
                            </select>
                        ) : null}
                        <label className="flex items-center gap-1.5 text-sm">
                            <input
                                type="checkbox"
                                checked={snap}
                                onChange={(e) => setSnap(e.target.checked)}
                                data-tour-id="diagram-snap-toggle"
                            />
                            Snap
                        </label>
                        <button
                            onClick={() => setTemplatePickerOpen((v) => !v)}
                            disabled={
                                diagram.is_locked || templates.length === 0
                            }
                            data-tour-id="diagram-apply-template"
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40 dark:border-sidebar-border"
                        >
                            Apply template...
                        </button>
                        <button
                            onClick={() => setAutoLayoutOpen(true)}
                            disabled={diagram.is_locked}
                            data-tour-id="diagram-auto-layout"
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40 dark:border-sidebar-border"
                        >
                            Auto-layout...
                        </button>
                        <button
                            onClick={() => setSaveAsOpen(true)}
                            disabled={diagram.is_locked || objects.length === 0}
                            data-tour-id="diagram-save-as-template"
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted disabled:opacity-40 dark:border-sidebar-border"
                        >
                            Save as template
                        </button>
                        <label
                            data-tour-id="diagram-floorplan-upload"
                            className="cursor-pointer rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        >
                            {space.floorplan_url
                                ? 'Replace floor plan'
                                : 'Upload floor plan'}
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                className="hidden"
                                onChange={(e) =>
                                    uploadFloorPlan(e.target.files?.[0] ?? null)
                                }
                            />
                        </label>
                        {space.floorplan_url && (
                            <button
                                onClick={removeFloorPlan}
                                className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                            >
                                Remove floor plan
                            </button>
                        )}
                        <button
                            onClick={exportPng}
                            data-tour-id="diagram-export-png"
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        >
                            Export PNG
                        </button>
                        <button
                            onClick={printPlan}
                            data-tour-id="diagram-print"
                            className="rounded-md border border-sidebar-border/70 px-3 py-1.5 text-sm font-medium hover:bg-muted dark:border-sidebar-border"
                        >
                            Print
                        </button>
                        <button
                            onClick={save}
                            disabled={!dirty || diagram.is_locked}
                            data-tour-id="diagram-save"
                            className="rounded-md bg-foreground px-3 py-1.5 text-sm font-medium text-background disabled:opacity-40"
                        >
                            {diagram.is_locked
                                ? 'Locked'
                                : dirty
                                  ? 'Save'
                                  : 'Saved'}
                        </button>
                    </div>
                </header>

                {(capacityWarn || attendanceWarn) && (
                    <div
                        className={`rounded-md border px-3 py-2 text-xs ${
                            capacityWarn
                                ? 'border-rose-300 bg-rose-50 text-rose-900 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
                                : 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
                        }`}
                        role="status"
                    >
                        {capacityWarn && (
                            <span>
                                <strong>Over capacity:</strong> {stats.seats}{' '}
                                seats placed vs space capacity of{' '}
                                {space.capacity}.
                            </span>
                        )}
                        {!capacityWarn && attendanceWarn && (
                            <span>
                                <strong>Short of estimate:</strong>{' '}
                                {stats.seats} seats placed vs expected
                                attendance of {booking.attendance_estimate}.
                            </span>
                        )}
                    </div>
                )}

                {autoLayoutResult && (
                    <div
                        className={`flex items-center justify-between rounded-md border px-3 py-2 text-xs ${
                            autoLayoutResult.placed < autoLayoutResult.requested
                                ? 'border-amber-300 bg-amber-50 text-amber-900 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
                                : 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200'
                        }`}
                        role="status"
                    >
                        <span>
                            {autoLayoutResult.placed <
                            autoLayoutResult.requested ? (
                                <>
                                    <strong>Partial fit:</strong> placed{' '}
                                    {autoLayoutResult.placed} of{' '}
                                    {autoLayoutResult.requested} tables -
                                    constraints (walls / columns) blocked the
                                    rest. Reduce buffer, use larger rounds, or
                                    drag the missing tables in manually.
                                </>
                            ) : (
                                <>
                                    <strong>Auto-layout:</strong> placed{' '}
                                    {autoLayoutResult.placed} tables clean of
                                    every constraint.
                                </>
                            )}
                        </span>
                        <button
                            type="button"
                            onClick={() => setAutoLayoutResult(null)}
                            className="rounded-md px-2 py-0.5 text-[11px] hover:bg-black/5 dark:hover:bg-white/5"
                        >
                            Dismiss
                        </button>
                    </div>
                )}

                {templatePickerOpen && (
                    <TemplatePicker
                        templates={templates}
                        hasObjects={objects.length > 0}
                        onApply={applyTemplate}
                        onClose={() => setTemplatePickerOpen(false)}
                    />
                )}

                {saveAsOpen && (
                    <SaveAsTemplateDialog
                        onSubmit={saveAsTemplate}
                        onClose={() => setSaveAsOpen(false)}
                    />
                )}

                {autoLayoutOpen && (
                    <AutoLayoutDialog
                        hasObjects={objects.length > 0}
                        onApply={applyAutoLayout}
                        onClose={() => setAutoLayoutOpen(false)}
                    />
                )}

                <div className="flex gap-4">
                    <aside className="w-56 shrink-0 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                        <h3 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Library
                        </h3>
                        <div
                            data-tour-id="diagram-palette"
                            className="flex flex-col gap-1.5"
                        >
                            {PALETTE.map((item) => (
                                <button
                                    key={item.type}
                                    onClick={() => addFromPalette(item)}
                                    className="flex items-center justify-between rounded-md border border-sidebar-border/70 px-2 py-1.5 text-left text-xs hover:bg-muted dark:border-sidebar-border"
                                >
                                    <span>{item.label}</span>
                                    <span
                                        className="ml-2 inline-block h-3 w-3 rounded-sm border"
                                        style={{
                                            backgroundColor: item.fill,
                                            borderColor: item.stroke,
                                        }}
                                    />
                                </button>
                            ))}
                        </div>

                        <div className="mt-4 rounded-md bg-muted/50 p-2 text-xs">
                            <div className="font-medium">
                                {stats.count} objects
                            </div>
                            <div className="text-muted-foreground">
                                {stats.seats} seats
                            </div>
                            <div className="mt-1 text-muted-foreground">
                                Press{' '}
                                <kbd className="rounded bg-background px-1 font-mono">
                                    Del
                                </kbd>{' '}
                                to remove selected
                            </div>
                        </div>

                        {selectedObject && (
                            <PropertiesPanel
                                object={selectedObject}
                                meta={objectMeta(selectedObject.type)}
                                onUpdate={updateSelected}
                                onDelete={deleteSelected}
                                disabled={diagram.is_locked}
                            />
                        )}
                    </aside>

                    <div
                        ref={containerRef}
                        role="region"
                        aria-label="Floor plan editor"
                        className="flex-1 overflow-auto rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900"
                    >
                        <Stage
                            ref={stageRef}
                            width={STAGE_WIDTH}
                            height={STAGE_HEIGHT}
                            onClick={(e) => {
                                if (e.target === e.target.getStage()) {
                                    setSelectedId(null);
                                }
                            }}
                        >
                            {floorPlanImg && (
                                <Layer listening={false}>
                                    <KonvaImage
                                        image={floorPlanImg}
                                        width={STAGE_WIDTH}
                                        height={STAGE_HEIGHT}
                                        opacity={0.45}
                                    />
                                </Layer>
                            )}
                            <Layer listening={false}>
                                {grid.map((g) => (
                                    <Line
                                        key={g.key}
                                        points={g.pts}
                                        stroke="#e5e7eb"
                                        strokeWidth={0.5}
                                    />
                                ))}
                            </Layer>
                            {/* read-only constraints layer (walls/doors/columns/outlets);
                                ignores pointer events so it never steals a drag */}
                            <Layer listening={false} opacity={0.85}>
                                {space.constraints.map((c) => {
                                    const style = CONSTRAINT_STYLE[c.kind];
                                    const w = c.width_ft * ppf;
                                    const h = c.height_ft * ppf;

                                    if (style.shape === 'circle') {
                                        return (
                                            <Circle
                                                key={c.id}
                                                x={c.x}
                                                y={c.y}
                                                radius={w / 2}
                                                fill={style.fill}
                                                stroke={style.stroke}
                                                strokeWidth={1}
                                            />
                                        );
                                    }

                                    return (
                                        <Rect
                                            key={c.id}
                                            x={c.x}
                                            y={c.y}
                                            width={w}
                                            height={h}
                                            offsetX={w / 2}
                                            offsetY={h / 2}
                                            rotation={c.rotation ?? 0}
                                            fill={style.fill}
                                            stroke={style.stroke}
                                            strokeWidth={1}
                                        />
                                    );
                                })}
                            </Layer>
                            {/* snap guide lines, drawn during an active snap, cleared on dragEnd */}
                            <Layer listening={false}>
                                {snapGuides.vx !== null && (
                                    <Line
                                        points={[
                                            snapGuides.vx,
                                            0,
                                            snapGuides.vx,
                                            STAGE_HEIGHT,
                                        ]}
                                        stroke="#ef4444"
                                        strokeWidth={1}
                                        dash={[4, 4]}
                                    />
                                )}
                                {snapGuides.hy !== null && (
                                    <Line
                                        points={[
                                            0,
                                            snapGuides.hy,
                                            STAGE_WIDTH,
                                            snapGuides.hy,
                                        ]}
                                        stroke="#ef4444"
                                        strokeWidth={1}
                                        dash={[4, 4]}
                                    />
                                )}
                            </Layer>
                            <Layer>
                                {objects.map((obj) => {
                                    const meta = objectMeta(obj.type);

                                    if (!meta) {
                                        return null;
                                    }

                                    const isSelected = obj.id === selectedId;
                                    const eff = effectiveMeta(obj, meta);
                                    const w = eff.width_ft * ppf;
                                    const h = eff.height_ft * ppf;
                                    const commonProps = {
                                        x: obj.x,
                                        y: obj.y,
                                        rotation: obj.rotation ?? 0,
                                        draggable: !diagram.is_locked,
                                        onClick: () => setSelectedId(obj.id),
                                        onTap: () => setSelectedId(obj.id),
                                        dragBoundFunc: (pos: {
                                            x: number;
                                            y: number;
                                        }) => {
                                            const result = computeSnap(
                                                obj.id,
                                                pos.x,
                                                pos.y,
                                                meta,
                                                obj,
                                            );
                                            setSnapGuides((prev) =>
                                                prev.vx === result.vx &&
                                                prev.hy === result.hy
                                                    ? prev
                                                    : {
                                                          vx: result.vx,
                                                          hy: result.hy,
                                                      },
                                            );

                                            return { x: result.x, y: result.y };
                                        },
                                        onDragEnd: (e: any) =>
                                            onDragEnd(
                                                obj.id,
                                                e.target.x(),
                                                e.target.y(),
                                            ),
                                    };

                                    if (meta.shape === 'circle') {
                                        return (
                                            <Group
                                                key={obj.id}
                                                {...commonProps}
                                            >
                                                <Circle
                                                    radius={w / 2}
                                                    fill={meta.fill}
                                                    stroke={
                                                        isSelected
                                                            ? '#ef4444'
                                                            : meta.stroke
                                                    }
                                                    strokeWidth={
                                                        isSelected ? 2 : 1
                                                    }
                                                />
                                                {eff.seats ? (
                                                    <Text
                                                        text={String(eff.seats)}
                                                        fontSize={12}
                                                        x={-6}
                                                        y={-6}
                                                        fill={meta.stroke}
                                                    />
                                                ) : null}
                                                {eff.label ? (
                                                    <Text
                                                        text={eff.label}
                                                        fontSize={11}
                                                        align="center"
                                                        width={w}
                                                        offsetX={w / 2}
                                                        y={w / 2 + 4}
                                                        fill={meta.stroke}
                                                    />
                                                ) : null}
                                            </Group>
                                        );
                                    }

                                    return (
                                        <Group key={obj.id} {...commonProps}>
                                            <Rect
                                                width={w}
                                                height={h}
                                                offsetX={w / 2}
                                                offsetY={h / 2}
                                                fill={meta.fill}
                                                stroke={
                                                    isSelected
                                                        ? '#ef4444'
                                                        : meta.stroke
                                                }
                                                strokeWidth={isSelected ? 2 : 1}
                                                cornerRadius={2}
                                            />
                                            {eff.seats ? (
                                                <Text
                                                    text={String(eff.seats)}
                                                    fontSize={11}
                                                    x={-6}
                                                    y={-6}
                                                    fill={meta.stroke}
                                                />
                                            ) : null}
                                            {eff.label ? (
                                                <Text
                                                    text={eff.label}
                                                    fontSize={11}
                                                    align="center"
                                                    width={w}
                                                    offsetX={w / 2}
                                                    y={h / 2 + 4}
                                                    fill={meta.stroke}
                                                />
                                            ) : null}
                                        </Group>
                                    );
                                })}
                            </Layer>
                        </Stage>
                    </div>
                </div>
            </div>
        </>
    );
}

function PropertiesPanel({
    object,
    meta,
    onUpdate,
    onDelete,
    disabled,
}: {
    object: DiagramObject;
    meta: PaletteItem | undefined;
    onUpdate: (
        patch:
            | Partial<Pick<DiagramObject, 'rotation'>>
            | { props: DiagramObject['props'] },
    ) => void;
    onDelete: () => void;
    disabled: boolean;
}) {
    if (!meta) {
        return null;
    }

    const widthFt = object.props?.width_ft ?? meta.width_ft;
    const heightFt = object.props?.height_ft ?? meta.height_ft;
    const seats = object.props?.seats ?? meta.seats ?? 0;
    const rotation = object.rotation ?? 0;
    const label = object.props?.label ?? '';

    const onNum = (key: 'width_ft' | 'height_ft' | 'seats', raw: string) => {
        const n = Number(raw);
        onUpdate({ props: { [key]: Number.isFinite(n) ? n : 0 } });
    };

    return (
        <div
            data-tour-id="diagram-properties"
            className="mt-4 flex flex-col gap-2 rounded-md border border-sidebar-border/70 p-2 text-xs dark:border-sidebar-border"
        >
            <div className="flex items-center justify-between">
                <span className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                    Properties
                </span>
                <span className="text-[10px] text-muted-foreground">
                    {meta.label}
                </span>
            </div>

            <label className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">Width (ft)</span>
                <input
                    type="number"
                    min={0.5}
                    step={0.5}
                    value={widthFt}
                    disabled={disabled}
                    onChange={(e) => onNum('width_ft', e.target.value)}
                    className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                />
            </label>

            <label className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">Height (ft)</span>
                <input
                    type="number"
                    min={0.5}
                    step={0.5}
                    value={heightFt}
                    disabled={disabled}
                    onChange={(e) => onNum('height_ft', e.target.value)}
                    className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                />
            </label>

            <label className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">Rotation (°)</span>
                <input
                    type="number"
                    step={1}
                    value={rotation}
                    disabled={disabled}
                    onChange={(e) => {
                        const n = Number(e.target.value);
                        onUpdate({
                            rotation: Number.isFinite(n) ? n : 0,
                        });
                    }}
                    className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                />
            </label>

            {meta.seats !== undefined && (
                <label className="flex items-center justify-between gap-2">
                    <span className="text-muted-foreground">Seats</span>
                    <input
                        type="number"
                        min={0}
                        step={1}
                        value={seats}
                        disabled={disabled}
                        onChange={(e) => onNum('seats', e.target.value)}
                        className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                    />
                </label>
            )}

            <label className="flex flex-col gap-1">
                <span className="text-muted-foreground">Label</span>
                <input
                    type="text"
                    value={label}
                    disabled={disabled}
                    placeholder="(none)"
                    onChange={(e) =>
                        onUpdate({
                            props: {
                                label:
                                    e.target.value.length > 0
                                        ? e.target.value
                                        : undefined,
                            },
                        })
                    }
                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 dark:border-sidebar-border"
                />
            </label>

            <button
                type="button"
                onClick={onDelete}
                disabled={disabled}
                className="mt-1 rounded-md border border-rose-300 px-2 py-1 text-rose-700 hover:bg-rose-50 disabled:opacity-40 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
            >
                Delete object
            </button>
        </div>
    );
}

function TemplatePicker({
    templates,
    hasObjects,
    onApply,
    onClose,
}: {
    templates: Template[];
    hasObjects: boolean;
    onApply: (template: Template, mode: 'replace' | 'append') => void;
    onClose: () => void;
}) {
    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-background p-3 shadow-sm dark:border-sidebar-border">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-sm font-semibold">
                    Pick a layout template
                </h2>
                <button
                    onClick={onClose}
                    className="rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                >
                    Cancel
                </button>
            </div>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2 lg:grid-cols-3">
                {templates.map((t) => (
                    <div
                        key={t.id}
                        className="flex flex-col gap-2 rounded-lg border border-sidebar-border/60 p-3 dark:border-sidebar-border"
                    >
                        <div>
                            <div className="flex items-baseline justify-between gap-2">
                                <span className="text-sm font-medium">
                                    {t.name}
                                </span>
                                {t.is_global && (
                                    <span className="rounded-sm bg-muted px-1.5 py-0.5 text-[10px] tracking-wider text-muted-foreground uppercase">
                                        global
                                    </span>
                                )}
                            </div>
                            {t.description && (
                                <div className="mt-0.5 text-xs text-muted-foreground">
                                    {t.description}
                                </div>
                            )}
                            <div className="mt-1 text-[11px] text-muted-foreground">
                                {t.object_count} objects · {t.seat_count} seats
                                {t.category ? ` · ${t.category}` : ''}
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <button
                                onClick={() => onApply(t, 'replace')}
                                className="flex-1 rounded-md bg-foreground px-2 py-1 text-xs font-medium text-background hover:opacity-90"
                            >
                                {hasObjects ? 'Replace' : 'Apply'}
                            </button>
                            {hasObjects && (
                                <button
                                    onClick={() => onApply(t, 'append')}
                                    className="flex-1 rounded-md border border-sidebar-border/70 px-2 py-1 text-xs font-medium hover:bg-muted dark:border-sidebar-border"
                                >
                                    Append
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function SaveAsTemplateDialog({
    onSubmit,
    onClose,
}: {
    onSubmit: (form: {
        name: string;
        category: string;
        description: string;
    }) => void;
    onClose: () => void;
}) {
    const [name, setName] = useState('');
    const [category, setCategory] = useState('banquet');
    const [description, setDescription] = useState('');

    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-background p-3 shadow-sm dark:border-sidebar-border">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-sm font-semibold">
                    Save current layout as a template
                </h2>
                <button
                    onClick={onClose}
                    className="rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                >
                    Cancel
                </button>
            </div>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-3">
                <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    placeholder="Template name (required)"
                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                />
                <select
                    value={category}
                    onChange={(e) => setCategory(e.target.value)}
                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                >
                    <option value="banquet">Banquet</option>
                    <option value="classroom">Classroom</option>
                    <option value="theater">Theater</option>
                    <option value="u_shape">U-shape</option>
                    <option value="booth_grid">Booth grid</option>
                    <option value="reception">Reception</option>
                    <option value="other">Other</option>
                </select>
                <input
                    type="text"
                    value={description}
                    onChange={(e) => setDescription(e.target.value)}
                    placeholder="Description (optional)"
                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                />
            </div>
            <div className="mt-3 flex justify-end">
                <button
                    onClick={() => onSubmit({ name, category, description })}
                    disabled={name.trim().length === 0}
                    className="rounded-md bg-foreground px-3 py-1.5 text-sm font-medium text-background disabled:opacity-40"
                >
                    Save template
                </button>
            </div>
        </div>
    );
}

function AutoLayoutDialog({
    hasObjects,
    onApply,
    onClose,
}: {
    hasObjects: boolean;
    onApply: (opts: AutoLayoutOpts, mode: 'replace' | 'append') => void;
    onClose: () => void;
}) {
    const [headcount, setHeadcount] = useState(80);
    const [tableType, setTableType] = useState<
        'round_table_60' | 'round_table_72'
    >('round_table_60');
    const [bufferFt, setBufferFt] = useState(4);
    const [includeStage, setIncludeStage] = useState(true);

    const seatsPerTable = tableType === 'round_table_72' ? 10 : 8;
    const numTables = Math.max(1, Math.ceil(headcount / seatsPerTable));
    const totalSeats = numTables * seatsPerTable;

    return (
        <div className="rounded-xl border border-sidebar-border/70 bg-background p-3 shadow-sm dark:border-sidebar-border">
            <div className="mb-2 flex items-center justify-between">
                <h2 className="text-sm font-semibold">
                    Auto-layout banquet rounds
                </h2>
                <button
                    onClick={onClose}
                    className="rounded-md px-2 py-1 text-xs text-muted-foreground hover:bg-muted"
                >
                    Cancel
                </button>
            </div>
            <div className="grid grid-cols-1 gap-2 md:grid-cols-4">
                <label className="flex flex-col gap-1 text-xs">
                    <span className="text-muted-foreground">Headcount</span>
                    <input
                        type="number"
                        min={1}
                        step={1}
                        value={headcount}
                        onChange={(e) =>
                            setHeadcount(Number(e.target.value) || 0)
                        }
                        data-tour-id="auto-layout-headcount"
                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                    />
                </label>
                <label className="flex flex-col gap-1 text-xs">
                    <span className="text-muted-foreground">Table type</span>
                    <select
                        value={tableType}
                        onChange={(e) =>
                            setTableType(
                                e.target.value as
                                    | 'round_table_60'
                                    | 'round_table_72',
                            )
                        }
                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                    >
                        <option value="round_table_60">
                            60&quot; round · seats 8
                        </option>
                        <option value="round_table_72">
                            72&quot; round · seats 10
                        </option>
                    </select>
                </label>
                <label className="flex flex-col gap-1 text-xs">
                    <span className="text-muted-foreground">Buffer (ft)</span>
                    <input
                        type="number"
                        min={2}
                        max={12}
                        step={0.5}
                        value={bufferFt}
                        onChange={(e) =>
                            setBufferFt(Number(e.target.value) || 0)
                        }
                        className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1.5 text-sm dark:border-sidebar-border"
                    />
                </label>
                <label className="flex items-end gap-2 text-xs">
                    <input
                        type="checkbox"
                        checked={includeStage}
                        onChange={(e) => setIncludeStage(e.target.checked)}
                    />
                    <span>Include 4x8 stage</span>
                </label>
            </div>
            <div className="mt-2 text-xs text-muted-foreground">
                Will place <strong>{numTables}</strong> tables ({totalSeats}{' '}
                seats) at {bufferFt} ft buffer.
            </div>
            <div className="mt-3 flex justify-end gap-2">
                <Button
                    size="sm"
                    onClick={() =>
                        onApply(
                            {
                                headcount,
                                table_type: tableType,
                                buffer_ft: bufferFt,
                                include_stage: includeStage,
                            },
                            'replace',
                        )
                    }
                    data-tour-id="auto-layout-apply"
                >
                    {hasObjects ? 'Replace canvas' : 'Generate'}
                </Button>
                {hasObjects && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            onApply(
                                {
                                    headcount,
                                    table_type: tableType,
                                    buffer_ft: bufferFt,
                                    include_stage: includeStage,
                                },
                                'append',
                            )
                        }
                    >
                        Append
                    </Button>
                )}
            </div>
        </div>
    );
}

DiagramPage.layout = {
    breadcrumbs: [
        { title: 'Bookings', href: '/bookings' },
        { title: 'Floor plan', href: '#' },
    ],
};
