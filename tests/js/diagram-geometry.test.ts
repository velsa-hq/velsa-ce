import { describe, expect, it } from 'vitest';

import {
    boxesOverlap,
    constraintAABB,
    floorPlanFileName,
} from '@/lib/diagram-geometry';
import type {Bounds, Constraint} from '@/lib/diagram-geometry';

function wall(
    x: number,
    y: number,
    widthFt: number,
    heightFt: number,
    rotation = 0,
): Constraint {
    return {
        id: 'w',
        kind: 'wall',
        x,
        y,
        width_ft: widthFt,
        height_ft: heightFt,
        rotation,
    };
}

function bounds(left: number, top: number, right: number, bottom: number): Bounds {
    return {
        left,
        right,
        top,
        bottom,
        cx: (left + right) / 2,
        cy: (top + bottom) / 2,
    };
}

describe('constraintAABB', () => {
    const ppf = 10;

    it('produces the raw rectangle for an axis-aligned (0°) wall', () => {
        // 10 ft x 0.5 ft wall at (100, 100) with ppf=10 -> 100 x 5 px.
        const aabb = constraintAABB(wall(100, 100, 10, 0.5, 0), ppf);

        expect(aabb.left).toBeCloseTo(50);
        expect(aabb.right).toBeCloseTo(150);
        expect(aabb.top).toBeCloseTo(97.5);
        expect(aabb.bottom).toBeCloseTo(102.5);
        expect(aabb.cx).toBe(100);
        expect(aabb.cy).toBe(100);
    });

    it('swaps width and height for a 90° rotated wall', () => {
        // Same wall but rotated 90°. The AABB should be 5 x 100 px.
        const aabb = constraintAABB(wall(100, 100, 10, 0.5, 90), ppf);

        expect(aabb.right - aabb.left).toBeCloseTo(5);
        expect(aabb.bottom - aabb.top).toBeCloseTo(100);
    });

    it('expands diagonally for a 45° rotated wall', () => {
        // 10 ft x 10 ft square at 45° has an AABB of side
        // 10·√2 ≈ 14.14 ft -> 141.4 px.
        const aabb = constraintAABB(
            wall(100, 100, 10, 10, 45),
            ppf,
        );
        const expectedSide = 10 * Math.SQRT2 * ppf;

        expect(aabb.right - aabb.left).toBeCloseTo(expectedSide, 4);
        expect(aabb.bottom - aabb.top).toBeCloseTo(expectedSide, 4);
    });

    it('treats undefined rotation as 0°', () => {
        const explicit = constraintAABB(wall(50, 50, 4, 2, 0), ppf);
        const implicit = constraintAABB(
            { id: 'x', kind: 'column', x: 50, y: 50, width_ft: 4, height_ft: 2 },
            ppf,
        );

        expect(implicit).toEqual({ ...explicit, cx: 50, cy: 50 });
    });

    it('scales linearly with ppf', () => {
        const at10 = constraintAABB(wall(0, 0, 6, 2, 0), 10);
        const at20 = constraintAABB(wall(0, 0, 6, 2, 0), 20);

        expect(at20.right - at20.left).toBeCloseTo(
            2 * (at10.right - at10.left),
        );
        expect(at20.bottom - at20.top).toBeCloseTo(
            2 * (at10.bottom - at10.top),
        );
    });

    it('is invariant under 180° rotation (rectangle symmetry)', () => {
        const at0 = constraintAABB(wall(100, 100, 10, 2), 10);
        const at180 = constraintAABB(wall(100, 100, 10, 2, 180), 10);

        expect(at180.left).toBeCloseTo(at0.left);
        expect(at180.right).toBeCloseTo(at0.right);
        expect(at180.top).toBeCloseTo(at0.top);
        expect(at180.bottom).toBeCloseTo(at0.bottom);
    });
});

