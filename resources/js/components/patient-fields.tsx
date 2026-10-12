import type { ReactNode } from 'react';
import FieldError from '@/components/field-error';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type Option = { value: string; label: string };

const controlClass = 'h-12 w-full text-base';

type BaseProps = {
    id: string;
    label: string;
    hint?: string;
    error?: string;
};

function describedBy(id: string, hint?: string, error?: string) {
    return (
        [hint ? `${id}-hint` : null, error ? `${id}-error` : null]
            .filter(Boolean)
            .join(' ') || undefined
    );
}

function FieldShell({
    id,
    label,
    hint,
    error,
    children,
}: BaseProps & { children: ReactNode }) {
    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="text-base font-medium">
                {label}
            </label>
            {children}
            {hint ? (
                <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                    {hint}
                </p>
            ) : null}
            <FieldError id={`${id}-error`} message={error} />
        </div>
    );
}

export function TextField({
    id,
    label,
    hint,
    error,
    value,
    onChange,
    type = 'text',
    inputMode,
    autoComplete = 'off',
    required = false,
    min,
    max,
}: BaseProps & {
    value: string;
    onChange: (value: string) => void;
    type?: 'text' | 'number' | 'date' | 'tel';
    inputMode?: 'numeric' | 'tel' | 'text';
    autoComplete?: string;
    required?: boolean;
    min?: string | number;
    max?: string | number;
}) {
    return (
        <FieldShell id={id} label={label} hint={hint} error={error}>
            <Input
                id={id}
                name={id}
                type={type}
                inputMode={inputMode}
                autoComplete={autoComplete}
                required={required}
                min={min}
                max={max}
                value={value}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, hint, error)}
                className={controlClass}
                onChange={(event) => onChange(event.target.value)}
            />
        </FieldShell>
    );
}

export function SelectField({
    id,
    label,
    hint,
    error,
    value,
    onChange,
    options,
    placeholder,
    required = false,
}: BaseProps & {
    value: string;
    onChange: (value: string) => void;
    options: Option[];
    placeholder?: string;
    required?: boolean;
}) {
    return (
        <FieldShell id={id} label={label} hint={hint} error={error}>
            <select
                id={id}
                name={id}
                required={required}
                value={value}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, hint, error)}
                className={cn(
                    controlClass,
                    'rounded-md border border-input bg-transparent px-3',
                )}
                onChange={(event) => onChange(event.target.value)}
            >
                {placeholder !== undefined ? (
                    <option value="">{placeholder}</option>
                ) : null}
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </FieldShell>
    );
}

export function TextAreaField({
    id,
    label,
    error,
    value,
    onChange,
}: BaseProps & { value: string; onChange: (value: string) => void }) {
    return (
        <FieldShell id={id} label={label} error={error}>
            <textarea
                id={id}
                name={id}
                rows={3}
                maxLength={1000}
                value={value}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy(id, undefined, error)}
                className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-base"
                onChange={(event) => onChange(event.target.value)}
            />
        </FieldShell>
    );
}
