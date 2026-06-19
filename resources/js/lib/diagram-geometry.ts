/**
 * Pure geometry helpers for the floor-plan editor, split out so the
 * auto-layout constraint math can be unit-tested without React + Konva.
 */

export type ConstraintKind =
    | 'wall'
    | 'door'
    | 'window'
    | 'column'
    | 'outlet'
    | 'post';

export type Constraint = {
    id: string;
    kind: ConstraintKind;
    /** Canvas pixel x of the constraint's center. */
    x: number;
    /** Canvas pixel y of the constraint's center. */
    y: number;
    width_ft: number;
    height_ft: number;
    /** Rotation in degrees about the center. Defaults to 0. */
    rotation?: number;
    label?: string;
};

export type Bounds = {
    left: number;
    right: number;
    top: number;
    bottom: number;
    cx: number;
    cy: number;
};

/**
 * Axis-aligned bounding box of a constraint, accounting for rotation.
 * For a W x H rect rotated theta: aabbW = |W*cos| + |H*sin|,
 * aabbH = |W*sin| + |H*cos|.
 */
export function constraintAABB(c: Constraint, ppf: number): Bounds {
    const w = c.width_ft * ppf;
    const h = c.height_ft * ppf;
    const theta = ((c.rotation ?? 0) * Math.PI) / 180;
    const cos = Math.abs(Math.cos(theta));
    const sin = Math.abs(Math.sin(theta));
    const aabbW = w * cos + h * sin;
    const aabbH = w * sin + h * cos;

    return {
        left: c.x - aabbW / 2,
        right: c.x + aabbW / 2,
        top: c.y - aabbH / 2,
        bottom: c.y + aabbH / 2,
        cx: c.x,
        cy: c.y,
    };
}

/**
 * AABB overlap test. Edge-touching boxes count as non-overlapping so
 * tables can sit flush against a wall.
 */
export function boxesOverlap(a: Bounds, b: Bounds): boolean {
    return !(
        a.right < b.left ||
        a.left > b.right ||
        a.bottom < b.top ||
        a.top > b.bottom
    );
}

/**
 * Filename for an exported floor-plan image. Slugs the booking reference so
 * the download is safe across operating systems.
 */
export function floorPlanFileName(reference: string, ext = 'png'): string {
    const slug = reference
        .replace(/[^A-Za-z0-9_-]+/g, '-')
        .replace(/^-+|-+$/g, '');

    return `floor-plan-${slug || 'export'}.${ext}`;
}
