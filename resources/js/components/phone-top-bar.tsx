import { usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';

/**
 * Top app bar for phone widths. The sidebar is hidden on a phone, so this is
 * where the person finds their name, email, Account and Log out. It has the
 * same menu content as the desktop sidebar user menu.
 */
export function PhoneTopBar() {
    const { auth, currentTeam, name } = usePage().props;
    const getInitials = useInitials();
    const user = auth.user;

    if (!user) {
        return null;
    }

    return (
        <header
            data-test="phone-top-bar"
            className="flex h-14 items-center justify-between gap-3 border-b bg-background px-4 md:hidden"
            style={{ paddingTop: 'env(safe-area-inset-top)' }}
        >
            <div className="flex min-w-0 items-center gap-2">
                <AppLogoIcon alt="" className="size-8 shrink-0 rounded-md" />
                <span className="truncate text-sm font-semibold">
                    {currentTeam?.name ?? name}
                </span>
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        data-test="phone-user-menu"
                        aria-label={`Account menu for ${user.name}`}
                        className="flex size-11 shrink-0 items-center justify-center rounded-full focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:outline-none"
                    >
                        <Avatar className="size-9 overflow-hidden rounded-full">
                            {user.avatar ? (
                                <AvatarImage src={user.avatar} alt="" />
                            ) : null}
                            <AvatarFallback className="bg-primary text-primary-foreground">
                                {getInitials(user.name)}
                            </AvatarFallback>
                        </Avatar>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    sideOffset={4}
                    collisionPadding={8}
                    className="min-w-56 rounded-lg"
                >
                    <UserMenuContent user={user} />
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}
