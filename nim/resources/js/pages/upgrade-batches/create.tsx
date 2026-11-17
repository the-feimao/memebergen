import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ArrowRight, Download, Layers3 } from 'lucide-react';
import InputError from '@/components/input-error';
import PageIntro from '@/components/page-intro';
import ProcessSteps from '@/components/process-steps';
import StatusPill from '@/components/status-pill';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type UpgradeType = {
    id: number;
    name: string;
    description: string | null;
};

type RecentBatch = {
    batch_reference: string;
    status: string;
    upgrade_type: string | null;
    total_rows: number;
    valid_rows: number;
    failed_rows: number;
    uploaded_at: string | null;
};

type PageProps = {
    maxFileSizeKilobytes: number;
    maxRows: number;
    upgradeTypes: UpgradeType[];
    recentBatches: RecentBatch[];
};

const gradePath = ['Graduate', 'Associate', 'Member', 'Fellow'];

function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function CreateUpgradeBatch({
    maxFileSizeKilobytes,
    maxRows,
    upgradeTypes,
    recentBatches,
}: PageProps) {
    const form = useForm<{ upgrade_type_id: string; file: File | null }>({
        upgrade_type_id: '',
        file: null,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/upgrade-batches', { forceFormData: true });
    }

    return (
        <>
            <Head title="Membership Upgrades" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Membership upgrade"
                    title="Move members one grade at a time"
                    description="The grade in the file is the member's current grade. The intended grade is always the next step, and the membership number never changes."
                />

                <section className="overflow-hidden rounded-xl border border-line bg-card">
                    <div className="border-b border-line bg-ink px-5 py-4">
                        <div className="flex flex-wrap items-center gap-3 text-white">
                            <span className="text-sm text-white/70">
                                Promotion path
                            </span>
                            <ol className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                {gradePath.map((grade, index) => (
                                    <li
                                        key={grade}
                                        className="flex items-center gap-2"
                                    >
                                        <span className="rounded-full border border-white/30 px-3 py-1">
                                            {grade}
                                        </span>
                                        {index < gradePath.length - 1 && (
                                            <ArrowRight className="size-3.5 text-brass" />
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>
                </section>

                <ProcessSteps
                    steps={[
                        {
                            title: 'Choose type & file',
                            description:
                                'Select the active programme and upload the workbook.',
                            state: 'complete',
                        },
                        {
                            title: 'Verify details',
                            description:
                                'Number, grade, and key information are checked.',
                            state: 'current',
                        },
                        {
                            title: 'Commit upgrades',
                            description:
                                'Grades and history update; numbers stay put.',
                            state: 'upcoming',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 overflow-hidden lg:col-span-2">
                        <CardHeader className="border-b border-line bg-muted/30 py-5">
                            <CardTitle className="flex items-center gap-2 font-display text-xl font-normal">
                                <Layers3 className="size-5 text-verdigris" />
                                Prepare the upgrade batch
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-6 py-6 sm:grid-cols-2">
                            <div className="space-y-4 border-line sm:border-r sm:pr-6">
                                <div className="space-y-1">
                                    <p className="font-medium text-ink">
                                        1. Get the template
                                    </p>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        Include membership number and grade.
                                        Name, email, phone, and dob help verify
                                        the record.
                                    </p>
                                </div>
                                <Button asChild variant="outline">
                                    <a href="/upgrade-batches/template">
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
                                {upgradeTypes.length === 0 && (
                                    <p
                                        role="alert"
                                        className="rounded-lg border border-brass/50 bg-brass/10 p-4 text-sm text-ink"
                                    >
                                        No active upgrade types are configured.
                                        Ask a Super Admin to add one before
                                        uploading.
                                    </p>
                                )}
                            </div>

                            <form onSubmit={submit} className="space-y-4">
                                <div className="space-y-1">
                                    <p className="font-medium text-ink">
                                        2. Choose type and file
                                    </p>
                                    <p className="text-sm leading-relaxed text-muted-foreground">
                                        The upgrade type is recorded with every
                                        successful promotion.
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <label
                                        htmlFor="upgrade-type"
                                        className="text-sm font-medium text-ink"
                                    >
                                        Upgrade type
                                    </label>
                                    <select
                                        id="upgrade-type"
                                        name="upgrade_type_id"
                                        required
                                        value={form.data.upgrade_type_id}
                                        onChange={(event) => {
                                            form.setData(
                                                'upgrade_type_id',
                                                event.currentTarget.value,
                                            );
                                            form.clearErrors('upgrade_type_id');
                                        }}
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Select an active upgrade type
                                        </option>
                                        {upgradeTypes.map((type) => (
                                            <option
                                                key={type.id}
                                                value={type.id}
                                            >
                                                {type.name}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={form.errors.upgrade_type_id}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <label
                                        htmlFor="upgrade-workbook"
                                        className="text-sm font-medium text-ink"
                                    >
                                        Upgrade file
                                    </label>
                                    <input
                                        id="upgrade-workbook"
                                        name="file"
                                        type="file"
                                        accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain,application/csv"
                                        required
                                        onChange={(event) => {
                                            form.setData(
                                                'file',
                                                event.currentTarget
                                                    .files?.[0] ?? null,
                                            );
                                            form.clearErrors('file');
                                        }}
                                        className="block w-full rounded-md border border-input bg-background px-3 py-2.5 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-ink file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white"
                                    />
                                    <InputError message={form.errors.file} />
                                </div>

                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        form.data.file === null ||
                                        form.data.upgrade_type_id === ''
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
                                    Verification rules
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Why a row may be blocked from processing.
                                </p>
                            </div>
                            <div className="px-5">
                                <Accordion>
                                    <AccordionItem
                                        title="Required columns"
                                        description="Number and grade"
                                        defaultOpen
                                    >
                                        Without a membership number or grade,
                                        the upload does not proceed. Names may
                                        be one column or split into lastname,
                                        othername, and firstname.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Key information"
                                        description="Compared with the register"
                                    >
                                        Email, phone, and date of birth are
                                        checked when supplied. A mismatch blocks
                                        the row; a missing registered value is
                                        flagged for review.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Current vs intended grade"
                                        description="One step, never more"
                                    >
                                        The grade column is the current grade.
                                        Intended is computed as the next step.
                                        An optional intended grade column must
                                        match it exactly.
                                    </AccordionItem>
                                    <AccordionItem
                                        title="Accepted aliases"
                                        description="Common NIM values"
                                    >
                                        Member_research resolves to Member;
                                        member_associate and member_exams to
                                        Associate; member_graduate, member_grad,
                                        and member_nysc to Graduate.
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
                                Recent upgrade uploads
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Previously uploaded upgrade workbooks.
                            </p>
                        </div>
                        <Layers3 className="size-5 text-brass" />
                    </div>

                    {recentBatches.length === 0 ? (
                        <div className="p-8 text-center text-sm text-muted-foreground">
                            No upgrade workbooks have been uploaded yet.
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
                                            Type
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
                                                    href={`/upgrade-batches/${batch.batch_reference}`}
                                                    className="text-ink underline decoration-brass/60 underline-offset-4 hover:text-verdigris"
                                                >
                                                    {batch.batch_reference}
                                                </Link>
                                            </td>
                                            <td className="px-5 py-3">
                                                {batch.upgrade_type || '—'}
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

CreateUpgradeBatch.layout = {
    breadcrumbs: [
        {
            title: 'Membership Upgrades',
            href: '/upgrade-batches/create',
        },
    ],
};
