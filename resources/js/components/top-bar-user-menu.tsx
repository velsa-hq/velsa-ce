import { usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';

// top-bar variant of the user menu: plain dropdown trigger for the accent header,
// reusing UserMenuContent for the items
export function TopBarUserMenu() {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <button
                    type="button"
                    className="flex items-center gap-2 rounded-md px-2 py-1 text-sm text-primary-foreground hover:bg-primary-foreground/10 focus-visible:ring-2 focus-visible:ring-primary-foreground/40 focus-visible:outline-none"
                    aria-label="Open user menu"
                >
                    <UserInfo user={auth.user} />
                    <ChevronDown className="size-3.5 opacity-70" aria-hidden />
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                side="bottom"
                className="min-w-56 rounded-lg"
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
