import { Form, Head, Link } from '@inertiajs/react';
import { ScrollText, Search, SlidersHorizontal } from 'lucide-react';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type AuditLogEntry = {
    id: number;
    action: string;
    action_label: string;
    actor: {
        id: number;
        name: string;
    } | null;
    actor_name: string | null;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    metadata: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string | null;
};

type PageProps = {
    logs: {
        data: AuditLogEntry[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    actions: string[];
    stats: {
        total: number;
        today: number;
        actions: number;
    };
    filters: {
        search: string;
        action: string;
        from: string;
        to: string;
    };
};

function formatTimestamp(value: string | null): string {
    if (value === null) {
        return 'Unknown';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function actorLabel(entry: AuditLogEntry): string {
    if (entry.actor) {
        return entry.actor.name;
    }

    if (entry.actor_name) {
        return `${entry.actor_name} (removed)`;
    }

    return 'System';
}

function MetadataPanel({
    metadata,
}: {
    metadata: Record<string, unknown> | null;
}) {
    if (metadata === null || Object.keys(metadata).length === 0) {
        return (
            <span className="text-muted-foreground">No context recorded.</span>
        );
    }

    return (
        <pre className="max-h-64 overflow-auto rounded-lg border border-line bg-paper/70 p-3 text-xs break-words whitespace-pre-wrap text-ink-soft">
            {JSON.stringify(metadata, null, 2)}
        </pre>
    );
}

export default function AuditLogs({
    logs,
    actions,
    stats,
    filters,
}: PageProps) {
    const hasFilters = Boolean(
        filters.search || filters.action || filters.from || filters.to,
    );

    return (
        <>
            <Head title="Audit Logs" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Governance"
                    title="Audit logs"
                    description="An immutable record of operational actions. Entries identify the actor, action, subject, and time, with before-and-after context where it matters."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Read-only ledger
                        </Badge>
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Entries recorded',
                            value: stats.total,
                            hint: 'Across the whole ledger',
                        },
                        {
                            label: 'Recorded today',
                            value: stats.today,
                            tone: stats.today > 0 ? 'verified' : 'neutral',
                            hint: 'Since midnight',
                        },
                        {
                            label: 'Action types',
                            value: stats.actions,
                            hint: 'Distinct recorded actions',
                        },
                        {
                            label: 'View state',
                            value: hasFilters ? 'Filtered' : 'All entries',
                            tone: hasFilters ? 'review' : 'neutral',
                            hint: hasFilters
                                ? 'Clear filters to reset'
                                : 'Newest entries first',
                        },
                    ]}
                />

                <Form
                    action="/audit-logs"
                    method="get"
                    className="grid gap-4 rounded-xl border border-line bg-card p-5 md:grid-cols-2 xl:grid-cols-5"
                >
                    <div className="grid gap-2 xl:col-span-2">
                        <label
                            htmlFor="search"
                            className="text-sm font-medium text-ink"
                        >
                            Search the ledger
                        </label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Actor, action, subject, IP, or context"
                        />
                    </div>

                    <div className="grid gap-2">
                        <label
                            htmlFor="action"
                            className="text-sm font-medium text-ink"
                        >
                            Action
                        </label>
                        <select
                            id="action"
                            name="action"
                            defaultValue={filters.action}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All actions</option>
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {action}
                                </option>
                            ))}
                        </select>
                    </div>

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

                    <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4 xl:col-span-5">
                        <Button type="submit">
                            <Search className="size-4" />
                            Apply filters
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/audit-logs">Clear filters</Link>
                        </Button>
                        <span className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                            <SlidersHorizontal className="size-3.5" />
                            Audit entries cannot be edited or removed.
                        </span>
                    </div>
                </Form>

                <section className="overflow-hidden rounded-xl border border-line bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                        <div>
                            <h2 className="flex items-center gap-2 font-medium text-ink">
                                <ScrollText className="size-4 text-verdigris" />
                                Recorded actions
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                {logs.total === 0
                                    ? 'No entries match this view.'
                                    : `${logs.total.toLocaleString()} matching ${logs.total === 1 ? 'entry' : 'entries'}.`}
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Page {logs.current_page} of {logs.last_page}
                        </Badge>
                    </div>

                    {logs.data.length === 0 ? (
                        <div className="p-8 text-center">
                            <h3 className="font-medium text-ink">
                                {stats.total === 0
                                    ? 'No audit entries yet'
                                    : 'No matching entries'}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {stats.total === 0
                                    ? 'Uploads, processing, exports, and account actions will appear here.'
                                    : 'Try adjusting the search or date filters.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            When
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Actor
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Action
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Subject
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Context
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Source
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="border-t border-line align-top transition-colors hover:bg-accent/30"
                                        >
                                            <td className="px-5 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatTimestamp(
                                                    entry.created_at,
                                                )}
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="font-medium text-ink">
                                                    {actorLabel(entry)}
                                                </span>
                                                {entry.actor === null &&
                                                    entry.actor_name !==
                                                        null && (
                                                        <span className="mt-1 block text-xs text-brass">
                                                            Account no longer
                                                            present
                                                        </span>
                                                    )}
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="font-medium text-ink">
                                                    {entry.action_label}
                                                </span>
                                                <span className="mt-1 block font-mono text-xs text-muted-foreground">
                                                    {entry.action}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="block text-ink">
                                                    {entry.subject_label ?? '—'}
                                                </span>
                                                <span className="mt-1 block text-xs text-muted-foreground">
                                                    {entry.subject_type ??
                                                        'No linked record'}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3">
                                                {entry.metadata ? (
                                                    <Accordion className="min-w-56">
                                                        <AccordionItem
                                                            title="Inspect context"
                                                            description="Recorded before / after data"
                                                        >
                                                            <MetadataPanel
                                                                metadata={
                                                                    entry.metadata
                                                                }
                                                            />
                                                        </AccordionItem>
                                                    </Accordion>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        No context
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-3 font-mono text-xs text-muted-foreground">
                                                {entry.ip_address ?? 'Unknown'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {logs.last_page > 1 && (
                        <nav
                            aria-label="Audit log pages"
                            className="flex flex-wrap items-center justify-center gap-2 border-t border-line p-4"
                        >
                            {logs.links.map((link) =>
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

AuditLogs.layout = {
    breadcrumbs: [
        {
            title: 'Audit Logs',
            href: '/audit-logs',
        },
    ],
};
