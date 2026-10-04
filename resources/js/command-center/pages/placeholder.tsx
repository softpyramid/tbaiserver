import { at, Panel } from '../ui';

/** Stands in for a page whose React port is not built yet, or that is switched off. */
export function PlaceholderPage({
    title,
    sub = 'This page is being built.',
    text = 'The figures for this page will appear here soon.',
}: {
    title: string;
    sub?: string;
    text?: string;
}) {
    return (
        <Panel id="cc-soon" title={title} sub={sub} style={at(350, 108, 520)}>
            <p className="cc-state" style={{ marginTop: 6 }}>
                {text}
            </p>
        </Panel>
    );
}
