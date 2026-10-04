import { useEffect, useState } from 'react';
import type { AiPatient, AiTests, Envelope } from '../types';
import { at, Chip, LoadState } from '../ui';
import { useApi } from '../use-api';

const VERDICT: Record<AiPatient['level'], string> = {
    high: 'High probability, refer for confirmatory testing',
    moderate: 'Moderate, clinical review advised',
    low: 'Low probability, routine follow-up',
};

const BAR: Record<AiPatient['level'], string> = {
    high: '#ff3131',
    moderate: '#ffa53a',
    low: '#4be08a',
};

/** Score counting up from 0 in steps of 3 every 16 ms, as the prototype does. */
function useCountUp(target: number, run: number) {
    const [score, setScore] = useState(0);

    useEffect(() => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setScore(target);

            return;
        }

        let n = 0;
        setScore(0);

        const timer = window.setInterval(() => {
            n = Math.min(target, n + 3);
            setScore(n);

            if (n >= target) {
                window.clearInterval(timer);
            }
        }, 16);

        return () => window.clearInterval(timer);
    }, [target, run]);

    return score;
}

function Card({
    left,
    top,
    title,
    children,
}: {
    left: number;
    top: number;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="cc-ac" style={at(left, top)} aria-label={title}>
            <h3>{title}</h3>
            {children}
        </section>
    );
}

/** Page 8: four example patients and the TB-AI probability score for one. */
export function AiTestsPage() {
    const state = useApi<Envelope<AiTests>>('/api/command/ai-tests');
    const [picked, setPicked] = useState<string | null>(null);
    const [run, setRun] = useState(0);
    const body = state.data?.data;
    const patient =
        body?.patients.find((p) => p.id === (picked ?? body.default_patient)) ??
        body?.patients[0];
    const score = useCountUp(patient?.probability ?? 0, run);

    if (!body) {
        return (
            <LoadState
                loading={state.loading}
                error={state.error}
                onRetry={state.reload}
            />
        );
    }

    if (!patient) {
        return (
            <section className="cc-ac" style={at(350, 150)}>
                <h3>AI Assisted Testing</h3>
                <p className="cc-state">No records in your area yet.</p>
            </section>
        );
    }

    return (
        <>
            <div
                className="cc-abs cc-ai-row"
                style={{
                    ...at(350, 96),
                    display: 'flex',
                    gap: 10,
                    alignItems: 'center',
                    flexWrap: 'wrap',
                    color: '#fff',
                    fontSize: 15,
                }}
                role="group"
                aria-label="Patients"
            >
                {body.patients.map((p) => (
                    <Chip
                        key={p.id}
                        on={p.id === patient.id}
                        onClick={() => {
                            setPicked(p.id);
                            setRun((n) => n + 1);
                        }}
                    >
                        {p.id}
                    </Chip>
                ))}
                <Chip
                    className="run"
                    onClick={() => setRun((n) => n + 1)}
                    pressed={false}
                >
                    ▶ Run TB-AI
                </Chip>
            </div>

            <Card left={350} top={150} title="Patient information">
                <b>{patient.id}</b> · {patient.summary}
                <br />
                {patient.location}
                <br />
                <br />
                Presenting symptoms:
                <br />
                {patient.symptoms}
            </Card>

            <Card left={910} top={150} title="TB-AI output">
                <div className="cc-big2" aria-hidden="true">
                    {score}%
                </div>
                <span className="cc-sr">
                    TB probability score {patient.probability}%
                </span>
                <span aria-hidden="true">TB probability score</span>
                <div className="cc-bt" aria-hidden="true">
                    <i
                        style={{
                            width: `${score}%`,
                            background: BAR[patient.level],
                        }}
                    />
                </div>
                <span>{VERDICT[patient.level]}</span>
            </Card>

            <Card left={350} top={470} title="Laboratory result">
                {patient.laboratory}
            </Card>

            <Card left={910} top={470} title="Previous history">
                {patient.history}
            </Card>
        </>
    );
}
