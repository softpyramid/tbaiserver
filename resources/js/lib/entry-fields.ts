export function focusNextEntryField(current: HTMLElement): void {
    const form = current.closest('form');

    if (!form) {
        return;
    }

    const outside = form.id
        ? [
              ...document.querySelectorAll<HTMLElement>(
                  `[form="${form.id}"][data-entry-field]`,
              ),
          ]
        : [];
    const fields = [
        ...form.querySelectorAll<HTMLElement>('[data-entry-field]'),
        ...outside,
    ].filter((field) => !field.hasAttribute('disabled'));
    const index = fields.indexOf(current);

    fields[index + 1]?.focus();
}

export function scrollToField(id: string): void {
    const node = document.getElementById(id);
    const error = document.getElementById(`${id}-error`);
    const target = error ?? node;

    target?.scrollIntoView({ behavior: 'smooth', block: 'center' });

    if (node instanceof HTMLElement) {
        node.focus({ preventScroll: true });
    }
}
