import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowLeft,
    ArrowRight,
    Download,
    Layers3,
    ListChecks,
    ShieldCheck,
} from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import StatusPill from '@/components/status-pill';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';

type Batch = {
    batch_reference: string;
    status: string;
    upgrade_type: string | null;
    original_filename: string;
    detected_headings: string[];
    total_rows: number;
    valid_rows: number;
    failed_rows: number;
    warning_rows: number;
    successful_rows: number;
    failure_reason: string | null;
    uploaded_at: string | null;
    processed_at: string | null;
};

type BatchRow = {
    row_number: number;
    supplied_membership_number: string | null;
    supplied_full_name: string | null;
    current_grade: string | null;
    intended_grade: string | null;
    status: 'valid' | 'warning' | 'invalid' | 'processed';
    errors: string[];
    warnings: string[];
};

type PageProps = {
    batch: Batch;
    rows: {
        data: BatchRow[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        current_page: number;
        last_page: number;
    };
    can: {
        process: boolean;
        export: boolean;
    };
};

type SharedProps = {
    flash?: { success?: string | null };
    errors?: Record<string, string>;
};

const gradePath = ['Graduate', 'Associate', 'Member', 'Fellow'];

export default function ShowUpgradeBatch({ batch, rows, can }: PageProps) {
    const { flash, errors } = usePage<SharedProps>().props;
    const [processing, setProcessing] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const isProcessed =
        batch.status === 'completed' ||
        batch.status === 'completed_with_errors';
    const canProcessBatch =
        can.process &&
        batch.status === 'ready_for_review' &&
        batch.valid_rows > 0;
    const sampleRow = rows.data.find((row) => row.current_grade !== null);
    const currentIndex = gradePath.indexOf(sampleRow?.current_grade ?? '');
    const intendedIndex = gradePath.indexOf(sampleRow?.intended_grade ?? '');

    function processBatch() {
        setConfirmOpen(false);
        setProcessing(true);
        router.post(
            `/upgrade-batches/${batch.batch_reference}/process`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title={`Upgrade Batch ${batch.batch_reference}`} />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label={isProcessed ? 'Upgrade result' : 'Upgrade review'}
                    title={isProcessed ? 'Upgrade result' : 'Review upgrades'}
                    description={`Batch ${batch.batch_reference} · ${batch.upgrade_type ?? 'No type recorded'} · ${batch.original_filename}`}
                    actions={
                        <div className="flex flex-wrap gap-2">
                            {can.export && isProcessed && (
                                <Button asChild variant="outline">
                                    <a
                                        href={`/upgrade-batches/${batch.batch_reference}/export`}
                                        download
                                    >
                                        <Download className="size-4" />
                                        Download results
                                    </a>
                                </Button>
                            )}
                            <Button asChild variant="outline">
                                <Link href="/upgrade-batches/create">
                                    <ArrowLeft className="size-4" />
                                    Upload another workbook
                                </Link>
                            </Button>
                        </div>
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
                            label: 'Rows in file',
                            value: batch.total_rows,
                            hint: 'Matched by membership number',
                        },
                        {
                            label: 'Valid rows',
                            value: batch.valid_rows,
                            tone: 'verified',
                            hint: 'Ready for promotion',
                        },
                        {
                            label: 'Warning rows',
                            value: batch.warning_rows,
                            tone: 'review',
                            hint: 'Review before processing',
                        },
                        {
                            label: isProcessed ? 'Upgraded' : 'Blocked',
                            value: isProcessed
                                ? batch.successful_rows
                                : batch.failed_rows,
                            tone: isProcessed ? 'verified' : 'blocked',
                            hint: isProcessed
                                ? 'History records written'
                                : 'Rows need correction',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="overflow-hidden rounded-xl border border-line bg-card lg:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                            <div className="flex items-center gap-3">
                                <ShieldCheck className="size-5 text-verdigris" />
                                <div>
                                    <h2 className="font-medium text-ink">
                                        Grade movement
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Current grade comes from the file.
                                        Intended is one step above it.
                                    </p>
                                </div>
                            </div>
                            <StatusPill status={batch.status} />
                        </div>

                        <div className="space-y-5 p-5">
                            <ol className="flex flex-wrap items-center gap-2">
                                {gradePath.map((grade, index) => {
                                    const isCurrent = index === currentIndex;
                                    const isIntended = index === intendedIndex;

                                    return (
                                        <li
                                            key={grade}
                                            className="flex items-center gap-2"
                                        >
                                            <span
                                                className={`rounded-full border px-3 py-1.5 text-sm font-medium ${
                                                    isCurrent
                                                        ? 'border-brass bg-brass/10 text-ink'
                                                        : isIntended
                                                          ? 'border-verdigris bg-verdigris/10 text-verdigris'
                                                          : 'border-line bg-paper/60 text-muted-foreground'
                                                }`}
                                            >
                                                {grade}
                                                {isCurrent && (
                                                    <span className="ml-2 text-xs">
                                                        current
                                                    </span>
                                                )}
                                                {isIntended && (
                                                    <span className="ml-2 text-xs">
                                                        intended
                                                    </span>
                                                )}
                                            </span>
                                            {index < gradePath.length - 1 && (
                                                <ArrowRight className="size-3.5 text-brass" />
                                            )}
                                        </li>
                                    );
                                })}
                            </ol>

                            <InputError message={errors?.batch} />

                            {batch.detected_headings.length > 0 && (
                                <div className="space-y-2">
                                    <p className="text-xs font-medium text-muted-foreground">
                                        Detected headings
                                    </p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {batch.detected_headings.map(
                                            (heading) => (
                                                <span
                                                    key={heading}
                                                    className="rounded-md border border-line bg-paper/70 px-2 py-1 text-xs text-ink"
                                                >
                                                    {heading}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                            {batch.failure_reason && (
                                <p
                                    role="alert"
                                    className="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
                                >
                                    {batch.failure_reason}
                                </p>
                            )}

                            {canProcessBatch ? (
                                <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-brass/50 bg-brass/5 p-5">
                                    <div className="max-w-lg space-y-1">
                                        <p className="font-medium text-ink">
                                            Ready to commit
                                        </p>
                                        <p className="text-sm leading-relaxed text-muted-foreground">
                                            {batch.valid_rows} valid and{' '}
                                            {batch.warning_rows} warning rows
                                            will update grades and history.
                                            Membership numbers will be retained.
                                        </p>
                                    </div>
                                    <Button
                                        onClick={() => setConfirmOpen(true)}
                                    >
                                        <ShieldCheck className="size-4" />
                                        Process upgrades
                                    </Button>
                                </div>
                            ) : isProcessed ? (
                                <p className="rounded-xl bg-muted/40 p-4 text-sm text-muted-foreground">
                                    This batch is closed. Grades and history
                                    were written without changing membership
                                    numbers.
                                </p>
                            ) : batch.status === 'ready_for_review' &&
                              batch.valid_rows === 0 ? (
                                <p className="rounded-xl bg-destructive/5 p-4 text-sm text-muted-foreground">
                                    No valid rows to process. Correct the
                                    workbook and upload a new batch.
                                </p>
                            ) : (
                                <p className="rounded-xl bg-muted/40 p-4 text-sm text-muted-foreground">
                                    Validation preview only. No grades have been
                                    changed.
                                </p>
                            )}
                        </div>
                    </section>

                    <aside className="space-y-4">
                        <section className="rounded-xl border border-line bg-card">
                            <div className="border-b border-line px-5 py-4">
                                <h2 className="font-medium text-ink">
                                    Safeguards
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    What is checked before a row can commit.
                                </p>
                            </div>
                            <div className="px-5">
                                <Accordion>
                                    <AccordionItem
                                        title="Matching"
                                        description="By membership number"
                                        defaultOpen
                                    >
                                        Names never drive the match. A row is
                                        only processed when the exact membership
                                        number exists in the register.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Key information"
                                        description="Email, phone, dob, grade"
                                    >
                                        Material mismatches block the row.
                                        Missing registered details are warnings
                                        that need a human decision.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="One-step rule"
                                        description="No skipping grades"
                                    >
                                        Graduate goes to Associate, Associate to
                                        Member, and Member to Fellow. Anything
                                        else is rejected.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Membership number"
                                        description="Never changed"
                                    >
                                        Upgrades only change the grade and write
                                        history. The number in the file must
                                        still match the member at commit time.
                                    </AccordionItem>
                                </Accordion>
                            </div>
                        </section>
                    </aside>
                </div>

                {rows.data.length > 0 && (
                    <section className="overflow-hidden rounded-xl border border-line bg-card">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                            <div className="flex items-center gap-3">
                                <ListChecks className="size-5 text-brass" />
                                <div>
                                    <h2 className="font-medium text-ink">
                                        Workbook rows
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Current grade is from the file; intended
                                        grade is computed one step up.
                                    </p>
                                </div>
                            </div>
                            <span className="text-xs text-muted-foreground tabular-nums">
                                Page {rows.current_page} of {rows.last_page}
                            </span>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Row
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Membership no.
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Supplied name
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Current grade
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Intended grade
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {rows.data.map((row) => (
                                        <tr
                                            key={row.row_number}
                                            className="border-t border-line align-top"
                                        >
                                            <td className="px-5 py-3 text-muted-foreground tabular-nums">
                                                {row.row_number}
                                            </td>
                                            <td className="px-5 py-3 font-medium text-ink tabular-nums">
                                                {row.supplied_membership_number ||
                                                    '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.supplied_full_name || '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.current_grade || '—'}
                                            </td>
                                            <td className="px-5 py-3 font-medium text-verdigris">
                                                {row.intended_grade || '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                <StatusPill
                                                    status={row.status}
                                                />
                                                {row.errors.length > 0 && (
                                                    <ul className="mt-2 list-inside list-disc space-y-1 text-xs text-destructive">
                                                        {row.errors.map(
                                                            (error) => (
                                                                <li key={error}>
                                                                    {error}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                                {row.warnings.length > 0 && (
                                                    <ul className="mt-2 list-inside list-disc space-y-1 text-xs text-muted-foreground">
                                                        {row.warnings.map(
                                                            (warning) => (
                                                                <li
                                                                    key={
                                                                        warning
                                                                    }
                                                                >
                                                                    {warning}
                                                                </li>
                                                            ),
                                                        )}
                                                    </ul>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {rows.last_page > 1 && (
                            <nav
                                aria-label="Upgrade batch pages"
                                className="flex flex-wrap items-center justify-center gap-2 border-t border-line p-4"
                            >
                                {rows.links.map((link) =>
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
                )}
            </div>

            <ConfirmDialog
                open={confirmOpen}
                onOpenChange={setConfirmOpen}
                title="Commit this upgrade batch?"
                description="Member grades and history will be updated. Membership numbers will not change. This cannot be undone from this screen."
                confirmLabel="Process upgrades"
                pendingLabel="Committing…"
                pending={processing}
                onConfirm={processBatch}
                details={
                    <div className="grid gap-3 sm:grid-cols-3">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Valid rows
                            </p>
                            <p className="font-display text-2xl text-ink tabular-nums">
                                {batch.valid_rows}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Warnings
                            </p>
                            <p className="font-display text-2xl text-ink tabular-nums">
                                {batch.warning_rows}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Skipped
                            </p>
                            <p className="font-display text-2xl text-ink tabular-nums">
                                {batch.failed_rows}
                            </p>
                        </div>
                        <p className="flex items-start gap-2 text-xs sm:col-span-3">
                            <Layers3 className="mt-0.5 size-4 shrink-0 text-brass" />
                            Each valid row moves exactly one grade up and keeps
                            its existing membership number.
                        </p>
                    </div>
                }
            />
        </>
    );
}
