import { useState } from 'react';
import { useCommand } from '../context';
import { fmt } from '../format';
import type {
    BmuDrilldown,
    ColumnFormat,
    Envelope,
    MapSector,
    Sector,
    SectorRow,
    SectorTable,
} from '../types';
import { at, LoadState } from '../ui';
import { useApi } from '../use-api';
import { BmuDialog } from './bmu-dialog';

const SECTOR_NAME: Record<Sector, string> = {
    public: 'Public',
    private: 'Private',
    ppm: 'PPM',
};

export function formatCell(
    value: string | number | null,
    format: ColumnFormat,
) {
    if (value === null || value === undefined) {
        return <span title="Not collected, or not enough returns">n/a</span>;
    }

    switch (format.type) {
        case 'int':
            return fmt(Number(value));
        case 'percent':
            return `${Number(value).toFixed(format.decimals)}%`;
        case 'decimal':
            return Number(value).toFixed(format.decimals);
        default:
            return String(value);
    }
}

function compare(a: SectorRow, b: SectorRow, key: string, dir: 1 | -1): number {
    const x = a[key];
    const y = b[key];

    if (x === null || x === undefined) {
        return y === null || y === undefined ? 0 : 1;
    }

    if (y === null || y === undefined) {
        return -1;
    }

    return key === 'district'
        ? String(x).localeCompare(String(y)) * -dir
        : (Number(x) - Number(y)) * dir;
}

export function SectorPage({ sector }: { sector: Sector }) {
    const { data, error, loading, reload } = useApi<Envelope<SectorTable>>(
        `/api/command/sectors/${sector}`,
    );
    const { openMap, sort, setSort } = useCommand();
    const [open, setOpen] = useState<number | null>(null);

    const drill = useApi<Envelope<BmuDrilldown>>(
        open !== null
            ? `/api/command/sectors/${sector}/districts/${open}/bmus`
            : null,
    );

    if (!data) {
        return <LoadState loading={loading} error={error} onRetry={reload} />;
    }

    const { columns, rows, total, row_action: rowAction } = data.data;
    const name = SECTOR_NAME[sector];
    const sorted = rows
        .slice()
        .sort((a, b) => compare(a, b, sort.key, sort.dir));
    const toMap = rowAction === 'map';

    const pick = (key: string) =>
        setSort({
            key,
            dir: sort.key === key ? (sort.dir === 1 ? -1 : 1) : -1,
        });

    const activate = (row: SectorRow) => {
        if (row.city_id === null) {
            return;
        }

        if (toMap) {
            openMap(sector as MapSector, row.city_id);
        } else {
            setOpen(row.city_id);
        }
    };

    return (
        <>
            <div className="cc-txt" style={{ ...at(340, 88), fontSize: 22 }}>
                <h2 style={{ font: 'inherit', display: 'inline' }}>
                    {name} Registered: District → Taluka → BMU
                </h2>{' '}
                <span style={{ opacity: 0.6, fontSize: 15 }}>
                    · click a header to sort,{' '}
                    {toMap
                        ? 'a row to open the map'
                        : 'a row to open its BMU graphs'}
                </span>
            </div>

            <div
                className="cc-dtw"
                style={at(340, 130, 1100, 655)}
                inert={open !== null}
            >
                <table className="cc-dt">
                    <caption className="cc-sr">
                        {name} Registered, by district
                    </caption>
                    <thead>
                        <tr>
                            {columns.map((c) => (
                                <th
                                    key={c.key}
                                    scope="col"
                                    aria-sort={
                                        sort.key === c.key
                                            ? sort.dir < 0
                                                ? 'descending'
                                                : 'ascending'
                                            : undefined
                                    }
                                >
                                    <button
                                        type="button"
                                        onClick={() => pick(c.key)}
                                    >
                                        {c.label}
                                        {sort.key === c.key
                                            ? sort.dir < 0
                                                ? ' ▼'
                                                : ' ▲'
                                            : ''}
                                    </button>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {sorted.map((row) => (
                            <tr
                                key={row.city_id ?? row.district}
                                onClick={() => activate(row)}
                            >
                                {columns.map((c, i) => (
                                    <td key={c.key}>
                                        {i === 0 ? (
                                            <button
                                                type="button"
                                                className="cc-rowbtn"
                                            >
                                                {row[c.key]}
                                            </button>
                                        ) : (
                                            formatCell(row[c.key], c.format)
                                        )}
                                    </td>
                                ))}
                            </tr>
                        ))}
                        {sorted.length > 1 ? (
                            <tr className="tot">
                                {columns.map((c) => (
                                    <td key={c.key}>
                                        {formatCell(total[c.key], c.format)}
                                    </td>
                                ))}
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>

            {open !== null ? (
                <BmuDialog
                    sectorName={name}
                    state={drill}
                    onClose={() => setOpen(null)}
                    onOpenMap={(cityId) => {
                        setOpen(null);
                        openMap(sector as MapSector, cityId);
                    }}
                />
            ) : null}
        </>
    );
}
