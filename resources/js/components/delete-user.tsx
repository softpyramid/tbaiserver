import { Form, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Danger zone for the Account page. It sits below every routine setting, and
 * the delete button only works after the person types their account email.
 */
export default function DeleteUser() {
    const { auth } = usePage().props;
    const passwordInput = useRef<HTMLInputElement>(null);
    const [typedEmail, setTypedEmail] = useState('');
    const email = auth.user.email;
    const emailMatches =
        typedEmail.trim().toLowerCase() === email.trim().toLowerCase();

    return (
        <section
            aria-labelledby="danger-zone-heading"
            data-test="danger-zone"
            className="mt-12 space-y-4 border-t pt-8"
        >
            <div className="space-y-1">
                <h2
                    id="danger-zone-heading"
                    className="text-base font-semibold text-destructive dark:text-destructive-foreground"
                >
                    Danger zone
                </h2>
                <p className="text-sm text-muted-foreground">
                    Actions here cannot be undone.
                </p>
            </div>

            <div className="space-y-4 rounded-lg border border-destructive/30 p-4">
                <div className="space-y-0.5">
                    <p className="font-medium">Delete account</p>
                    <p className="text-sm text-muted-foreground">
                        Delete your account and all of its resources. You will
                        be asked to type your email address to confirm.
                    </p>
                </div>

                <Dialog
                    onOpenChange={(open) => {
                        if (!open) {
                            setTypedEmail('');
                        }
                    }}
                >
                    <DialogTrigger asChild>
                        <Button
                            variant="destructive"
                            data-test="delete-user-button"
                        >
                            Delete account
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogTitle>
                            Are you sure you want to delete your account?
                        </DialogTitle>
                        <DialogDescription>
                            Once your account is deleted, all of its resources
                            and data will also be permanently deleted. Type your
                            email address <strong>{email}</strong> and enter
                            your password to confirm.
                        </DialogDescription>

                        <Form
                            {...ProfileController.destroy.form()}
                            options={{
                                preserveScroll: true,
                            }}
                            onError={() => passwordInput.current?.focus()}
                            resetOnSuccess
                            className="space-y-6"
                        >
                            {({ resetAndClearErrors, processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirm_email">
                                            Type your email address
                                        </Label>

                                        <Input
                                            id="confirm_email"
                                            name="confirm_email"
                                            type="email"
                                            autoComplete="off"
                                            placeholder={email}
                                            value={typedEmail}
                                            onChange={(event) =>
                                                setTypedEmail(
                                                    event.target.value,
                                                )
                                            }
                                            data-test="delete-user-email"
                                        />

                                        <InputError
                                            message={errors.confirm_email}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label
                                            htmlFor="password"
                                            className="sr-only"
                                        >
                                            Password
                                        </Label>

                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            ref={passwordInput}
                                            placeholder="Password"
                                            autoComplete="current-password"
                                        />

                                        <InputError message={errors.password} />
                                    </div>

                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                variant="secondary"
                                                onClick={() => {
                                                    resetAndClearErrors();
                                                    setTypedEmail('');
                                                }}
                                            >
                                                Cancel
                                            </Button>
                                        </DialogClose>

                                        <Button
                                            variant="destructive"
                                            disabled={
                                                processing || !emailMatches
                                            }
                                            asChild
                                        >
                                            <button
                                                type="submit"
                                                data-test="confirm-delete-user-button"
                                            >
                                                Delete account
                                            </button>
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
        </section>
    );
}
