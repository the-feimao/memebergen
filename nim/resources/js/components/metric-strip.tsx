type Metric = {
    label: string;
    value: string | number;
    hint?: string;
    tone?: 'neutral' | 'verified' | 'review' | 'blocked';
};

const toneStyles: Record<NonNullable<Metric['tone']>, string> = {
    neutral: 'border-t-ink/60',
    verified: 'border-t-verdigris',
    review: 'border-t-brass',
    blocked: 'border-t-destructive',
};

export default function MetricStrip({ metrics }: { metrics: Metric[] }) {
    return (
        <div className="grid gap-px overflow-hidden rounded-xl border border-line bg-line sm:grid-cols-2 xl:grid-cols-4">
            {metrics.map((metric) => (
                <div
                    key={metric.label}
                    className={`border-t-2 bg-card px-5 py-4 ${toneStyles[metric.tone ?? 'neutral']}`}
                >
                    <p className="text-xs font-medium text-muted-foreground">
                        {metric.label}
                    </p>
                    <p className="mt-2 font-display text-3xl leading-none tracking-tight text-ink tabular-nums">
                        {metric.value}
                    </p>
                    {metric.hint && (
                        <p className="mt-2 text-xs text-muted-foreground">
                            {metric.hint}
                        </p>
                    )}
                </div>
            ))}
        </div>
    );
}
