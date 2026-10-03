import { router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import type { RowAction } from '@/types/team-area';

type Props = {
    actions: RowAction[];
    label: string;
};

function postAction(action: Extract<RowAction, { kind: 'post' }>): void {
    router.post(action.url, action.fields, { preserveScroll: true });
}

function pick(action: RowAction, role: string | undefined, confirm: (action: Extract<RowAction, { kind: 'post' }>) => void): void {
    switch (action.kind) {
        case 'roles':
            if (role !== undefined) {
                router.patch(action.url, { role }, { preserveScroll: true });
            }
            return;
        case 'post':
            if (action.confirm) {
                confirm(action);
                return;
            }
            postAction(action);
            return;
        case 'delete':
            router.delete(action.url, { preserveScroll: true });
            return;
        default: {
            const unexpected: never = action;
            return unexpected;
        }
    }
}

function ActionList({
    actions,
    onPick,
}: {
    actions: RowAction[];
    onPick: (action: RowAction, role?: string) => void;
}) {
    return (
        <div className="flex flex-col gap-1">
            {actions.map((action) => {
                if (action.kind === 'roles') {
                    return (
                        <div key={action.label} className="flex flex-col gap-1">
                            <p className="px-2 pt-2 text-sm font-medium text-foreground">
                                {action.label}
                            </p>
                            {action.options.map((option) => (
                                <Button
                                    key={option.value}
                                    type="button"
                                    variant="ghost"
                                    className="justify-start"
                                    data-test="member-role-option"
                                    onClick={() => onPick(action, option.value)}
                                >
                                    {option.label}
                                </Button>
                            ))}
                        </div>
                    );
                }

                return (
                    <Button
                        key={action.label}
                        type="button"
                        variant="ghost"
                        className="justify-start"
                        onClick={() => onPick(action)}
                    >
                        {action.label}
                    </Button>
                );
            })}
        </div>
    );
}

export default function RowActions({ actions, label }: Props) {
    const [sheetOpen, setSheetOpen] = useState(false);
    const [confirming, setConfirming] = useState<Extract<RowAction, { kind: 'post' }> | null>(null);

    if (actions.length === 0) {
        return null;
    }

    function onPick(action: RowAction, role?: string): void {
        setSheetOpen(false);
        pick(action, role, setConfirming);
    }

    return (
        <>
            <div className="md:hidden">
                <Sheet open={sheetOpen} onOpenChange={setSheetOpen}>
                    <SheetTrigger asChild>
                        <Button type="button" variant="outline" data-test="member-actions">
                            {label}
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="bottom" className="pb-[max(1rem,env(safe-area-inset-bottom))]">
                        <SheetHeader>
                            <SheetTitle>{label}</SheetTitle>
                        </SheetHeader>
                        <ActionList actions={actions} onPick={onPick} />
                    </SheetContent>
                </Sheet>
            </div>

            <div className="hidden md:block">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button type="button" variant="ghost" size="icon" data-test="member-actions" aria-label={label}>
                            <MoreHorizontal />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        {actions.map((action) => {
                            if (action.kind === 'roles') {
                                return action.options.map((option) => (
                                    <DropdownMenuItem
                                        key={option.value}
                                        data-test="member-role-option"
                                        onSelect={() => onPick(action, option.value)}
                                    >
                                        {option.label}
                                    </DropdownMenuItem>
                                ));
                            }

                            return (
                                <DropdownMenuItem key={action.label} onSelect={() => onPick(action)}>
                                    {action.label}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <Dialog open={confirming !== null} onOpenChange={(open) => {
                if (!open) {
                    setConfirming(null);
                }
            }}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{confirming?.label}</DialogTitle>
                        <DialogDescription>{confirming?.confirm}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button type="button" variant="secondary" onClick={() => setConfirming(null)}>
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            data-test="confirm-action"
                            onClick={() => {
                                if (confirming) {
                                    postAction(confirming);
                                }
                                setConfirming(null);
                            }}
                        >
                            {confirming?.label}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
