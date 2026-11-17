import { Form, Head, Link, usePage } from '@inertiajs/react';
import { Download, FileBarChart, Search } from 'lucide-react';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type CountRow = {
    name: string;
    count: number;
};

type MonthRow = {
    label: string;
    count: number;
};

type Filters = {
    from: string;
    to: string;
    grade: string;
    search: string;
};

type PageProps = {
    summary: {
        members: number;
        upgrades: number;
        batches: number;
        failed_rows: number;
    };
    gradeBreakdown: CountRow[];
    upgradeTypeBreakdown: CountRow[];
    monthlyRegistrations: MonthRow[];
    monthlyUpgrades: MonthRow[];
    grades: string[];
    filters: Filters;
    can: {
        exportRegister: boolean;
        exportUpgrades: boolean;
    };
};

type SharedProps = {
    flash?: {
        success?: string | null;
    };
};

function exportHref(path: string, filters: Filters): string {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
        if (value !== '') {
            params.set(key, value);
        }
    });

    const query = params.toString();

    return query === '' ? path : `${path}?${query}`;
}

function BreakdownCard({
    title,
    description,
    rows,
}: {
    title: string;
    description: string;
    rows: CountRow[];
}) {
    return (
        <section className="overflow-hidden rounded-xl border border-line bg-card">
            <div className="border-b border-line bg-muted/30 px-5 py-4">
                <h2 className="font-medium text-ink">{title}</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    {description}
                </p>
            </div>
            {rows.length === 0 ? (
                <p className="p-8 text-center text-sm text-muted-foreground">
                    No records in this view.
                </p>
            ) : (
                <div className="divide-y divide-line">
                    {rows.map((row) => (
                        <div
                            key={row.name}
                            className="flex items-center justify-between gap-4 px-5 py-3 text-sm"
                        >
                            <span className="text-ink-soft">{row.name}</span>
                            <span className="font-display text-xl text-ink tabular-nums">
                                {row.count}
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </section>
    );
}

function TrendCard({ title, rows }: { title: string; rows: MonthRow[] }) {
    const maximum = Math.max(1, ...rows.map((row) => row.count));

    return (
        <section className="overflow-hidden rounded-xl border border-line bg-card">
            <div className="border-b border-line bg-muted/30 px-5 py-4">
                <h2 className="font-medium text-ink">{title}</h2>
                <p className="mt-1 text-xs text-muted-foreground">
                    Six-month trend for the current view
                </p>
            </div>
            <div className="grid h-52 grid-cols-6 items-end gap-3 px-5 py-5">
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className="flex h-full flex-col items-center justify-end gap-2"
                    >
                        <span className="text-xs text-ink tabular-nums">
                            {row.count}
                        </span>
                        <div
                            className="w-full rounded-t-sm bg-verdigris/80"
                            style={{
                                height: `${Math.max(4, (row.count / maximum) * 100)}%`,
                            }}
                            title={`${row.label}: ${row.count}`}
                        />
                        <span className="text-[10px] whitespace-nowrap text-muted-foreground">
                            {row.label}
                        </span>
                    </div>
                ))}
            </div>
        </section>
    );
}

export default function Reports({
    summary,
    gradeBreakdown,
    upgradeTypeBreakdown,
    monthlyRegistrations,
    monthlyUpgrades,
    grades,
    filters,
    can,
}: PageProps) {
    const { flash } = usePage<SharedProps>().props;
    const hasFilters = Boolean(
        filters.from || filters.to || filters.grade || filters.search,
    );

    return (
        <>
            <Head title="Reports" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Administration"
                    title="Reports"
                    description="Summarise the register and upgrade history, then export the exact records in view."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Read-only analysis
                        </Badge>
                    }
                />

                {flash?.success && (
                    <p
                        role="status"
                        className="rounded-xl border border-verdigris/40 bg-verdigris/10 p-4 text-sm text-verdigris"
                    >
                        {flash.success}
                    </p>
                )}

                <MetricStrip
                    metrics={[
                        {
                            label: 'Members in view',
                            value: summary.members,
                            hint: 'After active filters',
                        },
                        {
                            label: 'Upgrades in view',
                            value: summary.upgrades,
                            tone: 'verified',
                            hint: 'By effective date',
                        },
                        {
                            label: 'Batches recorded',
                            value: summary.batches,
                            hint: 'All import runs',
                        },
                        {
                            label: 'Failed rows',
                            value: summary.failed_rows,
                            tone:
                                summary.failed_rows > 0 ? 'review' : 'neutral',
                            hint: 'Across all batches',
                        },
                    ]}
                />

                <Form
                    action="/reports"
                    method="get"
                    className="grid gap-4 rounded-xl border border-line bg-card p-5 md:grid-cols-2 xl:grid-cols-5"
                >
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
                    <div className="grid gap-2">
                        <label
                            htmlFor="grade"
                            className="text-sm font-medium text-ink"
                        >
                            Grade
                        </label>
                        <select
                            id="grade"
                            name="grade"
                            defaultValue={filters.grade}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="">All grades</option>
                            {grades.map((grade) => (
                                <option key={grade} value={grade}>
                                    {grade}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="grid gap-2 xl:col-span-2">
                        <label
                            htmlFor="search"
                            className="text-sm font-medium text-ink"
                        >
                            Search
                        </label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Number, name, email, or batch"
                        />
                    </div>
                    <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4 xl:col-span-5">
                        <Button type="submit">
                            <Search className="size-4" />
                            Apply filters
                        </Button>
                        <Button asChild variant="outline">
                            <Link href="/reports">Clear filters</Link>
                        </Button>
                        <span className="ml-auto text-xs text-muted-foreground">
                            {hasFilters ? 'Filtered view' : 'All records'}
                        </span>
                    </div>
                </Form>

                <section className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-line bg-card p-5">
                    <div>
                        <h2 className="flex items-center gap-2 font-medium text-ink">
                            <FileBarChart className="size-4 text-verdigris" />
                            Export the current view
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Exports include the filters above and are recorded
                            in the audit log.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can.exportRegister && (
                            <Button asChild>
                                <a
                                    href={exportHref(
                                        '/reports/register/export',
                                        filters,
                                    )}
                                >
                                    <Download className="size-4" />
                                    Register report
                                </a>
                            </Button>
                        )}
                        {can.exportUpgrades && (
                            <Button asChild variant="outline">
                                <a
                                    href={exportHref(
                                        '/reports/upgrades/export',
                                        filters,
                                    )}
                                >
                                    <Download className="size-4" />
                                    Upgrade report
                                </a>
                            </Button>
                        )}
                    </div>
                </section>

                <div className="grid gap-6 lg:grid-cols-2">
                    <BreakdownCard
                        title="Members by grade"
                        description="Current grade distribution in the filtered register"
                        rows={gradeBreakdown}
                    />
                    <BreakdownCard
                        title="Upgrades by type"
                        description="Confirmed upgrade programmes in the filtered history"
                        rows={upgradeTypeBreakdown}
                    />
                    <TrendCard
                        title="New registrations"
                        rows={monthlyRegistrations}
                    />
                    <TrendCard title="Upgrades" rows={monthlyUpgrades} />
                </div>
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports', href: '/reports' }],
};
