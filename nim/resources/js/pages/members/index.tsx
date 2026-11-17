import { Form, Head, Link } from '@inertiajs/react';
import {
    ArrowUpRight,
    Download,
    Search,
    SlidersHorizontal,
} from 'lucide-react';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index as membersIndex, show as showMember } from '@/routes/members';

type MemberSummary = {
    id: number;
    membership_number: string;
    full_name: string;
    registration_date: string | null;
    grade: {
        id: number;
        name: string;
    };
};

type PageProps = {
    members: {
        data: MemberSummary[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    grades: string[];
    filters: {
        search: string;
        grade: string;
        registration_from: string;
        registration_to: string;
    };
    can: {
        export: boolean;
    };
};

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Not recorded';
    }

    const [year, month, day] = value.slice(0, 10).split('-');

    return `${day}/${month}/${year}`;
}

export default function MembershipRegister({
    members,
    grades,
    filters,
    can,
}: PageProps) {
    const exportParams = new URLSearchParams();
    if (filters.search) exportParams.set('search', filters.search);
    if (filters.grade) exportParams.set('grade', filters.grade);
    if (filters.registration_from)
        exportParams.set('registration_from', filters.registration_from);
    if (filters.registration_to)
        exportParams.set('registration_to', filters.registration_to);
    const exportHref = `/members/export${exportParams.toString() ? `?${exportParams.toString()}` : ''}`;
    const hasFilters = Boolean(
        filters.search ||
        filters.grade ||
        filters.registration_from ||
        filters.registration_to,
    );

    return (
        <>
            <Head title="Membership Register" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Register"
                    title="Membership register"
                    description="Find a member by number, name, grade, or registration date. Every record opened here is read-only."
                    actions={
                        can.export ? (
                            <Button asChild variant="outline">
                                <a href={exportHref}>
                                    <Download className="size-4" />
                                    Export current view
                                </a>
                            </Button>
                        ) : undefined
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Records held',
                            value: members.total,
                            hint: 'Across the whole register',
                        },
                        {
                            label: 'On this page',
                            value: members.data.length,
                            tone: 'verified',
                            hint: `Page ${members.current_page} of ${members.last_page}`,
                        },
                        {
                            label: 'Grades available',
                            value: grades.length,
                            hint: 'Configured membership grades',
                        },
                        {
                            label: 'View state',
                            value: hasFilters ? 'Filtered' : 'All records',
                            tone: hasFilters ? 'review' : 'neutral',
                            hint: hasFilters
                                ? 'Clear filters to reset'
                                : 'No filters applied',
                        },
                    ]}
                />

                <Form
                    action={membersIndex.url()}
                    method="get"
                    className="grid gap-4 rounded-xl border border-line bg-card p-5 md:grid-cols-2 xl:grid-cols-5"
                >
                    <div className="grid gap-2 xl:col-span-2">
                        <label
                            htmlFor="search"
                            className="text-sm font-medium text-ink"
                        >
                            Name or membership number
                        </label>
                        <Input
                            id="search"
                            name="search"
                            defaultValue={filters.search}
                            placeholder="Search the register"
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

                    <div className="grid gap-2">
                        <label
                            htmlFor="registration_from"
                            className="text-sm font-medium text-ink"
                        >
                            Registered from
                        </label>
                        <Input
                            id="registration_from"
                            name="registration_from"
                            type="date"
                            defaultValue={filters.registration_from}
                        />
                    </div>

                    <div className="grid gap-2">
                        <label
                            htmlFor="registration_to"
                            className="text-sm font-medium text-ink"
                        >
                            Registered to
                        </label>
                        <Input
                            id="registration_to"
                            name="registration_to"
                            type="date"
                            defaultValue={filters.registration_to}
                        />
                    </div>

                    <div className="flex flex-wrap items-center gap-2 border-t border-line pt-4 xl:col-span-5">
                        <Button type="submit">
                            <Search className="size-4" />
                            Apply filters
                        </Button>
                        <Button asChild variant="outline">
                            <Link href={membersIndex()}>Clear filters</Link>
                        </Button>
                        <span className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                            <SlidersHorizontal className="size-3.5" />
                            Filters combine together.
                        </span>
                    </div>
                </Form>

                <section className="overflow-hidden rounded-xl border border-line bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-muted/30 px-5 py-4">
                        <div>
                            <h2 className="font-medium text-ink">
                                Register entries
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Membership number is the stable identifier and
                                never changes on upgrade.
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Read only
                        </Badge>
                    </div>

                    {members.data.length === 0 ? (
                        <div className="p-8 text-center">
                            <h3 className="font-medium text-ink">
                                {members.total === 0
                                    ? 'No member records yet'
                                    : 'No matching members'}
                            </h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {members.total === 0
                                    ? 'The register will show records after the first approved import.'
                                    : 'Try adjusting the filters and search again.'}
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-sm">
                                <thead className="bg-muted/40 text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Membership number
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Full name
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Grade
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Registration date
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            <span className="sr-only">
                                                Details
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {members.data.map((member) => (
                                        <tr
                                            key={member.id}
                                            className="border-t border-line transition-colors hover:bg-accent/30"
                                        >
                                            <td className="px-5 py-3 font-display text-lg text-ink tabular-nums">
                                                {member.membership_number}
                                            </td>
                                            <td className="px-5 py-3 text-ink">
                                                {member.full_name}
                                            </td>
                                            <td className="px-5 py-3">
                                                <span className="inline-flex rounded-full border border-line bg-paper/70 px-2.5 py-1 text-xs text-ink">
                                                    {member.grade.name}
                                                </span>
                                            </td>
                                            <td className="px-5 py-3 whitespace-nowrap text-muted-foreground">
                                                {formatDate(
                                                    member.registration_date,
                                                )}
                                            </td>
                                            <td className="px-5 py-3 text-right">
                                                <Button
                                                    asChild
                                                    variant="link"
                                                    size="sm"
                                                >
                                                    <Link
                                                        href={showMember(
                                                            member.id,
                                                        )}
                                                    >
                                                        View record
                                                        <ArrowUpRight className="size-3.5" />
                                                    </Link>
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}

                    {members.last_page > 1 && (
                        <nav
                            aria-label="Membership register pages"
                            className="flex flex-wrap items-center justify-center gap-2 border-t border-line p-4"
                        >
                            {members.links.map((link) =>
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

MembershipRegister.layout = {
    breadcrumbs: [
        {
            title: 'Membership Register',
            href: membersIndex(),
        },
    ],
};
