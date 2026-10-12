import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeInvitation } from '@/routes/teams/invitations';
import type { DesignationOption, RoleOption, Team } from '@/types';

type Props = {
    team: Team;
    availableRoles: RoleOption[];
    /** Shown when the role is a frontline worker (screener). */
    designations?: DesignationOption[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function InviteMemberModal({
    team,
    availableRoles,
    designations = [],
    open,
    onOpenChange,
}: Props) {
    const [inviteRole, setInviteRole] = useState<RoleOption['value']>('member');
    const [designation, setDesignation] = useState('');
    const needsDesignation = inviteRole === 'screener';

    const handleOpenChange = (nextOpen: boolean) => {
        onOpenChange(nextOpen);

        if (!nextOpen) {
            setInviteRole('member');
            setDesignation('');
        }
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...storeInvitation.form(team.slug)}
                    className="space-y-6"
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Invite staff</DialogTitle>
                                <DialogDescription>
                                    Send an email invitation. This does not
                                    create a public signup.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        data-test="invite-email"
                                        placeholder="colleague@example.com"
                                        required
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="role">Role</Label>
                                    <Select
                                        name="role"
                                        data-test="invite-role"
                                        value={inviteRole}
                                        onValueChange={(value) =>
                                            setInviteRole(
                                                value as RoleOption['value'],
                                            )
                                        }
                                    >
                                        <SelectTrigger className="w-full">
                                            <SelectValue placeholder="Select a role" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableRoles.map((role) => (
                                                <SelectItem
                                                    key={role.value}
                                                    value={role.value}
                                                >
                                                    {role.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.role} />
                                </div>

                                {needsDesignation ? (
                                    <div className="grid gap-2">
                                        <Label htmlFor="designation">
                                            Designation
                                        </Label>
                                        <Select
                                            name="designation"
                                            data-test="invite-designation"
                                            value={designation}
                                            onValueChange={setDesignation}
                                        >
                                            <SelectTrigger
                                                id="designation"
                                                className="w-full"
                                            >
                                                <SelectValue placeholder="Select a designation" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {designations.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            message={errors.designation}
                                        />
                                    </div>
                                ) : null}
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    data-test="invite-submit"
                                    disabled={processing}
                                >
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
