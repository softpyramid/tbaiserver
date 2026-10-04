import {
    createContext,
    useContext,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
    type FocusEvent,
    type MouseEvent,
    type ReactNode,
    type RefObject,
} from 'react';

type TipState = { content: ReactNode; x: number; y: number } | null;

type TipApi = {
    show: (content: ReactNode, clientX: number, clientY: number) => void;
    hide: () => void;
    stage: RefObject<HTMLDivElement | null>;
};

const TipContext = createContext<TipApi | null>(null);

/** Hover and focus tooltip, positioned in stage coordinates like the prototype's `#gtip`. */
export function TipProvider({
    stageRef,
    children,
}: {
    stageRef: RefObject<HTMLDivElement | null>;
    children: ReactNode;
}) {
    const [tip, setTip] = useState<TipState>(null);
    const box = useRef<HTMLDivElement>(null);

    const api = useMemo<TipApi>(
        () => ({
            stage: stageRef,
            show: (content, clientX, clientY) => {
                const stage = stageRef.current;

                if (!stage) {
                    return;
                }

                const r = stage.getBoundingClientRect();
                const scale =
                    stage.offsetWidth > 0 ? r.width / stage.offsetWidth : 1;

                setTip({
                    content,
                    x: (clientX - r.left) / scale + 14,
                    y: (clientY - r.top) / scale + 12,
                });
            },
            hide: () => setTip(null),
        }),
        [stageRef],
    );

    useLayoutEffect(() => {
        const el = box.current;
        const stage = stageRef.current;

        if (!el || !stage || !tip) {
            return;
        }

        let { x, y } = tip;

        if (x + el.offsetWidth > stage.offsetWidth - 6) {
            x -= el.offsetWidth + 28;
        }

        if (y + el.offsetHeight > stage.offsetHeight - 4) {
            y -= el.offsetHeight + 24;
        }

        el.style.left = `${Math.max(0, x)}px`;
        el.style.top = `${Math.max(0, y)}px`;
    }, [tip, stageRef]);

    return (
        <TipContext.Provider value={api}>
            {children}
            <div
                ref={box}
                id="cc-gtip"
                className="cc-gtip"
                role="tooltip"
                style={{ display: tip ? 'block' : 'none' }}
            >
                {tip?.content}
            </div>
        </TipContext.Provider>
    );
}

/** Handlers that show `content` on hover and keyboard focus. */
export function useTip() {
    const api = useContext(TipContext);

    return (content: ReactNode) => ({
        onMouseEnter: (e: MouseEvent) =>
            api?.show(content, e.clientX, e.clientY),
        onMouseMove: (e: MouseEvent) =>
            api?.show(content, e.clientX, e.clientY),
        onMouseLeave: () => api?.hide(),
        onFocus: (e: FocusEvent<Element>) => {
            const r = e.currentTarget.getBoundingClientRect();
            api?.show(content, r.left + r.width / 2, r.top + r.height / 2);
        },
        onBlur: () => api?.hide(),
    });
}
