import { Fragment, useEffect, useMemo, useRef, useState } from 'react';
import { CommandContext, type MapIntent, type SortState } from './context';
import { PlaceholderPage } from './pages/placeholder';
import { DriversPage } from './pages/drivers';
import { IncidencePage } from './pages/incidence';
import { IntelligencePage } from './pages/intelligence';
import { MapPage } from './pages/map';
import { SectorPage } from './pages/sector';
import { CumulativePage } from './pages/cumulative';
import { TipProvider } from './tooltip';
import type { Envelope, Frame, MapSector, Screen, Sector } from './types';
import { at, LoadState } from './ui';
import { useApi } from './use-api';

/** Pill geometry on the stage: [left, top, width, height], from the prototype. */
const NAV_BOX: Record<number, [number, number, number, number]> = {
    1: [38, 69, 267, 64],
    2: [36, 153, 266, 64],
    3: [36, 237, 266, 64],
    4: [33, 321, 265, 64],
    5: [33, 405, 265, 64],
    6: [36, 489, 266, 64],
    7: [36, 573, 266, 64],
    8: [38, 657, 266, 64],
    9: [36, 741, 266, 64],
};

const STAGE_W = 1456;
const STAGE_H = 819;

function numberFromHash(): number {
    return Number.parseInt(window.location.hash.slice(1), 10) || 1;
}

function useStageFit() {
    const [fit, setFit] = useState<{
        scale: number;
        left: number;
        top: number;
    } | null>(null);

    useEffect(() => {
        const phone = window.matchMedia('(max-width: 767px)');
        const apply = () => {
            if (phone.matches) {
                setFit(null);

                return;
            }

            setFit({
                scale: Math.min(
                    window.innerWidth / STAGE_W,
                    window.innerHeight / STAGE_H,
                ),
                left: window.innerWidth / 2,
                top: window.innerHeight / 2,
            });
        };

        apply();
        window.addEventListener('resize', apply);

        return () => window.removeEventListener('resize', apply);
    }, []);

    return fit;
}

