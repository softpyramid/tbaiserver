import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/screening';

type Question = {
    key: string;
    label: string;
};

type Confirmation = {
    score: number;
    isSuspect: boolean;
    message: string;
};

type Props = {
    facilityName: string;
    teamSlug: string;
    notice: string | null;
    questions: Question[];
    confirmation: Confirmation | null;
};

export default function ScreeningNew({
    facilityName,
    teamSlug,
    notice,
    questions,
    confirmation,
}: Props) {
    return (
        <>
            <Head title="Screening" />

            <div className="mx-auto flex w-full max-w-md flex-col gap-8 px-4 py-6">
                <Heading title="Screening" description={facilityName} />

                {confirmation ? (
                    <p data-test="screening-confirmation">
                        {confirmation.message}
                    </p>
                ) : null}

                {notice ? (
                    <p>{notice}</p>
                ) : (
                    <Form {...store.form(teamSlug)} className="space-y-6">
                        {({ errors, processing }) => (
                            <>
                                {questions.map((question) => (
                                    <fieldset
                                        key={question.key}
                                        className="grid gap-2"
                                    >
                                        <legend className="text-sm font-medium">
                                            {question.label}
                                        </legend>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name={`answers[${question.key}]`}
                                                value="yes"
                                                required
                                            />
                                            Yes
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="radio"
                                                name={`answers[${question.key}]`}
                                                value="no"
                                                required
                                            />
                                            No
                                        </label>
                                        <InputError
                                            message={
                                                errors[
                                                    `answers.${question.key}`
                                                ]
                                            }
                                        />
                                    </fieldset>
                                ))}

                                <InputError message={errors.answers} />

                                <Button type="submit" disabled={processing}>
                                    Save screening
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}
