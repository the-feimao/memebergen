import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BadgeCheck, Layers3, ScrollText } from 'lucide-react';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Button } from '@/components/ui/button';
import { index as membersIndex } from '@/routes/members';

type MembershipHistoryEntry = {
    id: number;
    change_type: string;
    changed_at: string | null;
    previous_grade: {
        id: number;
        name: string;
    } | null;
    new_grade: {
        id: number;
        name: string;
    };
    changed_by: {
        id: number;
        name: string;
    } | null;
};

type MembershipUpgradeRecord = {
    id: number;
    previous_grade: { id: number; name: string } | null;
    new_grade: { id: number; name: string } | null;
    upgrade_type: { id: number; name: string } | null;
    batch: { id: number; batch_reference: string } | null;
    processed_by: { id: number; name: string } | null;
    effective_date: string | null;
    created_at: string | null;
};

type Member = {
    id: number;
    membership_number: string;
    full_name: string;
    email: string | null;
    phone: string | null;
    state: string | null;
    registration_date: string | null;
    grade: {
        id: number;
        name: string;
    };
    history: MembershipHistoryEntry[];
    upgrades: MembershipUpgradeRecord[];
};

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}

function formatDateTime(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function MemberDetails({ member }: { member: Member }) {
    return (
        <>
            <Head title={member.full_name} />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Member record"
                    title={member.full_name}
                    description={`Membership number ${member.membership_number} · registered ${formatDate(member.registration_date)}`}
                    actions={
                        <Button asChild variant="outline">
                            <Link href={membersIndex()}>
                                <ArrowLeft className="size-4" />
                                Back to register
                            </Link>
                        </Button>
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Membership number',
                            value: member.membership_number,
                            hint: 'Stable identifier',
                        },
                        {
                            label: 'Current grade',
                            value: member.grade.name,
                            tone: 'verified',
                            hint: 'As held in the register',
                        },
                        {
                            label: 'Registered',
                            value: formatDate(member.registration_date),
                            hint: 'Original registration date',
                        },
                        {
                            label: 'Upgrade records',
                            value: member.upgrades.length,
                            tone:
                                member.upgrades.length > 0
                                    ? 'review'
                                    : 'neutral',
                            hint: 'Processed grade changes',
                        },
                    ]}
                />

                <section
                    aria-labelledby="member-details-heading"
                    className="overflow-hidden rounded-xl border border-line bg-card"
                >
                    <div className="flex items-center gap-3 border-b border-line bg-ink px-5 py-4 text-white">
                        <BadgeCheck className="size-5 text-brass" />
                        <div>
                            <h2
                                id="member-details-heading"
                                className="font-medium text-white"
                            >
                                Current membership
                            </h2>
                            <p className="mt-0.5 text-xs text-white/70">
                                Contact details held with this record.
                            </p>
                        </div>
                    </div>

                    <dl className="grid gap-px bg-line sm:grid-cols-2 lg:grid-cols-3">
                        <div className="bg-card p-5">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Membership number
                            </dt>
                            <dd className="mt-2 font-display text-2xl text-ink tabular-nums">
                                {member.membership_number}
                            </dd>
                        </div>
                        <div className="bg-card p-5">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Current grade
                            </dt>
                            <dd className="mt-2 font-medium text-ink">
                                {member.grade.name}
                            </dd>
                        </div>
                        <div className="bg-card p-5">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Registration date
                            </dt>
                            <dd className="mt-2 font-medium text-ink">
                                {formatDate(member.registration_date)}
                            </dd>
                        </div>
                        <div className="bg-card p-5">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Email
                            </dt>
                            <dd className="mt-2 font-medium break-all text-ink">
                                {member.email || 'Not recorded'}
                            </dd>
                        </div>
                        <div className="bg-card p-5">
                            <dt className="text-xs font-medium text-muted-foreground">
                                Phone
                            </dt>
                            <dd className="mt-2 font-medium text-ink">
                                {member.phone || 'Not recorded'}
                            </dd>
                        </div>
                        <div className="bg-card p-5">
                            <dt className="text-xs font-medium text-muted-foreground">
                                State
                            </dt>
                            <dd className="mt-2 font-medium text-ink">
                                {member.state || 'Not recorded'}
                            </dd>
                        </div>
                    </dl>
                </section>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section
                        aria-labelledby="membership-upgrades-heading"
                        className="overflow-hidden rounded-xl border border-line bg-card lg:col-span-2"
                    >
                        <div className="flex items-center gap-3 border-b border-line bg-muted/30 px-5 py-4">
                            <Layers3 className="size-5 text-verdigris" />
                            <div>
                                <h2
                                    id="membership-upgrades-heading"
                                    className="font-medium text-ink"
                                >
                                    Upgrade records
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Grade changes processed for this member.
                                </p>
                            </div>
                        </div>

                        {member.upgrades.length === 0 ? (
                            <p className="p-8 text-center text-sm text-muted-foreground">
                                No upgrade records have been recorded.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="bg-muted/40 text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-3 font-medium">
                                                Type
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Previous grade
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                New grade
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Effective date
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Batch
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Processed
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {member.upgrades.map((upgrade) => (
                                            <tr
                                                key={upgrade.id}
                                                className="border-t border-line align-top"
                                            >
                                                <td className="px-5 py-3 font-medium text-ink">
                                                    {upgrade.upgrade_type
                                                        ?.name || '—'}
                                                </td>
                                                <td className="px-5 py-3">
                                                    {upgrade.previous_grade
                                                        ?.name || '—'}
                                                </td>
                                                <td className="px-5 py-3 font-medium text-verdigris">
                                                    {upgrade.new_grade?.name ||
                                                        '—'}
                                                </td>
                                                <td className="px-5 py-3 whitespace-nowrap">
                                                    {formatDate(
                                                        upgrade.effective_date,
                                                    )}
                                                </td>
                                                <td className="px-5 py-3">
                                                    {upgrade.batch
                                                        ?.batch_reference ||
                                                        '—'}
                                                </td>
                                                <td className="px-5 py-3 text-muted-foreground">
                                                    {formatDateTime(
                                                        upgrade.created_at,
                                                    )}
                                                    {upgrade.processed_by
                                                        ? ` · ${upgrade.processed_by.name}`
                                                        : ''}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>

                    <aside className="rounded-xl border border-line bg-card">
                        <div className="border-b border-line px-5 py-4">
                            <h2 className="font-medium text-ink">
                                Record notes
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                How this record is maintained.
                            </p>
                        </div>
                        <div className="px-5">
                            <Accordion>
                                <AccordionItem
                                    title="Number retention"
                                    description="Never changed by an upgrade"
                                    defaultOpen
                                >
                                    The membership number shown here is the
                                    number allocated or supplied when the member
                                    joined. Upgrade batches match on it and
                                    never rewrite it.
                                </AccordionItem>
                                <AccordionItem
                                    title="Grade history"
                                    description="Each change is recorded"
                                >
                                    Registration and grade changes create
                                    history entries with the change type, time,
                                    and the user who processed the batch.
                                </AccordionItem>
                                <AccordionItem
                                    title="Upgrade records"
                                    description="Linked to source batches"
                                >
                                    Each upgrade record keeps the batch
                                    reference and upgrade type, so a promotion
                                    can be traced back to the approved workbook.
                                </AccordionItem>
                            </Accordion>
                        </div>
                    </aside>
                </div>

                <section
                    aria-labelledby="membership-history-heading"
                    className="overflow-hidden rounded-xl border border-line bg-card"
                >
                    <div className="flex items-center gap-3 border-b border-line bg-muted/30 px-5 py-4">
                        <ScrollText className="size-5 text-brass" />
                        <div>
                            <h2
                                id="membership-history-heading"
                                className="font-medium text-ink"
                            >
                                Membership history
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Chronological audit of this record.
                            </p>
                        </div>
                    </div>

                    {member.history.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No membership history has been recorded.
                        </p>
                    ) : (
                        <ol className="space-y-4 p-5">
                            {member.history.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="relative border-l-2 border-line pl-5"
                                >
                                    <span className="absolute top-1.5 -left-[5px] size-2 rounded-full bg-verdigris" />
                                    <p className="font-medium text-ink">
                                        {entry.previous_grade
                                            ? `${entry.previous_grade.name} to ${entry.new_grade.name}`
                                            : `Registered as ${entry.new_grade.name}`}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground capitalize">
                                        {entry.change_type.replaceAll('_', ' ')}{' '}
                                        · {formatDateTime(entry.changed_at)}
                                        {entry.changed_by
                                            ? ` · recorded by ${entry.changed_by.name}`
                                            : ''}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </section>
            </div>
        </>
    );
}

MemberDetails.layout = {
    breadcrumbs: [
        {
            title: 'Membership Register',
            href: membersIndex(),
        },
        {
            title: 'Member details',
            href: '#',
        },
    ],
};
