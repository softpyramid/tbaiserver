import { usePage } from '@inertiajs/react';

type Replacement = Record<string, string | number>;

export function translate(
    messages: Record<string, string>,
    key: string,
    replace?: Replacement,
): string {
    const template = messages[key] ?? key;

    if (!replace) {
        return template;
    }

    return Object.entries(replace).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
        template,
    );
}

export function useEntryI18n() {
    const { i18n } = usePage().props;

    return {
        locale: i18n.locale,
        dir: i18n.dir,
        t: (key: string, replace?: Replacement) =>
            translate(i18n.entry, key, replace),
    };
}
