import { router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useIsMobile } from '@/hooks/use-mobile';
import { useEntryI18n } from '@/lib/i18n';
import { switchMethod } from '@/routes/teams';
import type { Team } from '@/types';

type TeamSwitcherProps = {
    inHeader?: boolean;
};

export function TeamSwitcher({ inHeader = false }: TeamSwitcherProps) {
    const page = usePage();
    const { t } = useEntryI18n();
    const isMobile = useIsMobile();
    const scopeLabel =
        typeof page.props.scopeLabel === 'string' ? page.props.scopeLabel : null;
    const facilities = (page.props.teams ?? []).filter((team) => !team.isPersonal);
    const currentTeam = page.props.currentTeam;
    const current =
        currentTeam && !currentTeam.isPersonal
            ? currentTeam
            : (facilities.find((team) => team.isCurrent) ?? facilities[0] ?? null);

    if (scopeLabel) {
        return (
            <p
                data-test="scope-label"
                className={
                    inHeader
                        ? 'max-w-[160px] truncate px-2 text-sm'
                        : 'truncate px-2 text-sm group-data-[collapsible=icon]:hidden'
                }
            >
                <span className="text-muted-foreground">{t('scope_prefix')}: </span>
                <span className="font-medium">{scopeLabel}</span>
            </p>
        );
    }

    if (facilities.length < 2 || current === null) {
        if (current === null) {
            return null;
        }

        return (
            <p
                data-test="current-facility"
                className={
                    inHeader
                        ? 'max-w-[160px] truncate px-2 text-sm font-medium'
                        : 'truncate px-2 text-sm font-medium group-data-[collapsible=icon]:hidden'
                }
            >
                {current.name}
            </p>
        );
    }

    const switchFacility = (team: Team) => {
        const previousSlug = current.slug;

        router.visit(switchMethod(team.slug), {
            onFinish: () => {
                if (!previousSlug || typeof window === 'undefined') {
                    router.reload();

                    return;
                }

                const currentUrl = `${window.location.pathname}${window.location.search}${window.location.hash}`;
                const segment = `/${previousSlug}`;

                if (currentUrl.includes(segment)) {
                    router.visit(currentUrl.replace(segment, `/${team.slug}`), {
                        replace: true,
                    });

                    return;
                }

                router.reload();
            },
        });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    aria-label={t('switch_facility')}
                    data-test="team-switcher-trigger"
                    className={
                        inHeader
                            ? 'h-8 gap-1 px-2'
                            : 'w-full justify-start px-2 has-[>svg]:px-2 data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground'
                    }
                >
                    <div
                        className={
                            inHeader
                                ? 'grid flex-1 text-left text-sm leading-tight'
                                : 'grid flex-1 text-left text-sm leading-tight group-data-[collapsible=icon]:hidden'
                        }
                    >
                        <span className="text-xs text-muted-foreground">
                            {t('switch_facility')}
                        </span>
                        <span
                            className={
                                inHeader
                                    ? 'max-w-[120px] truncate font-medium'
                                    : 'truncate font-semibold'
                            }
                        >
                            {current.name}
                        </span>
                    </div>
                    <ChevronsUpDown
                        className={
                            inHeader
                                ? 'size-4 opacity-50'
                                : 'ml-auto group-data-[collapsible=icon]:hidden'
                        }
                    />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className={
                    inHeader
                        ? 'w-56'
                        : 'w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg'
                }
                side={inHeader ? undefined : isMobile ? 'bottom' : 'right'}
                align={inHeader ? 'end' : 'start'}
                sideOffset={inHeader ? undefined : 4}
            >
                <DropdownMenuLabel className="text-xs text-muted-foreground">
                    {t('switch_facility')}
                </DropdownMenuLabel>
                {facilities.map((team) => (
                    <DropdownMenuItem
                        key={team.id}
                        data-test="team-switcher-item"
                        className={
                            inHeader
                                ? 'cursor-pointer gap-2'
                                : 'cursor-pointer gap-2 p-2'
                        }
                        onSelect={() => switchFacility(team)}
                    >
                        {team.name}
                        {current.id === team.id && (
                            <Check
                                className={
                                    inHeader
                                        ? 'ml-auto size-4'
                                        : 'ml-auto h-4 w-4'
                                }
                            />
                        )}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
