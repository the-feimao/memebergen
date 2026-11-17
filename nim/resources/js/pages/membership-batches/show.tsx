import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertCircle,
    ArrowLeft,
    ClipboardCheck,
    ListChecks,
} from 'lucide-react';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import StatusPill from '@/components/status-pill';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { create as createMembershipBatch } from '@/routes/membership-batches';

type Batch = {
    batch_reference: string;
    batch_type: string;
    status: string;
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
    full_name: string | null;
    email: string | null;
    phone: string | null;
    state: string | null;
    registration_date: string | null;
    grade: string | null;
    supplied_membership_number: string | null;
    allocated_membership_number: string | null;
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
    };
};

type SharedProps = {
    flash?: {
        success?: string | null;
    };
    errors?: Record<string, string>;
};

function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatRegistrationDate(value: string | null): string {
    if (value === null) {
        return 'At processing';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}

export default function ShowMembershipBatch({ batch, rows, can }: PageProps) {
    const { flash, errors } = usePage<SharedProps>().props;
    const [processing, setProcessing] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);

    const isProcessed =
        batch.status === 'completed' ||
        batch.status === 'completed_with_errors';
    const canProcessBatch =
        can.process &&
        batch.batch_type === 'new_registration' &&
        batch.status === 'ready_for_review' &&
        batch.valid_rows > 0;

    function processBatch() {
        setConfirmOpen(false);
        setProcessing(true);
        router.post(
            `/membership-batches/${batch.batch_reference}/process`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <>
            <Head title={`Batch ${batch.batch_reference}`} />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label={isProcessed ? 'Batch result' : 'Review queue'}
                    title={
                        isProcessed
                            ? 'Registration result'
                            : 'Review registrations'
                    }
                    description={`Batch ${batch.batch_reference} · ${batch.original_filename}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={createMembershipBatch()}>
                                <ArrowLeft className="size-4" />
                                Upload another workbook
                            </Link>
                        </Button>
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
                            hint: 'Shown in worksheet order',
                        },
                        {
                            label: 'Valid rows',
                            value: batch.valid_rows,
                            tone: 'verified',
                            hint: 'Ready for the register',
                        },
                        {
                            label: 'Warning rows',
                            value: batch.warning_rows,
                            tone: 'review',
                            hint: 'Processed after review',
                        },
                        {
                            label: isProcessed ? 'Committed' : 'Blocked',
                            value: isProcessed
                                ? batch.successful_rows
                                : batch.failed_rows,
                            tone: isProcessed ? 'verified' : 'blocked',
                            hint: isProcessed
                                ? 'Members created'
                                : 'Rows need correction',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="overflow-hidden rounded-xl border border-line bg-card lg:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                            <div className="flex items-center gap-3">
                                <ClipboardCheck className="size-5 text-verdigris" />
                                <div>
                                    <h2 className="font-medium text-ink">
                                        Batch decision
                                    </h2>
                                    <p className="mt-0.5 text-xs text-muted-foreground">
                                        Uploaded{' '}
                                        {formatDateTime(batch.uploaded_at)}
                                        {batch.processed_at &&
                                            ` · processed ${formatDateTime(batch.processed_at)}`}
                                    </p>
                                </div>
                            </div>
                            <StatusPill status={batch.status} />
                        </div>

                        <div className="space-y-4 p-5">
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
                                            will be written. Supplied numbers
                                            are retained; missing numbers are
                                            allocated by last name.
                                        </p>
                                    </div>
                                    <Button
                                        onClick={() => setConfirmOpen(true)}
                                    >
                                        <ClipboardCheck className="size-4" />
                                        Process batch
                                    </Button>
                                </div>
                            ) : isProcessed ? (
                                <p className="rounded-xl bg-muted/40 p-4 text-sm text-muted-foreground">
                                    This batch is closed. Membership numbers
                                    shown below are the numbers now held in the
                                    register.
                                </p>
                            ) : batch.status === 'ready_for_review' &&
                              batch.valid_rows === 0 ? (
                                <p className="rounded-xl bg-destructive/5 p-4 text-sm text-muted-foreground">
                                    No valid rows to process. Correct the
                                    workbook and upload a new batch.
                                </p>
                            ) : (
                                <p className="rounded-xl bg-muted/40 p-4 text-sm text-muted-foreground">
                                    Validation preview only. No members or
                                    numbers have been created.
                                </p>
                            )}
                        </div>
                    </section>

                    <aside className="space-y-4">
                        <section className="rounded-xl border border-line bg-card">
                            <div className="border-b border-line px-5 py-4">
                                <h2 className="font-medium text-ink">
                                    Review notes
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    What the commit action will do.
                                </p>
                            </div>
                            <div className="px-5">
                                <Accordion>
                                    <AccordionItem
                                        title="Rows that will be written"
                                        description="Valid and warning rows"
                                        defaultOpen
                                    >
                                        Invalid rows are skipped. Warning rows
                                        are included because the flag needs
                                        review, not rejection.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Number allocation"
                                        description="Supplied first, then generated"
                                    >
                                        Numbers in the file are preserved when
                                        they are numeric, unused, and not
                                        already registered. The rest are
                                        allocated after sorting by last name.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="What cannot be reversed here"
                                        description="Commit is final"
                                    >
                                        Members, numbers, and audit records are
                                        written together. Re-upload the workbook
                                        to try again if a row is wrong.
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
                                        Source worksheet order. Errors block
                                        processing; warnings require review.
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
                                            Full name
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Email
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Phone
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            State
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Registered
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Grade
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
                                                    row.allocated_membership_number ||
                                                    '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.full_name || '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.email || '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.phone || '—'}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.state || '—'}
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatRegistrationDate(
                                                    row.registration_date,
                                                )}
                                            </td>
                                            <td className="px-5 py-3">
                                                {row.grade || '—'}
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
                                aria-label="Membership batch pages"
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
                title="Commit this registration batch?"
                description="Members and membership numbers will be written to the register. This cannot be undone from this screen."
                confirmLabel="Process batch"
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
                            <AlertCircle className="mt-0.5 size-4 shrink-0 text-brass" />
                            Supplied numbers are retained. Rows without one
                            receive the next number after last-name sorting.
                        </p>
                    </div>
                }
            />
        </>
    );
}
