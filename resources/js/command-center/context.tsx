import { createContext, useContext } from 'react';
import type { MapSector } from './types';

export type SortState = { key: string; dir: 1 | -1 };

export type MapIntent = {
    sector: MapSector;
    selected: number | null;
    indicator: string | null;
};

export type CommandContextValue = {
    /** Jump to the map page with a sector, district and indicator preselected. */
    openMap: (
        sector: MapSector,
        cityId: number | null,
        indicator?: string,
    ) => void;
    mapIntent: MapIntent;
    sort: SortState;
    setSort: (next: SortState) => void;
};

export const CommandContext = createContext<CommandContextValue | null>(null);

export function useCommand(): CommandContextValue {
    const value = useContext(CommandContext);

    if (!value) {
        throw new Error('useCommand needs the command center shell.');
    }

    return value;
}