export function CommandShell({ backUrl }: { backUrl: string }) {
    const stage = useRef<HTMLDivElement>(null);
    const fit = useStageFit();
    const frame = useApi<Envelope<Frame>>('/api/command/frame');
    const [wanted, setWanted] = useState<number>(() => numberFromHash());
    const [sort, setSort] = useState<SortState>({ key: 'suspects', dir: -1 });
    const [mapIntent, setMapIntent] = useState<MapIntent>({
        sector: 'all',
        selected: null,
        indicator: null,
        heat: true,
    });

    const screens = useMemo<Screen[]>(
        () => frame.data?.data.screens ?? [],
        [frame.data],
    );
    const listed = screens.find((s) => s.number === wanted);
    // A real page number that this user's flags switch off is not the same as
    // a stray hash: only the stray one falls back to the first page.
    const switchedOff =
        !listed && wanted >= 1 && wanted <= 9 && screens.length > 0;
    const current = listed ?? (switchedOff ? undefined : screens[0]);

    useEffect(() => {
        if (!window.location.hash) {
            window.history.replaceState(null, '', '#1');
        }

        const onHash = () => setWanted(numberFromHash());

        window.addEventListener('hashchange', onHash);

        return () => window.removeEventListener('hashchange', onHash);
    }, []);

    const go = (screen: Screen) => {
        setWanted(screen.number);
        window.history.replaceState(null, '', `#${screen.number}`);
    };

    const patchMapIntent = (next: Partial<MapIntent>) =>
        setMapIntent((current) => ({ ...current, ...next }));

    const openMap = (
        sector: MapSector,
        cityId: number | null,
        indicator?: string,
    ) => {
        setMapIntent((current) => ({
            ...current,
            sector,
            selected: cityId,
            indicator: indicator ?? current.indicator,
        }));

        const map = screens.find((s) => s.key === 'map');

        if (map) {
            go(map);
        }
    };

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const target = e.target as HTMLElement | null;

            if (
                e.altKey ||
                e.ctrlKey ||
                e.metaKey ||
                (target && /^(INPUT|SELECT|TEXTAREA)$/.test(target.tagName))
            ) {
                return;
            }

            const at_ = screens.findIndex((s) => s.number === current?.number);
            const next =
                e.key === 'ArrowRight' || e.key === 'PageDown'
                    ? screens[Math.min(screens.length - 1, at_ + 1)]
                    : e.key === 'ArrowLeft' || e.key === 'PageUp'
                      ? screens[Math.max(0, at_ - 1)]
                      : null;

            if (next && next !== current) {
                go(next);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    const data = frame.data?.data;
    const style = fit
        ? {
              left: fit.left,
              top: fit.top,
              transform: `scale(${fit.scale}) translate(-50%,-50%)`,
          }
        : undefined;

    return (
        <div className="cc-root">
            <div
                ref={stage}
                className="cc-stage"
                data-bg={current?.background ?? (switchedOff ? 'ajrak' : 'lab')}
                style={style}
            >
                <div className="cc-bgimg" aria-hidden="true" />
                <CommandContext.Provider
                    value={{
                        openMap,
                        mapIntent,
                        setMapIntent: patchMapIntent,
                        sort,
                        setSort,
                    }}
                >
                    <TipProvider stageRef={stage}>
                        <header className="cc-head">
                            <img
                                className="cc-lg cc-lg1"
                                alt="Government of Sindh"
                                src="/cc-assets/logo-sindh-govt.png"
                                width={142}
                                height={152}
                            />
                            <h1 className="cc-title">
                                <span>
                                    {data?.title ??
                                        'TB-AI Sindh Govt. Super Intelligence Command Center'}
                                </span>
                            </h1>
                            <img
                                className="cc-lg cc-lg2"
                                alt="TB-AI"
                                src="/cc-assets/logo-tbai.png"
                                width={209}
                                height={222}
                            />
                        </header>

                        <nav
                            className="cc-navs"
                            aria-label="Command center pages"
                        >
                            {screens.map((s) => {
                                const box = NAV_BOX[s.number] ?? NAV_BOX[1];
                                const on = s.number === current?.number;
                                const small = s.label_lines.length > 1;

                                return (
                                    <button
                                        key={s.number}
                                        type="button"
                                        className={`cc-nav${s.accent === 'alt' ? ' g2' : ''}${on ? ' on' : ''}${s.number === 6 || s.number === 7 ? ' cc-nav-sm' : ''}`}
                                        style={{
                                            ...at(
                                                box[0],
                                                box[1],
                                                box[2],
                                                box[3],
                                            ),
                                        }}
                                        aria-current={on ? 'page' : undefined}
                                        onClick={() => go(s)}
                                    >
                                        {small ? (
                                            <small>
                                                {s.label_lines.map(
                                                    (line, i) => (
                                                        <Fragment key={line}>
                                                            {i > 0 ? (
                                                                <>
                                                                    {' '}
                                                                    <br />
                                                                </>
                                                            ) : null}
                                                            {line}
                                                        </Fragment>
                                                    ),
                                                )}
                                            </small>
                                        ) : (
                                            s.label
                                        )}
                                    </button>
                                );
                            })}
                        </nav>

                        <main
                            className="cc-main"
                            id="cc-main"
                            aria-label={current?.label}
                        >
                            {frame.loading || frame.error ? (
                                <LoadState
                                    loading={frame.loading}
                                    error={frame.error}
                                    onRetry={frame.reload}
                                />
                            ) : current?.key === 'cumulative' ? (
                                <CumulativePage key={current.key} />
                            ) : current?.key === 'public' ||
                              current?.key === 'private' ||
                              current?.key === 'ppm' ? (
                                <SectorPage
                                    key={current.key}
                                    sector={current.key as Sector}
                                />
                            ) : current?.key === 'intelligence' ? (
                                <IntelligencePage key={current.key} />
                            ) : current?.key === 'drivers' ? (
                                <DriversPage key={current.key} />
                            ) : current?.key === 'incidence' ? (
                                <IncidencePage key={current.key} />
                            ) : current?.key === 'map' ? (
                                <MapPage key={current.key} />
                            ) : switchedOff ? (
                                <PlaceholderPage
                                    key={`off-${wanted}`}
                                    title="Page not available"
                                    sub="This page is not switched on."
                                    text="An administrator can turn it on."
                                />
                            ) : current ? (
                                <PlaceholderPage
                                    key={current.key}
                                    title={current.label}
                                />
                            ) : null}
                        </main>

                        <a className="cc-back" href={backUrl}>
                            Back to the app
                        </a>

                        <div className="cc-pager">
                            {screens.map((s) => (
                                <button
                                    key={s.number}
                                    type="button"
                                    className={
                                        s.number === current?.number
                                            ? 'on'
                                            : undefined
                                    }
                                    aria-label={s.label}
                                    onClick={() => go(s)}
                                />
                            ))}
                        </div>
                    </TipProvider>
                </CommandContext.Provider>
            </div>
        </div>
    );
}
