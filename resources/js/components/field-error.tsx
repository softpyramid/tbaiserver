import { CircleAlert } from 'lucide-react';

export default function FieldError({
    id,
    message,
}: {
    id?: string;
    message?: string;
}) {
    if (!message) {
        return null;
    }

    return (
        <p
            id={id}
            role="alert"
            className="flex items-start gap-2 text-base text-foreground"
        >
            <CircleAlert
                aria-hidden="true"
                className="mt-0.5 size-5 shrink-0 text-[#9f1239] dark:text-[#fecdd3]"
            />
            <span>{message}</span>
        </p>
    );
}