describe('boxesOverlap', () => {
    it('detects clearly-overlapping boxes', () => {
        expect(
            boxesOverlap(bounds(0, 0, 50, 50), bounds(25, 25, 75, 75)),
        ).toBe(true);
    });

    it('detects clearly-separated boxes', () => {
        expect(
            boxesOverlap(bounds(0, 0, 10, 10), bounds(100, 100, 110, 110)),
        ).toBe(false);
    });

    it('treats edge-flush boxes as overlapping', () => {
        // a.right === b.left -> strict-< check (a.right < b.left) is
        // false, so the !(...) clause counts this as overlap. Correct
        // for auto-layout: candidate boxes already include the buffer,
        // so edge-flush means there is no buffer between table and
        // constraint.
        expect(
            boxesOverlap(bounds(0, 0, 50, 50), bounds(50, 0, 100, 50)),
        ).toBe(true);
        expect(
            boxesOverlap(bounds(0, 0, 50, 50), bounds(0, 50, 50, 100)),
        ).toBe(true);
    });

    it('detects fully-contained boxes', () => {
        expect(
            boxesOverlap(bounds(0, 0, 100, 100), bounds(40, 40, 60, 60)),
        ).toBe(true);
    });

    it('detects single-axis overlap (vertical strips that share rows)', () => {
        expect(
            boxesOverlap(bounds(0, 0, 10, 100), bounds(5, 50, 15, 60)),
        ).toBe(true);
    });

    it('is symmetric', () => {
        const a = bounds(0, 0, 50, 50);
        const b = bounds(25, 25, 75, 75);
        expect(boxesOverlap(a, b)).toBe(boxesOverlap(b, a));
    });
});

describe('auto-layout style integration: constraintAABB + boxesOverlap', () => {
    const ppf = 10;

    /**
     * Replays the inner loop of generateAutoLayout: for a candidate
     * round-table at position (cx, cy) with a buffer, decide whether to
     * place it given the constraint set.
     */
    function wouldPlaceTable(
        constraints: Constraint[],
        candidate: { cx: number; cy: number; sideFt: number; bufferFt: number },
    ): boolean {
        const sidePx = (candidate.sideFt + 2 * candidate.bufferFt) * ppf;
        const candidateBox: Bounds = {
            left: candidate.cx - sidePx / 2,
            right: candidate.cx + sidePx / 2,
            top: candidate.cy - sidePx / 2,
            bottom: candidate.cy + sidePx / 2,
            cx: candidate.cx,
            cy: candidate.cy,
        };
        const constraintBoxes = constraints.map((c) =>
            constraintAABB(c, ppf),
        );

        return !constraintBoxes.some((cb) => boxesOverlap(candidateBox, cb));
    }

    it('places a table in an empty room', () => {
        expect(
            wouldPlaceTable([], { cx: 500, cy: 500, sideFt: 5, bufferFt: 4 }),
        ).toBe(true);
    });

    it('skips a candidate that would overlap an axis-aligned wall', () => {
        const eastWall = wall(900, 500, 0.5, 50);
        expect(
            wouldPlaceTable([eastWall], {
                cx: 895,
                cy: 500,
                sideFt: 5,
                bufferFt: 4,
            }),
        ).toBe(false);
    });

    it('skips a candidate that overlaps the AABB of a 45° rotated wall', () => {
        // 20 ft x 0.5 ft wall at 45° centered at (500, 500). Its AABB
        // is ~14.5 x 14.5 ft -> ~145 px on each side.
        const diagonal = wall(500, 500, 20, 0.5, 45);
        expect(
            wouldPlaceTable([diagonal], {
                cx: 530,
                cy: 530,
                sideFt: 5,
                bufferFt: 4,
            }),
        ).toBe(false);
    });

    it('places a candidate well clear of every constraint', () => {
        const column = wall(100, 100, 2, 2);
        expect(
            wouldPlaceTable([column], {
                cx: 800,
                cy: 800,
                sideFt: 5,
                bufferFt: 4,
            }),
        ).toBe(true);
    });

    it('the partial-fit count matches the manually-computed skip list', () => {
        // Two interior columns; a 3x3 grid of candidate slots; one
        // candidate sits on top of a column.
        const constraints = [
            wall(200, 200, 4, 4), // column 1 - 4x4 ft -> 40x40 px AABB
            wall(400, 400, 4, 4), // column 2
        ];
        const candidates = [
            { cx: 100, cy: 100 },
            { cx: 200, cy: 200 }, // overlaps column 1
            { cx: 300, cy: 300 },
            { cx: 400, cy: 400 }, // overlaps column 2
            { cx: 500, cy: 500 },
        ].map((c) => ({ ...c, sideFt: 5, bufferFt: 4 }));

        const placed = candidates.filter((c) =>
            wouldPlaceTable(constraints, c),
        );

        expect(placed.length).toBe(3);
    });
});

describe('floorPlanFileName', () => {
    it('builds a slugged png filename from a booking reference', () => {
        expect(floorPlanFileName('BK-2026-00042')).toBe('floor-plan-BK-2026-00042.png');
    });

    it('sanitizes unsafe characters and honors a custom extension', () => {
        expect(floorPlanFileName('BK 2026/00042', 'pdf')).toBe('floor-plan-BK-2026-00042.pdf');
    });

    it('falls back when the reference has no safe characters', () => {
        expect(floorPlanFileName('///')).toBe('floor-plan-export.png');
    });
});
