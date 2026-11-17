import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Download, FileSpreadsheet, UploadCloud } from 'lucide-react';
import InputError from '@/components/input-error';
import PageIntro from '@/components/page-intro';
import ProcessSteps from '@/components/process-steps';
import StatusPill from '@/components/status-pill';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    create as createMembershipBatch,
    show as showMembershipBatch,
    store as storeMembershipBatch,
    template as membershipBatchTemplate,
} from '@/routes/membership-batches';

type RecentBatch = {
    batch_reference: string;
    status: string;
    total_rows: number;
    valid_rows: number;
    failed_rows: number;
    uploaded_at: string | null;
};

type PageProps = {
    maxFileSizeKilobytes: number;
    maxRows: number;
    recentBatches: RecentBatch[];
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

export default function CreateMembershipBatch({
    maxFileSizeKilobytes,
    maxRows,
    recentBatches,
}: PageProps) {
    const form = useForm<{ file: File | null }>({ file: null });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post(storeMembershipBatch.url(), { forceFormData: true });
    }

    return (
        <>
            <Head title="New Membership Upload" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="New registration"
                    title="Bring new members into the register"
                    description="Upload a workbook, inspect the validation preview, and only then commit numbers and member records."
                />

                <ProcessSteps
                    steps={[
                        {
                            title: 'Download',
                            description:
                                'Start from the approved workbook template.',
                            state: 'complete',
                        },
                        {
                            title: 'Upload & validate',
                            description:
                                'Rows are checked without creating members.',
                            state: 'current',
                        },
                        {
                            title: 'Review & commit',
                            description:
                                'Confirm the preview to write the register.',
                            state: 'upcoming',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 overflow-hidden lg:col-span-2">
                        <CardHeader className="border-b border-line bg-muted/30 py-5">
                            <CardTitle className="flex items-center gap-2 font-display text-xl font-normal">
                                <UploadCloud className="size-5 text-verdigris" />
                                Prepare the upload
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6 py-6 sm:grid-cols-2">
                            <div className="space-y-4 border-line sm:border-r sm:pr-6">
                                <div className="space-y-1">
                                    <p className="font-medium text-ink">
                                        1. Get the template
                                    </p>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        One worksheet, flexible heading
                                        variants, and the columns the register
                                        understands.
                                    </p>
                                </div>
                                <Button asChild variant="outline">
                                    <a href={membershipBatchTemplate.url()}>
                                        <Download className="size-4" />
                                        Download template
                                    </a>
                                </Button>
                                <div className="rounded-lg border border-line bg-paper/70 p-4 text-xs leading-relaxed text-muted-foreground">
                                    Maximum file size:{' '}
                                    {(maxFileSizeKilobytes / 1024).toFixed(
                                        0,
                                    )}{' '}
                                    MB. Maximum rows: {maxRows.toLocaleString()}
                                    .
                                </div>
                            </div>

                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-1">
                                    <p className="font-medium text-ink">
                                        2. Choose your file
                                    </p>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        .xlsx or .csv. The preview opens as soon
                                        as validation finishes.
                                    </p>
                                </div>
                                <label
                                    htmlFor="membership-workbook"
                                    className="text-sm font-medium text-ink"
                                >
                                    Membership file
                                </label>
                                <input
                                    id="membership-workbook"
                                    name="file"
                                    type="file"
                                    accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv"
                                    required
                                    onChange={(event) => {
                                        form.setData(
                                            'file',
                                            event.currentTarget.files?.[0] ??
                                                null,
                                        );
                                        form.clearErrors('file');
                                    }}
                                    className="block w-full rounded-md border border-input bg-background px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white"
                                />
                                <InputError message={form.errors.file} />
                                {form.progress && (
                                    <p
                                        role="status"
                                        className="text-sm text-muted-foreground"
                                    >
                                        Validating: {form.progress.percentage}%
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        form.data.file === null
                                    }
                                    className="w-full sm:w-auto"
                                >
                                    {form.processing
                                        ? 'Uploading and validating…'
                                        : 'Upload and preview'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <aside className="space-y-4">
                        <section className="rounded-xl border border-line bg-card">
                            <div className="border-b border-line px-5 py-4">
                                <h2 className="font-medium text-ink">
                                    File rules
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Expand a topic while preparing the workbook.
                                </p>
                            </div>
                            <div className="px-5">
                                <Accordion>
                                    <AccordionItem
                                        title="Required columns"
                                        description="Name and grade"
                                        defaultOpen
                                    >
                                        Use either “Full Name” or split
                                        “lastname”, “othername”, and “firstname”
                                        columns. A grade column is always
                                        required.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Membership numbers"
                                        description="Optional in the file"
                                    >
                                        Supplied numbers must be numeric-only,
                                        unique in the file, and unused in the
                                        register. Leave the column blank to
                                        allocate numbers after last-name
                                        sorting.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Accepted grade values"
                                        description="Aliases are resolved"
                                    >
                                        Member_research → Member,
                                        member_associate and member_exams →
                                        Associate, and member_graduate,
                                        member_grad, or member_nysc → Graduate.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Optional details"
                                        description="Kept with the member"
                                    >
                                        Email, phone, state, dob, and address
                                        columns are stored when supplied. Email
                                        must be unique.
                                    </AccordionItem>
                                </Accordion>
                            </div>
                        </section>
                    </aside>
                </div>

                <section className="overflow-hidden rounded-xl border border-line bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                        <div>
                            <h2 className="font-medium text-ink">
                                Recent uploads
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Previously uploaded membership workbooks.
                            </p>
                        </div>
                        <FileSpreadsheet className="size-5 text-brass" />
                    </div>

                    {recentBatches.length === 0 ? (
                        <div className="p-8 text-center text-sm text-muted-foreground">
                            No membership workbooks have been uploaded yet.
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Batch
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Status
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Rows
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Uploaded
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentBatches.map((batch) => (
                                        <tr
                                            key={batch.batch_reference}
                                            className="border-t border-line"
                                        >
                                            <td className="px-5 py-3 font-medium">
                                                <Link
                                                    href={showMembershipBatch(
                                                        batch.batch_reference,
                                                    )}
                                                    className="text-ink underline decoration-brass/60 underline-offset-4 hover:text-verdigris"
                                                >
                                                    {batch.batch_reference}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3">
                                                <StatusPill
                                                    status={batch.status}
                                                />
                                            </td>
                                            <td className="px-5 py-3 tabular-nums">
                                                {batch.total_rows} total ·{' '}
                                                {batch.valid_rows} valid ·{' '}
                                                {batch.failed_rows} invalid
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDateTime(
                                                    batch.uploaded_at,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

CreateMembershipBatch.layout = {
    breadcrumbs: [
        {
            title: 'New Membership Upload',
            href: createMembershipBatch(),
        },
    ],
};
