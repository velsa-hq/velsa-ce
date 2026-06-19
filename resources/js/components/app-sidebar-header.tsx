// no-op: kept so the layout slot stays stable; per-page H1s do the wayfinding
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

// props kept for slot compatibility; breadcrumbs not rendered
// eslint-disable-next-line @typescript-eslint/no-unused-vars
export function AppSidebarHeader(_props: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return null;
}
