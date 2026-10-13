import { Form, Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { scrollToField } from '@/lib/entry-fields';
import InputError from '@/components/input-error';
import { MarketingShell } from '@/components/marketing-shell';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home, privacy } from '@/routes';
import { store } from '@/routes/access-requests';

const fields = [
    { name: 'name', label: 'Name', type: 'text', autoComplete: 'name' },
    {
        name: 'role',
        label: 'Role',
        type: 'text',
        autoComplete: 'organization-title',
        placeholder: 'e.g. Lab technician, BMU',
    },
    {
        name: 'organisation',
        label: 'Organisation',
        type: 'text',
        autoComplete: 'organization',
        placeholder: 'e.g. District TB programme',
    },
    {
        name: 'district',
        label: 'District',
        type: 'text',
        autoComplete: 'address-level2',
        placeholder: 'e.g. Larkana',
    },
    {
        name: 'email',
        label: 'Email',
        type: 'email',
        autoComplete: 'email',
    },
] as const;

const successMessage =
    'We have your request. We will review it and be in touch.';

type FieldName = (typeof fields)[number]['name'];

type AccessRequestDefaults = Partial<
    Record<FieldName | 'consent', string | null>
>;

function SuccessPanel({ message }: { message: string }) {
    const headingRef = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        headingRef.current?.focus();
    }, []);

    return (
        <div
            role="status"
            data-test="access-request-success"
            className="mx-auto mt-10 max-w-md rounded-2xl border border-navy/10 bg-white px-8 py-12 text-center shadow-[0_2px_20px_rgba(0,0,0,0.06)] sm:px-12 dark:border-white/15 dark:bg-navy"
        >
            <div
                aria-hidden="true"
                className="mx-auto flex size-14 items-center justify-center rounded-full bg-brand text-white"
            >
                <Check className="size-7" strokeWidth={2.5} />
            </div>
            <h2
                ref={headingRef}
                tabIndex={-1}
                className="mt-6 text-2xl font-semibold tracking-tight text-navy outline-none focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric dark:text-white"
            >
                Request received
            </h2>
            <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                {message}
            </p>
            <Link
                href={home()}
                className="mt-8 inline-flex text-sm font-semibold text-navy underline decoration-electric underline-offset-4 hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric dark:text-white dark:hover:text-white"
            >
                Back to home
            </Link>
        </div>
    );
}

