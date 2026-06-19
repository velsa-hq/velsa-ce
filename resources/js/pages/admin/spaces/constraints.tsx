import { Head, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { Circle, Group, Layer, Line, Rect, Stage, Text } from 'react-konva';

import { useMeasurement } from '@/hooks/use-measurement';
import type { Constraint } from '@/lib/diagram-geometry';

type Props = {
    venue: { id: number; name: string; slug: string };
    space: {
        id: number;
        name: string;
        sqft: number | null;
        capacity: number | null;
    };
    constraints: Constraint[];
    scale_px_per_foot: number;
};

type PaletteItem = {
    kind: Constraint['kind'];
    label: string;
    shape: 'rect' | 'circle';
    width_ft: number;
    height_ft: number;
    fill: string;
    stroke: string;
};

const PALETTE: PaletteItem[] = [
    {
        kind: 'wall',
        label: 'Wall (10 ft)',
        shape: 'rect',
        width_ft: 10,
        height_ft: 0.5,
        fill: '#475569',
        stroke: '#1e293b',
    },
    {
        kind: 'door',
        label: 'Door (3 ft)',
        shape: 'rect',
        width_ft: 3,
        height_ft: 0.5,
        fill: '#b45309',
        stroke: '#78350f',
    },
    {
        kind: 'window',
        label: 'Window (6 ft)',
        shape: 'rect',
        width_ft: 6,
        height_ft: 0.4,
        fill: '#7dd3fc',
        stroke: '#0369a1',
    },
    {
        kind: 'column',
        label: 'Column 2x2',
        shape: 'rect',
        width_ft: 2,
        height_ft: 2,
        fill: '#64748b',
        stroke: '#1e293b',
    },
    {
        kind: 'post',
        label: 'Post 1x1',
        shape: 'rect',
        width_ft: 1,
        height_ft: 1,
        fill: '#64748b',
        stroke: '#1e293b',
    },
    {
        kind: 'outlet',
        label: 'Outlet',
        shape: 'circle',
        width_ft: 0.6,
        height_ft: 0.6,
        fill: '#facc15',
        stroke: '#92400e',
    },
];

const STAGE_WIDTH = 1000;
const STAGE_HEIGHT = 600;
const GRID_FT = 1;

function paletteFor(kind: Constraint['kind']): PaletteItem | undefined {
    return PALETTE.find((p) => p.kind === kind);
}

export default function SpaceConstraintsPage({
    venue,
    space,
    constraints: initial,
    scale_px_per_foot,
}: Props) {
    const ppf = scale_px_per_foot;
    const gridPx = GRID_FT * ppf;
    const { formatArea } = useMeasurement();
    const [constraints, setConstraints] = useState<Constraint[]>(initial ?? []);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [dirty, setDirty] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.key === 'Delete' || e.key === 'Backspace') && selectedId) {
                e.preventDefault();
                setConstraints((prev) =>
                    prev.filter((c) => c.id !== selectedId),
                );
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

    const selected = useMemo(
        () => constraints.find((c) => c.id === selectedId) ?? null,
        [constraints, selectedId],
    );

    function addFromPalette(p: PaletteItem) {
        const id = `con_${Date.now()}_${Math.random().toString(36).slice(2, 7)}`;
        setConstraints((prev) => [
            ...prev,
            {
                id,
                kind: p.kind,
                x: STAGE_WIDTH / 2,
                y: STAGE_HEIGHT / 2,
                width_ft: p.width_ft,
                height_ft: p.height_ft,
                rotation: 0,
            },
        ]);
        setSelectedId(id);
        setDirty(true);
    }

    function onDragEnd(id: string, x: number, y: number) {
        const sx = Math.round(x / gridPx) * gridPx;
        const sy = Math.round(y / gridPx) * gridPx;
        setConstraints((prev) =>
            prev.map((c) => (c.id === id ? { ...c, x: sx, y: sy } : c)),
        );
        setDirty(true);
    }

    function updateSelected(patch: Partial<Constraint>) {
        if (!selectedId) {
            return;
        }

        setConstraints((prev) =>
            prev.map((c) => (c.id === selectedId ? { ...c, ...patch } : c)),
        );
        setDirty(true);
    }

    function save() {
        router.post(
            `/admin/spaces/${space.id}/constraints`,
            { constraints },
            { preserveScroll: true, onSuccess: () => setDirty(false) },
        );
    }

    return (
        <>
            <Head title={`${space.name} · Floor constraints`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {space.name} · Floor constraints
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {venue.name}
                            {space.sqft ? ` · ${formatArea(space.sqft)}` : ''}
                            {space.capacity ? ` · cap ${space.capacity}` : ''}
                            {' · '}
                            Permanent infrastructure - walls, doors, columns,
                            outlets. Renders as a backdrop on every booking's
                            floor plan.
                        </p>
                    </div>
                    <button
                        onClick={save}
                        disabled={!dirty}
                        data-tour-id="admin-sc-save"
                        className="rounded-md bg-foreground px-3 py-1.5 text-sm font-medium text-background disabled:opacity-40"
                    >
                        {dirty ? 'Save' : 'Saved'}
                    </button>
                </header>

                <div className="flex gap-4">
                    <aside className="w-56 shrink-0 rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border">
                        <h3 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                            Constraint library
                        </h3>
                        <div className="flex flex-col gap-1.5">
                            {PALETTE.map((item) => (
                                <button
                                    key={item.kind}
                                    onClick={() => addFromPalette(item)}
                                    data-tour-id={`admin-sc-add-${item.kind}`}
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
                                {constraints.length} features
                            </div>
                            <div className="mt-1 text-muted-foreground">
                                Press{' '}
                                <kbd className="rounded bg-background px-1 font-mono">
                                    Del
                                </kbd>{' '}
                                to remove selected
                            </div>
                        </div>

                        {selected && (
                            <ConstraintProperties
                                constraint={selected}
                                onUpdate={updateSelected}
                                onDelete={() => {
                                    setConstraints((prev) =>
                                        prev.filter(
                                            (c) => c.id !== selected.id,
                                        ),
                                    );
                                    setSelectedId(null);
                                    setDirty(true);
                                }}
                            />
                        )}
                    </aside>

                    <div
                        role="region"
                        aria-label="Constraint editor"
                        className="flex-1 overflow-auto rounded-xl border border-sidebar-border/70 bg-white dark:border-sidebar-border dark:bg-neutral-900"
                    >
                        <Stage
                            width={STAGE_WIDTH}
                            height={STAGE_HEIGHT}
                            onClick={(e) => {
                                if (e.target === e.target.getStage()) {
                                    setSelectedId(null);
                                }
                            }}
                        >
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
                            <Layer>
                                {constraints.map((c) => {
                                    const p = paletteFor(c.kind);

                                    if (!p) {
                                        return null;
                                    }

                                    const w = c.width_ft * ppf;
                                    const h = c.height_ft * ppf;
                                    const isSelected = c.id === selectedId;
                                    const stroke = isSelected
                                        ? '#ef4444'
                                        : p.stroke;
                                    const strokeWidth = isSelected ? 2 : 1;
                                    const commonProps = {
                                        x: c.x,
                                        y: c.y,
                                        rotation: c.rotation ?? 0,
                                        draggable: true,
                                        onClick: () => setSelectedId(c.id),
                                        onTap: () => setSelectedId(c.id),
                                        onDragEnd: (e: any) =>
                                            onDragEnd(
                                                c.id,
                                                e.target.x(),
                                                e.target.y(),
                                            ),
                                    };

                                    if (p.shape === 'circle') {
                                        return (
                                            <Group key={c.id} {...commonProps}>
                                                <Circle
                                                    radius={w / 2}
                                                    fill={p.fill}
                                                    stroke={stroke}
                                                    strokeWidth={strokeWidth}
                                                />
                                            </Group>
                                        );
                                    }

                                    return (
                                        <Group key={c.id} {...commonProps}>
                                            <Rect
                                                width={w}
                                                height={h}
                                                offsetX={w / 2}
                                                offsetY={h / 2}
                                                fill={p.fill}
                                                stroke={stroke}
                                                strokeWidth={strokeWidth}
                                            />
                                            {c.label ? (
                                                <Text
                                                    text={c.label}
                                                    fontSize={10}
                                                    align="center"
                                                    width={w}
                                                    offsetX={w / 2}
                                                    y={h / 2 + 2}
                                                    fill={p.stroke}
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

function ConstraintProperties({
    constraint,
    onUpdate,
    onDelete,
}: {
    constraint: Constraint;
    onUpdate: (patch: Partial<Constraint>) => void;
    onDelete: () => void;
}) {
    return (
        <div
            data-tour-id="admin-sc-properties"
            className="mt-4 flex flex-col gap-2 rounded-md border border-sidebar-border/70 p-2 text-xs dark:border-sidebar-border"
        >
            <div className="flex items-center justify-between">
                <span className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                    Properties
                </span>
                <span className="text-[10px] text-muted-foreground capitalize">
                    {constraint.kind}
                </span>
            </div>

            <label className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">Width (ft)</span>
                <input
                    type="number"
                    min={0.1}
                    step={0.5}
                    value={constraint.width_ft}
                    onChange={(e) =>
                        onUpdate({ width_ft: Number(e.target.value) || 0.1 })
                    }
                    className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                />
            </label>

            <label className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">Height (ft)</span>
                <input
                    type="number"
                    min={0.1}
                    step={0.5}
                    value={constraint.height_ft}
                    onChange={(e) =>
                        onUpdate({ height_ft: Number(e.target.value) || 0.1 })
                    }
                    className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                />
            </label>

            <label className="flex items-center justify-between gap-2">
                <span className="text-muted-foreground">Rotation (°)</span>
                <input
                    type="number"
                    step={1}
                    value={constraint.rotation ?? 0}
                    onChange={(e) =>
                        onUpdate({ rotation: Number(e.target.value) || 0 })
                    }
                    className="w-20 rounded-md border border-sidebar-border/70 bg-background px-2 py-0.5 text-right dark:border-sidebar-border"
                />
            </label>

            <label className="flex flex-col gap-1">
                <span className="text-muted-foreground">Label</span>
                <input
                    type="text"
                    value={constraint.label ?? ''}
                    placeholder="(optional)"
                    onChange={(e) =>
                        onUpdate({
                            label:
                                e.target.value.length > 0
                                    ? e.target.value
                                    : undefined,
                        })
                    }
                    className="rounded-md border border-sidebar-border/70 bg-background px-2 py-1 dark:border-sidebar-border"
                />
            </label>

            <button
                type="button"
                onClick={onDelete}
                className="mt-1 rounded-md border border-rose-300 px-2 py-1 text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-300 dark:hover:bg-rose-950/40"
            >
                Delete constraint
            </button>
        </div>
    );
}

SpaceConstraintsPage.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin' },
        { title: 'Spaces', href: '#' },
        { title: 'Constraints', href: '#' },
    ],
};
