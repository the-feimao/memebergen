type StatusPillProps = {
    status: string;
};

const styles: Record<string, string> = {
    completed: 'border-verdigris/40 bg-verdigris/10 text-verdigris',
    processed: 'border-verdigris/40 bg-verdigris/10 text-verdigris',
    valid: 'border-verdigris/40 bg-verdigris/10 text-verdigris',
    ready_for_review: 'border-brass/50 bg-brass/10 text-ink',
    warning: 'border-brass/50 bg-brass/10 text-ink',
    processing: 'border-ink/30 bg-muted text-ink',
    validating: 'border-ink/30 bg-muted text-ink',
    invalid: 'border-destructive/40 bg-destructive/10 text-destructive',
    failed: 'border-destructive/40 bg-destructive/10 text-destructive',
    completed_with_errors:
        'border-destructive/40 bg-destructive/10 text-destructive',
};

export default function StatusPill({ status }: StatusPillProps) {
    const tone = styles[status] ?? 'border-line bg-muted text-muted-foreground';

    return (
        <span
            className={`inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium ${tone}`}
        >
            <span className="size-1.5 rounded-full bg-current" />
            {status.replaceAll('_', ' ')}
        </span>
    );
}
