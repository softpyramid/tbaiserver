import { Minus, Plus } from 'lucide-react';
import { useEffect, useRef, type PointerEvent, type ReactNode } from 'react';
import FieldError from '@/components/field-error';
import { Label } from '@/components/ui/label';
import { focusNextEntryField } from '@/lib/entry-fields';
import { useEntryI18n } from '@/lib/i18n';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    name: string;
    label: string;
    value: string;
    onValueChange: (value: string) => void;
    decimal?: boolean;
    required?: boolean;
    disabled?: boolean;
    error?: string;
    hint?: string;
    enterKeyHint: 'next' | 'done';
};

function sanitize(value: string, decimal: boolean): string {
    if (!decimal) {
        return value.replace(/\D/g, '');
    }

    const cleaned = value.replace(/[^0-9.]/g, '');
    const [whole, ...rest] = cleaned.split('.');

    if (rest.length === 0) {
        return whole ?? '';
    }

    return `${whole ?? ''}.${rest.join('')}`;
}

function stepValue(value: string, delta: number, decimal: boolean): string {
    const current = value === '' || value === '.' ? 0 : Number(value);

    if (!Number.isFinite(current)) {
        return value;
    }

    const next = Math.max(0, current + delta);

    if (!decimal) {
        return String(Math.round(next));
    }

    return String(Math.round(next * 100) / 100);
}

export default function CountStepper({
    id,
    name,
    label,
    value,
    onValueChange,
    decimal = false,
    required = false,
    disabled = false,
    error,
    hint,
    enterKeyHint,
}: Props) {
    const { t } = useEntryI18n();
    const valueRef = useRef(value);
    const delayRef = useRef<number | null>(null);
    const repeatRef = useRef<number | null>(null);
    const handledRef = useRef(false);
    const errorId = `${id}-error`;
    const hintId = `${id}-hint`;

    valueRef.current = value;

    const stop = () => {
        if (delayRef.current !== null) {
            window.clearTimeout(delayRef.current);
            delayRef.current = null;
        }

        if (repeatRef.current !== null) {
            window.clearInterval(repeatRef.current);
            repeatRef.current = null;
        }
    };

    useEffect(() => stop, []);

    const apply = (delta: number) => {
        const next = stepValue(valueRef.current, delta, decimal);
        valueRef.current = next;
        onValueChange(next);
    };

    const startRepeat =
        (delta: number) => (event: PointerEvent<HTMLButtonElement>) => {
            if (event.button !== 0 || disabled) {
                return;
            }

            event.preventDefault();
            handledRef.current = true;
            apply(delta);
            stop();
            delayRef.current = window.setTimeout(() => {
                repeatRef.current = window.setInterval(() => apply(delta), 80);
            }, 400);
        };

    const describedBy = [hint ? hintId : null, error ? errorId : null]
        .filter(Boolean)
        .join(' ');

    return (
        <div className="grid gap-2">
            <Label htmlFor={id} className="text-base">
                {label}
            </Label>
            <div className="flex min-w-0 items-center gap-2">
                <StepperButton
                    label={t('decrease', { label })}
                    disabled={disabled}
                    onPointerDown={startRepeat(decimal ? -0.1 : -1)}
                    onPointerUp={stop}
                    onPointerCancel={stop}
                    onClick={() => {
                        if (handledRef.current) {
                            handledRef.current = false;
                            return;
                        }

                        apply(decimal ? -0.1 : -1);
                    }}
                >
                    <Minus aria-hidden="true" className="size-6" />
                </StepperButton>
                <input
                    id={id}
                    name={name}
                    type="text"
                    inputMode={decimal ? 'decimal' : 'numeric'}
                    pattern={decimal ? undefined : '[0-9]*'}
                    enterKeyHint={enterKeyHint}
                    data-entry-field=""
                    autoComplete="off"
                    required={required}
                    disabled={disabled}
                    value={value}
                    aria-invalid={error ? true : undefined}
                    aria-describedby={describedBy || undefined}
                    onFocus={(event) => event.currentTarget.select()}
                    onChange={(event) =>
                        onValueChange(sanitize(event.target.value, decimal))
                    }
                    onKeyDown={(event) => {
                        if (event.key !== 'Enter') {
                            return;
                        }

                        event.preventDefault();
                        focusNextEntryField(event.currentTarget);
                    }}
                    className="h-12 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 text-center text-base text-foreground shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-50"
                />
                <StepperButton
                    label={t('increase', { label })}
                    disabled={disabled}
                    onPointerDown={startRepeat(decimal ? 0.1 : 1)}
                    onPointerUp={stop}
                    onPointerCancel={stop}
                    onClick={() => {
                        if (handledRef.current) {
                            handledRef.current = false;
                            return;
                        }

                        apply(decimal ? 0.1 : 1);
                    }}
                >
                    <Plus aria-hidden="true" className="size-6" />
                </StepperButton>
            </div>
            {hint ? (
                <p
                    id={hintId}
                    className="text-base text-slate-700 dark:text-slate-200"
                >
                    {hint}
                </p>
            ) : null}
            <FieldError id={errorId} message={error} />
        </div>
    );
}

function StepperButton({
    label,
    disabled,
    onPointerDown,
    onPointerUp,
    onPointerCancel,
    onClick,
    children,
}: {
    label: string;
    disabled: boolean;
    onPointerDown: (event: PointerEvent<HTMLButtonElement>) => void;
    onPointerUp: () => void;
    onPointerCancel: () => void;
    onClick: () => void;
    children: ReactNode;
}) {
    return (
        <button
            type="button"
            aria-label={label}
            disabled={disabled}
            onPointerDown={onPointerDown}
            onPointerUp={onPointerUp}
            onPointerCancel={onPointerCancel}
            onPointerLeave={onPointerUp}
            onContextMenu={(event) => event.preventDefault()}
            onClick={onClick}
            className={cn(
                'inline-flex size-12 shrink-0 items-center justify-center rounded-md border border-input bg-background text-foreground select-none',
                'focus-visible:ring-2 focus-visible:ring-[#14294c] focus-visible:ring-offset-2 focus-visible:outline-none',
                'disabled:opacity-50 dark:focus-visible:ring-[#93c5fd]',
            )}
            style={{ touchAction: 'manipulation' }}
        >
            {children}
        </button>
    );
}
