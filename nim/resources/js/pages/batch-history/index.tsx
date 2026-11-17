import { Form, Head, Link } from '@inertiajs/react';
import { History, Search } from 'lucide-react';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import StatusPill from '@/components/status-pill';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type BatchHistoryEntry = {
    id: number;
    batch_reference: string;
    batch_type: string;
    batch_type_label: string;
    status: string;
    uploaded_by: string;
    uploaded_at: string | null;
    processed_at: string | null;
    original_filename: string;
    total_rows: number;
    successful_rows: number;
    failed_rows: number;
    warning_rows: number;
    failure_reason: string | null;
    review_url: string;
};

type FilterOption = {
    value: string;
    label: string;
};

type PageProps = {
    batches: {
        data: BatchHistoryEntry[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    types: FilterOption[];
    statuses: FilterOption[];
    filters: {
        search: string;
        type: string;
        status: string;
        from: string;
        to: string;
    };
    stats: {
        total: number;
        awaiting_review: number;
        completed: number;
        with_errors: number;
    };
};

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Not yet';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function BatchHistory({
    batches,
    types,
    statuses,
    filters,
    stats,
}: PageProps) {
    const hasFilters = Boolean(
        filters.search ||
        filters.type ||
        filters.status ||
        filters.from ||
        filters.to,
    );

    return (
        <>
            <Head title="Import History" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Registry operations"
                    title="Import history"
                    description="Every upload and processing run, with row outcomes and a direct route back to the original review screen."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Non-destructive record
                        </Badge>
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Batches uploaded',
                            value: stats.total,
                            hint: 'All recorded imports',
                        },
                        {
                            label: 'Awaiting review',
                            value: stats.awaiting_review,
                            tone: 'review',
                            hint: 'Validated but not committed',
                        },
                        {
                            label: 'Completed',
                            value: stats.completed,
                            tone: 'verified',
                            hint: 'Processed batches',
                        },
                        {
                            label: 'With failures',
                            value: stats.with_errors,
                            tone: stats.with_errors > 0 ? 'blocked' : 'neutral',
                            hint: 'Failed batches or failed rows',
                        },
                    ]}
                />

                <Form
                    action="/batch-history"
                    method="get"
                    className="grid gap-4 rounded-xl border border-line bg-card p-5 md:grid-cols-2 xl:grid-cols-5"
                >
                    <div className="grid gap-2 xl:col-span-2">
                        <label
                            htmlFor="search"
                            className="text-sm font-medium text-ink"
                        >
                            Search history
                        </label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Reference, file, uploader, or error"
                        />
                    </div>

                    <div className="grid gap-2">
                        <label
                            htmlFor="type"
                            className="text-sm font-medium text-ink"
                        >
                            Batch type
                        </label>
                        <select
                            id="type"
                            name="type"
                            defaultValue={filters.type}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All types</option>
                            {types.map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <label
                            htmlFor="status"
                            className="text-sm font-medium text-ink"
                        >
                            Status
                        </label>
                        <select
                            id="status"
                            name="status"
                            defaultValue={filters.status}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <div className="grid gap-2">
                            <label
                                htmlFor="from"
                                className="text-sm font-medium text-ink"
                            >
                                From
                            </label>
                            <Input
                                id="from"
                                name="from"
                                type="date"
                                defaultValue={filters.from}
                            />
                        </div>
                        <div className="grid gap-2">
                            <label
                                htmlFor="to"
                                className="text-sm font-medium text-ink"
                            >
                                To
                            </label>
                            <Input
                                id="to"
                                name="to"
                                type="date"
                                defaultValue={filters.to}
                            />
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4 xl:col-span-5">
                        <Button type="submit">
                            <Search className="size-4" />
                            Apply filters
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/batch-history">Clear filters</Link>
                        </Button>
                        <span className="ml-auto text-xs text-muted-foreground">
                            {hasFilters
                                ? `${batches.total} matching batch${batches.total === 1 ? '' : 'es'}`
                                : `${stats.total} batch${stats.total === 1 ? '' : 'es'} recorded`}
                        </span>
                    </div>
                </Form>

                <section className="overflow-hidden rounded-xl border border-line bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                        <div>
                            <h2 className="flex items-center gap-2 font-medium text-ink">
                                <History className="size-4 text-verdigris" />
                                Recorded batches
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Newest uploads first. Open a batch to inspect
                                row-level outcomes.
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Page {batches.current_page} of {batches.last_page}
                        </Badge>
                    </div>

                    {batches.data.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            {hasFilters
                                ? 'No batches match these filters.'
                                : 'No imports have been recorded yet.'}
                        </p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Batch
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Type
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Uploaded
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Rows
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Processed
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Review
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {batches.data.map((batch) => (
                                        <tr
                                            key={batch.id}
                                            className="border-t border-line align-top transition-colors hover:bg-accent/30"
                                        >
                                            <td className="px-5 py-3">
                                                <span className="font-medium text-ink">
                                                    {batch.batch_reference}
                                                </span>
                                                <span className="mt-1 block max-w-52 truncate text-xs text-muted-foreground">
                                                    {batch.original_filename}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    by {batch.uploaded_by}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft">
                                                {batch.batch_type_label}
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDate(batch.uploaded_at)}
                                            </td>
                                            <td className="px-5 py-3">
                                                <StatusPill
                                                    status={batch.status}
                                                />
                                                {batch.failure_reason && (
                                                    <span className="mt-1 block max-w-56 text-xs text-brass">
                                                        {batch.failure_reason}
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-ink-soft tabular-nums">
                                                <span>
                                                    {batch.total_rows} total
                                                </span>
                                                <span className="mt-1 block text-xs text-verdigris">
                                                    {batch.successful_rows}{' '}
                                                    successful
                                                </span>
                                                <span className="mt-1 block text-xs text-brass">
                                                    {batch.failed_rows} failed ·{' '}
                                                    {batch.warning_rows}{' '}
                                                    warnings
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDate(batch.processed_at)}
                                            </td>
                                            <td className="px-5 py-3">
                                                <Button
                                                    asChild
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={batch.review_url}
                                                    >
                                                        Open review
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {batches.last_page > 1 && (
                        <nav
                            aria-label="Batch history pages"
                            className="flex flex-wrap items-center justify-center gap-2 border-t border-line p-4"
                        >
                            {batches.links.map((link) =>
                                link.url === null ? (
                                    <span
                                        key={link.label}
                                        aria-disabled="true"
                                        className="rounded-md border border-line px-3 py-1.5 text-sm text-muted-foreground"
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </span>
                                ) : (
                                    <Link
                                        key={link.label}
                                        href={link.url}
                                        aria-current={
                                            link.active ? 'page' : undefined
                                        }
                                        className={`rounded-md border px-3 py-1.5 text-sm ${
                                            link.active
                                                ? 'border-ink bg-ink text-white'
                                                : 'border-line hover:bg-accent'
                                        }`}
                                    >
                                        {link.label
                                            .replace('&laquo;', '‹')
                                            .replace('&raquo;', '›')}
                                    </Link>
                                ),
                            )}
                        </nav>
                    )}
                </section>
            </div>
        </>
    );
}

BatchHistory.layout = {
    breadcrumbs: [{ title: 'Import History', href: '/batch-history' }],
};
