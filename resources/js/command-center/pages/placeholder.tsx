import { at, Panel } from '../ui';

/** Stands in for a page whose React port is not built yet. */
export function PlaceholderPage({ title }: { title: string }) {
    return (
        <Panel
            id="cc-soon"
            title={title}
            sub="This page is being built."
            style={at(350, 108, 520)}
        >
            <p className="cc-state" style={{ marginTop: 6 }}>
                The figures for this page will appear here soon.
            </p>
        </Panel>
    );
}
