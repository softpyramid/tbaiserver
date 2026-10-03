import { Form, Head, Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { MarketingShell } from '@/components/marketing-shell';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { privacy } from '@/routes';
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

export default function AccessRequest({ status }: { status?: string }) {
    return (
        <MarketingShell>
            <Head title="Request access" />
            <main className="mx-auto max-w-xl px-5 py-16 sm:px-8">
                <p className="text-xs font-medium tracking-[0.22em] text-brand uppercase">
                    Access
                </p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Request access
                </h1>
                <p className="mt-4 text-base leading-relaxed text-[#374151] dark:text-[#E2E8F0]">
                    Tell us who you are. We store this for review. It does not
                    create an account. Invited users sign in.
                </p>

                {status && (
                    <p
                        className="mt-6 rounded-md border border-navy/15 bg-white px-4 py-3 text-sm leading-relaxed dark:border-white/15 dark:bg-navy"
                        role="status"
                        data-test="access-request-status"
                    >
                        {status}
                    </p>
                )}

                <Form
                    {...store.form()}
                    resetOnSuccess
                    className="relative mt-8 flex flex-col gap-5"
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
                                <div key={field.name} className="grid gap-2">
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
                                        maxLength={255}
                                        required
                                    />
                                    <InputError message={errors[field.name]} />
                                    {field.name === 'email' && (
                                        <>
                                            <div className="flex items-start gap-3">
                                                <input
                                                    id="consent"
                                                    name="consent"
                                                    type="checkbox"
                                                    value="1"
                                                    required
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
                                                message={errors.consent}
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
            </main>
        </MarketingShell>
    );
}
