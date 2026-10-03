import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { MarketingShell } from '@/components/marketing-shell';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/access-requests';

const fields = [
    { name: 'name', label: 'Name', type: 'text', autoComplete: 'name' },
    { name: 'role', label: 'Role', type: 'text', autoComplete: 'organization-title' },
    {
        name: 'organisation',
        label: 'Organisation',
        type: 'text',
        autoComplete: 'organization',
    },
    {
        name: 'district',
        label: 'District',
        type: 'text',
        autoComplete: 'address-level2',
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
                <p className="text-xs font-medium tracking-[0.22em] text-[#E53935] uppercase">
                    Access
                </p>
                <h1 className="mt-3 text-3xl font-semibold tracking-tight sm:text-4xl">
                    Request access
                </h1>
                <p className="mt-4 text-base leading-relaxed text-[#1A237E]/75 dark:text-[#E8EAF6]">
                    Tell us who you are. We store this for review. It does not
                    create an account. Invited users sign in.
                </p>

                {status && (
                    <p
                        className="mt-6 rounded-md border border-[#1A237E]/15 bg-white px-4 py-3 text-sm leading-relaxed dark:border-white/15 dark:bg-[#121858]"
                        role="status"
                    >
                        {status}
                    </p>
                )}

                <Form
                    {...store.form()}
                    resetOnSuccess
                    className="mt-8 flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
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
                                        required
                                    />
                                    <InputError message={errors[field.name]} />
                                </div>
                            ))}

                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-[#E53935] px-5 text-sm font-medium text-white hover:bg-[#D32F2F] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#1E88E5] disabled:opacity-50"
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
