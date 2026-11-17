import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    ClipboardCheck,
    FileSpreadsheet,
    Layers3,
    Search,
    ShieldCheck,
} from 'lucide-react';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import ProcessSteps from '@/components/process-steps';
import StatusPill from '@/components/status-pill';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { index as membersIndex } from '@/routes/members';
import { create as createMembershipBatch } from '@/routes/membership-batches';

type LatestBatch = {
    batch_reference: string;
    batch_type: string;
    status: string;
    uploaded_by: string;
    uploaded_at: string | null;
    review_url: string;
};

type PageProps = {
    metrics: {
        members: number;
        newMembersThisMonth: number;
        upgradesThisMonth: number;
        awaitingReview: number;
    };
    latestBatches: LatestBatch[];
    gradeBreakdown: {
        name: string;
        count: number;
    }[];
};

type SharedProps = PageProps & {
    auth: {
        can: Record<string, boolean>;
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

export default function Dashboard({
    metrics,
    latestBatches,
    gradeBreakdown,
}: PageProps) {
    const { auth } = usePage<SharedProps>().props;
    const largestGradeCount = Math.max(
        1,
        ...gradeBreakdown.map((grade) => grade.count),
    );

    const actions = [
        {
            title: 'Search the register',
            description:
                'Find a member by number, name, grade, or registration date.',
            href: membersIndex(),
            icon: Search,
        },
        ...(auth.can.membershipRegisterImport
            ? [
                  {
                      title: 'New membership upload',
                      description:
                          'Validate new members and preview numbers before committing.',
                      href: createMembershipBatch(),
                      icon: FileSpreadsheet,
                  },
              ]
            : []),
        ...(auth.can.membershipUpgradeImport
            ? [
                  {
                      title: 'Membership upgrades',
                      description:
                          'Review one-step grade changes without altering membership numbers.',
                      href: '/upgrade-batches/create',
                      icon: Layers3,
                  },
              ]
            : []),
        ...(auth.can.upgradeTypesManage
            ? [
                  {
                      title: 'Upgrade types',
                      description:
                          'Keep the approved programme list current for upgrade batches.',
                      href: '/settings/upgrade-types',
                      icon: ShieldCheck,
                  },
              ]
            : []),
    ];

    return (
        <>
            <Head title="Dashboard" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Operations desk"
                    title="Membership registry"
                    description="Prepare files, review what will change, then commit with a clear record of who did what."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-verdigris/40 text-verdigris"
                        >
                            Review before commit
                        </Badge>
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Members on register',
                            value: metrics.members,
                            hint: 'Current membership records',
                        },
                        {
                            label: 'New this month',
                            value: metrics.newMembersThisMonth,
                            tone: 'verified',
                            hint: 'By registration date',
                        },
                        {
                            label: 'Upgrades this month',
                            value: metrics.upgradesThisMonth,
                            tone: 'verified',
                            hint: 'By effective date',
                        },
                        {
                            label: 'Awaiting review',
                            value: metrics.awaitingReview,
                            tone:
                                metrics.awaitingReview > 0
                                    ? 'review'
                                    : 'neutral',
                            hint: 'Validated but not committed',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <section className="overflow-hidden rounded-xl border border-line bg-card">
                            <div className="border-b border-line bg-ink px-6 py-6 text-white">
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div className="max-w-xl space-y-2">
                                        <p className="text-sm text-white/70">
                                            Start here
                                        </p>
                                        <h2 className="font-display text-2xl leading-tight text-white sm:text-3xl">
                                            Every change passes through a
                                            review.
                                        </h2>
                                        <p className="text-sm leading-relaxed text-white/75">
                                            Uploads are non-destructive. Nothing
                                            is written to the register until the
                                            batch is explicitly processed.
                                        </p>
                                    </div>
                                    <ClipboardCheck className="size-10 text-brass/90" />
                                </div>
                            </div>

                            <div className="grid gap-px bg-line sm:grid-cols-2">
                                {actions.map((action) => (
                                    <Link
                                        key={action.title}
                                        href={action.href}
                                        className="group flex items-start gap-4 bg-card p-5 transition-colors hover:bg-accent/50"
                                    >
                                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg border border-line bg-muted/60 text-ink">
                                            <action.icon className="size-5" />
                                        </span>
                                        <span className="min-w-0 flex-1 space-y-1">
                                            <span className="flex items-center justify-between gap-2">
                                                <span className="font-medium text-ink">
                                                    {action.title}
                                                </span>
                                                <ArrowUpRight className="size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-verdigris" />
                                            </span>
                                            <span className="block text-sm leading-relaxed text-muted-foreground">
                                                {action.description}
                                            </span>
                                        </span>
                                    </Link>
                                ))}
                            </div>
                        </section>

                        <section className="overflow-hidden rounded-xl border border-line bg-card">
                            <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                                <div>
                                    <h2 className="font-medium text-ink">
                                        Register at a glance
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Current grade distribution and the most
                                        recent work at the desk.
                                    </p>
                                </div>
                                {auth.can.batchesView && (
                                    <Button asChild variant="outline" size="sm">
                                        <Link href="/batch-history">
                                            Full import history
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            <div className="grid gap-px bg-line md:grid-cols-2">
                                <div className="bg-card p-5">
                                    <h3 className="text-sm font-medium text-ink">
                                        Members by grade
                                    </h3>
                                    {gradeBreakdown.length === 0 ? (
                                        <p className="mt-4 text-sm text-muted-foreground">
                                            No grade data is configured yet.
                                        </p>
                                    ) : (
                                        <div className="mt-4 space-y-3">
                                            {gradeBreakdown.map((grade) => (
                                                <div
                                                    key={grade.name}
                                                    className="grid gap-1.5"
                                                >
                                                    <div className="flex items-center justify-between text-sm">
                                                        <span className="text-ink-soft">
                                                            {grade.name}
                                                        </span>
                                                        <span className="text-ink tabular-nums">
                                                            {grade.count}
                                                        </span>
                                                    </div>
                                                    <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                                        <div
                                                            className="h-full rounded-full bg-verdigris"
                                                            style={{
                                                                width: `${(grade.count / largestGradeCount) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="bg-card p-5">
                                    <h3 className="text-sm font-medium text-ink">
                                        Recent batches
                                    </h3>
                                    {latestBatches.length === 0 ? (
                                        <p className="mt-4 text-sm text-muted-foreground">
                                            No imports have been recorded yet.
                                        </p>
                                    ) : (
                                        <div className="mt-4 divide-y divide-line">
                                            {latestBatches.map((batch) => (
                                                <div
                                                    key={batch.batch_reference}
                                                    className="flex flex-wrap items-start justify-between gap-2 py-2 first:pt-0"
                                                >
                                                    <div className="min-w-0">
                                                        <Link
                                                            href={
                                                                batch.review_url
                                                            }
                                                            className="block truncate font-mono text-xs text-ink hover:underline"
                                                        >
                                                            {
                                                                batch.batch_reference
                                                            }
                                                        </Link>
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            {batch.batch_type} ·{' '}
                                                            {formatDate(
                                                                batch.uploaded_at,
                                                            )}
                                                        </p>
                                                    </div>
                                                    <StatusPill
                                                        status={batch.status}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </section>

                        <section className="space-y-4 rounded-xl border border-line bg-card p-6">
                            <div>
                                <h2 className="font-display text-xl text-ink">
                                    How a batch moves
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Three deliberate steps, with the same
                                    sequence for registrations and upgrades.
                                </p>
                            </div>
                            <ProcessSteps
                                steps={[
                                    {
                                        title: 'Prepare',
                                        description:
                                            'Download the template and fill one sheet with the required headings.',
                                        state: 'complete',
                                    },
                                    {
                                        title: 'Preview',
                                        description:
                                            'Upload to validate rows and see exactly what the system will change.',
                                        state: 'current',
                                    },
                                    {
                                        title: 'Commit',
                                        description:
                                            'Confirm in the dialog; numbers and history are written together.',
                                        state: 'upcoming',
                                    },
                                ]}
                            />
                        </section>
                    </div>

                    <aside className="space-y-4">
                        <section className="rounded-xl border border-line bg-card">
                            <div className="border-b border-line px-5 py-4">
                                <h2 className="font-medium text-ink">
                                    Desk notes
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Rules worth knowing before an upload.
                                </p>
                            </div>
                            <div className="px-5">
                                <Accordion>
                                    <AccordionItem
                                        title="Membership numbers"
                                        description="Six digits, numeric only"
                                        defaultOpen
                                    >
                                        Supplied numbers are preserved when new.
                                        Missing numbers are allocated after
                                        sorting by last name. An existing number
                                        is never reused or changed by an
                                        upgrade.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Grade aliases"
                                        description="Common NIM headings are accepted"
                                    >
                                        Values such as member_research,
                                        member_associate, member_exams,
                                        member_graduate, member_grad, and
                                        member_nysc resolve to the configured
                                        grade.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Upgrade steps"
                                        description="One promotion at a time"
                                    >
                                        The grade in the file is the member’s
                                        current grade. The intended grade is
                                        always the next step: Graduate →
                                        Associate → Member → Fellow.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Re-uploads"
                                        description="Safe to repeat"
                                    >
                                        The same workbook can be uploaded again
                                        as a separate batch. Each preview is
                                        reviewed on its own before processing.
                                    </AccordionItem>
                                </Accordion>
                            </div>
                        </section>

                        <section className="rounded-xl border border-brass/40 bg-brass/5 p-5">
                            <p className="font-medium text-ink">
                                Need the exact template?
                            </p>
                            <p className="mt-1 text-sm leading-relaxed text-muted-foreground">
                                Download it from the upload screen. Column names
                                are flexible, but the template is the fastest
                                way to start.
                            </p>
                            {auth.can.membershipRegisterImport && (
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-4"
                                >
                                    <Link href={createMembershipBatch()}>
                                        Open upload screen
                                    </Link>
                                </Button>
                            )}
                        </section>
                    </aside>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