export default function AccessRequest({
    received = false,
    status,
    defaults,
}: {
    received?: boolean;
    status?: string | null;
    defaults?: AccessRequestDefaults | null;
}) {
    const [clientErrors, setClientErrors] = useState<
        Partial<Record<FieldName | 'consent', string>>
    >({});
    const saving = useRef(false);

    return (
        <MarketingShell>
            <Head title="Request access" />
            <main className="mx-auto max-w-xl px-5 py-16 sm:px-8">
                <p className="text-sm font-semibold tracking-[0.18em] text-navy uppercase dark:text-white">
                    Access
                </p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Request access
                </h1>
                <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                    Tell us who you are. We store this for review. It does not
                    create an account. Invited users sign in.
                </p>

                {received ? (
                    <SuccessPanel message={status || successMessage} />
                ) : (
                    <Form
                        {...store.form()}
                        resetOnSuccess
                        noValidate
                        disableWhileProcessing
                        id="access-request-form"
                        className="relative mt-8 flex flex-col gap-5"
                        onBefore={() => {
                            if (saving.current) {
                                return false;
                            }

                            const form = document.getElementById(
                                'access-request-form',
                            );
                            const next =
                                form instanceof HTMLFormElement
                                    ? validateAccess(new FormData(form))
                                    : { name: 'Enter your name.' };

                            setClientErrors(next);
                            const first = Object.keys(next)[0];

                            if (first) {
                                scrollToField(first);
                                return false;
                            }

                            saving.current = true;
                        }}
                        onFinish={() => {
                            saving.current = false;
                        }}
                    >
                        {({ processing, errors }) => (
                            <>
                                <div
                                    className="absolute -left-[10000px] h-px w-px overflow-hidden"
                                    aria-hidden="true"
                                >
                                    <label htmlFor="website">Website</label>
                                    <input
                                        id="website"
                                        name="website"
                                        type="text"
                                        tabIndex={-1}
                                        autoComplete="off"
                                        defaultValue=""
                                    />
                                </div>

                                {fields.map((field) => (
                                    <div
                                        key={field.name}
                                        className="grid gap-2"
                                    >
                                        <Label htmlFor={field.name}>
                                            {field.label}
                                        </Label>
                                        <Input
                                            id={field.name}
                                            name={field.name}
                                            type={field.type}
                                            autoComplete={field.autoComplete}
                                            placeholder={
                                                'placeholder' in field
                                                    ? field.placeholder
                                                    : undefined
                                            }
                                            defaultValue={
                                                defaults?.[field.name] ??
                                                undefined
                                            }
                                            maxLength={255}
                                            aria-required="true"
                                            aria-invalid={Boolean(
                                                clientErrors[field.name] ||
                                                errors[field.name],
                                            )}
                                            aria-describedby={
                                                clientErrors[field.name] ||
                                                errors[field.name]
                                                    ? `${field.name}-error`
                                                    : undefined
                                            }
                                            onChange={() =>
                                                setClientErrors((current) => ({
                                                    ...current,
                                                    [field.name]: undefined,
                                                }))
                                            }
                                        />
                                        <InputError
                                            id={`${field.name}-error`}
                                            message={
                                                clientErrors[field.name] ||
                                                errors[field.name]
                                            }
                                        />
                                        {field.name === 'email' && (
                                            <>
                                                <div className="flex items-start gap-3">
                                                    <input
                                                        id="consent"
                                                        name="consent"
                                                        type="checkbox"
                                                        value="1"
                                                        aria-required="true"
                                                        aria-invalid={Boolean(
                                                            clientErrors.consent ||
                                                            errors.consent,
                                                        )}
                                                        aria-describedby={
                                                            clientErrors.consent ||
                                                            errors.consent
                                                                ? 'consent-error'
                                                                : undefined
                                                        }
                                                        defaultChecked={
                                                            defaults?.consent ===
                                                            '1'
                                                        }
                                                        className="mt-1 size-4 shrink-0 rounded border border-navy/30 accent-navy"
                                                    />
                                                    <p className="text-sm leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                                                        <label
                                                            htmlFor="consent"
                                                            className="cursor-pointer"
                                                        >
                                                            We use this only to
                                                            review your request.
                                                        </label>{' '}
                                                        See{' '}
                                                        <Link
                                                            href={privacy()}
                                                            className="underline decoration-electric underline-offset-4 hover:text-navy focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-electric dark:hover:text-white"
                                                        >
                                                            {'Privacy.'}
                                                        </Link>
                                                    </p>
                                                </div>
                                                <InputError
                                                    id="consent-error"
                                                    message={
                                                        clientErrors.consent ||
                                                        errors.consent
                                                    }
                                                />
                                            </>
                                        )}
                                    </div>
                                ))}

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-brand px-5 text-sm font-semibold text-white hover:bg-[#211e7a] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-electric disabled:opacity-50"
                                >
                                    {processing && <Spinner />}
                                    Request access
                                </button>
                            </>
                        )}
                    </Form>
                )}
            </main>
        </MarketingShell>
    );
}

function validateAccess(
    data: FormData,
): Partial<Record<FieldName | 'consent', string>> {
    const errors: Partial<Record<FieldName | 'consent', string>> = {};
    const value = (name: string) => String(data.get(name) ?? '').trim();

    if (value('name') === '') {
        errors.name = 'The name field is required.';
    }

    if (value('role') === '') {
        errors.role = 'The role field is required.';
    }

    if (value('organisation') === '') {
        errors.organisation = 'The organisation field is required.';
    }

    if (value('district') === '') {
        errors.district = 'The district field is required.';
    }

    const email = value('email');

    if (email === '') {
        errors.email = 'The email field is required.';
    } else if (!email.includes('@') || !email.includes('.')) {
        errors.email = 'The email field must be a valid email address.';
    }

    if (data.get('consent') !== '1') {
        errors.consent = 'The consent field must be accepted.';
    }

    return errors;
}
