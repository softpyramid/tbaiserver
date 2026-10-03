import { cn } from '@/lib/utils';
import type { SummaryCard } from '@/types/team-area';

type Props = {
    cards: SummaryCard[];
    columns?: 3 | 4;
};

const cardTone: Record<string, string> = {
    members: 'bg-brand/5',
    in_charge: 'bg-success/10',
    active_week: 'bg-electric/10',
    pending: 'bg-destructive/10',
    teams: 'bg-brand/5',
};

export default function TeamSummaryCards({ cards, columns = 4 }: Props) {
    return (
        <ul
            className={cn(
                'grid grid-cols-2 gap-3',
                columns === 3 ? 'md:grid-cols-3' : 'md:grid-cols-4',
            )}
        >
            {cards.map((card) => (
                <li
                    key={card.key}
                    data-test="summary-card"
                    data-key={card.key}
                    className={cn(
                        'rounded-xl border p-4 text-card-foreground',
                        cardTone[card.key] ?? 'bg-card',
                    )}
                >
                    <p className="text-sm font-medium">{card.label}</p>
                    <p className="mt-2 text-2xl font-semibold">{card.figure}</p>
                    <p className="mt-1 text-sm text-muted-foreground">{card.caption}</p>
                </li>
            ))}
        </ul>
    );
}
