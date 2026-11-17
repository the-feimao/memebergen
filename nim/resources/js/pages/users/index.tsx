import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { KeyRound, Search, UserCog } from 'lucide-react';
import InputError from '@/components/input-error';
import MetricStrip from '@/components/metric-strip';
import PageIntro from '@/components/page-intro';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ManagedUser = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_active: boolean;
    last_login_at: string | null;
    created_at: string | null;
};

type PageProps = {
    users: {
        data: ManagedUser[];
        links: {
            url: string | null;
            label: string;
            active: boolean;
        }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    roles: string[];
    filters: {
        search: string;
    };
    stats: {
        total: number;
        active: number;
        admins: number;
    };
    currentUserId: number;
};

type SharedProps = {
    flash?: {
        success?: string | null;
        error?: string | null;
    };
};

function formatDate(value: string | null): string {
    if (value === null) {
        return 'Never';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function EditRow({
    user,
    roles,
    isCurrentUser,
}: {
    user: ManagedUser;
    roles: string[];
    isCurrentUser: boolean;
}) {
    const form = useForm({
        name: user.name,
        email: user.email,
        role: user.role ?? roles[0],
        is_active: user.is_active,
    });
    const resetForm = useForm({});

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(`/users/${user.id}`, {
            preserveScroll: true,
        });
    }

    function sendReset(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        resetForm.post(`/users/${user.id}/password-reset`, {
            preserveScroll: true,
        });
    }

    return (
        <div className="grid gap-4 rounded-lg border border-line bg-card p-4 text-ink">
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="grid gap-1.5">
                        <Label htmlFor={`user-name-${user.id}`}>
                            Full name
                        </Label>
                        <Input
                            id={`user-name-${user.id}`}
                            value={form.data.name}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.name} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`user-email-${user.id}`}>
                            Email address
                        </Label>
                        <Input
                            id={`user-email-${user.id}`}
                            type="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                        />
                        <InputError message={form.errors.email} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`user-role-${user.id}`}>Role</Label>
                        <select
                            id={`user-role-${user.id}`}
                            value={form.data.role}
                            onChange={(event) =>
                                form.setData('role', event.target.value)
                            }
                            disabled={isCurrentUser}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {roles.map((role) => (
                                <option key={role} value={role}>
                                    {role}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.role} />
                    </div>
                    <div className="grid gap-1.5">
                        <Label htmlFor={`user-status-${user.id}`}>
                            Account status
                        </Label>
                        <label className="flex h-9 items-center gap-2 rounded-md border border-input bg-background px-3 text-sm">
                            <input
                                id={`user-status-${user.id}`}
                                type="checkbox"
                                checked={form.data.is_active}
                                onChange={(event) =>
                                    form.setData(
                                        'is_active',
                                        event.target.checked,
                                    )
                                }
                                disabled={isCurrentUser}
                                className="size-4"
                            />
                            {form.data.is_active ? 'Active' : 'Deactivated'}
                        </label>
                        <InputError message={form.errors.is_active} />
                    </div>
                </div>

                {isCurrentUser && (
                    <p className="text-xs text-muted-foreground">
                        Your own role and status are protected so at least one
                        active Super Admin remains available.
                    </p>
                )}

                <div>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving…' : 'Save changes'}
                    </Button>
                </div>
            </form>

            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                <div>
                    <p className="text-sm font-medium text-ink">
                        Password reset
                    </p>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Sends a secure reset link to the account email. No
                        stored password is ever displayed.
                    </p>
                </div>
                <form onSubmit={sendReset}>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={resetForm.processing}
                    >
                        <KeyRound className="size-4" />
                        {resetForm.processing ? 'Sending…' : 'Send reset link'}
                    </Button>
                </form>
            </div>
        </div>
    );
}

export default function Users({
    users,
    roles,
    filters,
    stats,
    currentUserId,
}: PageProps) {
    const { flash } = usePage<SharedProps>().props;
    const createForm = useForm({
        name: '',
        email: '',
        role: roles[0] ?? 'Editor',
        is_active: true as boolean,
        password: '',
        password_confirmation: '',
    });

    function submitCreate(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        createForm.post('/users', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    }

    return (
        <>
            <Head title="User Management" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Administration"
                    title="User management"
                    description="Create and maintain registry accounts, assign roles, and control access. Only Super Admins can manage users."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-line text-muted-foreground"
                        >
                            Super Admin only
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
                {flash?.error && (
                    <p
                        role="alert"
                        className="rounded-xl border border-brass/40 bg-brass/10 p-4 text-sm text-brass"
                    >
                        {flash.error}
                    </p>
                )}

                <MetricStrip
                    metrics={[
                        {
                            label: 'Accounts',
                            value: stats.total,
                            hint: 'All registered users',
                        },
                        {
                            label: 'Active',
                            value: stats.active,
                            tone: 'verified',
                            hint: 'Allowed to sign in',
                        },
                        {
                            label: 'Super Admins',
                            value: stats.admins,
                            tone: 'review',
                            hint: 'Accounts with full access',
                        },
                        {
                            label: 'View state',
                            value: filters.search ? 'Filtered' : 'All users',
                            hint: filters.search
                                ? 'Clear the search to reset'
                                : 'Ordered by name',
                        },
                    ]}
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 overflow-hidden lg:col-span-1">
                        <CardHeader className="border-b border-line bg-muted/30 py-5">
                            <CardTitle className="flex items-center gap-2 font-display text-xl font-normal">
                                <UserCog className="size-5 text-verdigris" />
                                Add a user
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="py-6">
                            <form
                                onSubmit={submitCreate}
                                className="grid gap-4"
                            >
                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-user-name">
                                        Full name
                                    </Label>
                                    <Input
                                        id="new-user-name"
                                        value={createForm.data.name}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="e.g. NIM Registrar"
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.name}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-user-email">
                                        Email address
                                    </Label>
                                    <Input
                                        id="new-user-email"
                                        type="email"
                                        value={createForm.data.email}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.email}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-user-role">Role</Label>
                                    <select
                                        id="new-user-role"
                                        value={createForm.data.role}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'role',
                                                event.target.value,
                                            )
                                        }
                                        className="h-9 rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        {roles.map((role) => (
                                            <option key={role} value={role}>
                                                {role}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError
                                        message={createForm.errors.role}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-user-password">
                                        Temporary password
                                    </Label>
                                    <Input
                                        id="new-user-password"
                                        type="password"
                                        value={createForm.data.password}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.password}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-user-password-confirmation">
                                        Confirm temporary password
                                    </Label>
                                    <Input
                                        id="new-user-password-confirmation"
                                        type="password"
                                        value={
                                            createForm.data
                                                .password_confirmation
                                        }
                                        onChange={(event) =>
                                            createForm.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        autoComplete="new-password"
                                        required
                                    />
                                </div>

                                <label className="flex items-center gap-2 text-sm text-ink">
                                    <input
                                        type="checkbox"
                                        checked={createForm.data.is_active}
                                        onChange={(event) =>
                                            createForm.setData(
                                                'is_active',
                                                event.target.checked,
                                            )
                                        }
                                    />
                                    Active immediately
                                </label>

                                <Button
                                    type="submit"
                                    disabled={createForm.processing}
                                >
                                    <UserCog className="size-4" />
                                    {createForm.processing
                                        ? 'Creating…'
                                        : 'Create user'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <section className="overflow-hidden rounded-xl border border-line bg-card lg:col-span-2">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-ink px-5 py-4 text-white">
                            <div>
                                <h2 className="font-medium text-white">
                                    Managed accounts
                                </h2>
                                <p className="mt-0.5 text-xs text-white/70">
                                    Expand an account to edit it or send a
                                    password reset link.
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                className="border-white/30 text-white/80"
                            >
                                {users.total} accounts
                            </Badge>
                        </div>

                        <div className="border-b border-line bg-muted/20 p-4">
                            <Form
                                action="/users"
                                method="get"
                                className="flex flex-wrap items-end gap-3"
                            >
                                <div className="grid min-w-56 flex-1 gap-2">
                                    <label
                                        htmlFor="user-search"
                                        className="text-sm font-medium text-ink"
                                    >
                                        Search users
                                    </label>
                                    <Input
                                        id="user-search"
                                        name="search"
                                        defaultValue={filters.search}
                                        placeholder="Name, email, or role"
                                    />
                                </div>
                                <Button type="submit">
                                    <Search className="size-4" />
                                    Search
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href="/users">Clear</Link>
                                </Button>
                            </Form>
                        </div>

                        <div className="px-5">
                            {users.data.length === 0 ? (
                                <p className="p-8 text-center text-sm text-muted-foreground">
                                    {filters.search
                                        ? 'No accounts match that search.'
                                        : 'No user accounts have been created yet.'}
                                </p>
                            ) : (
                                <Accordion>
                                    {users.data.map((user) => (
                                        <AccordionItem
                                            key={user.id}
                                            title={user.name}
                                            description={`${user.email} · expand to manage`}
                                        >
                                            <div className="grid gap-4">
                                                <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                                    <Badge
                                                        variant={
                                                            user.role ===
                                                            'Super Admin'
                                                                ? 'secondary'
                                                                : 'outline'
                                                        }
                                                        className={
                                                            user.role ===
                                                            'Super Admin'
                                                                ? 'border-verdigris/40 text-verdigris'
                                                                : 'border-line'
                                                        }
                                                    >
                                                        {user.role ?? 'No role'}
                                                    </Badge>
                                                    <Badge
                                                        variant={
                                                            user.is_active
                                                                ? 'secondary'
                                                                : 'destructive'
                                                        }
                                                    >
                                                        {user.is_active
                                                            ? 'Active'
                                                            : 'Deactivated'}
                                                    </Badge>
                                                    {user.id ===
                                                        currentUserId && (
                                                        <Badge
                                                            variant="outline"
                                                            className="border-brass/40 text-brass"
                                                        >
                                                            Your account
                                                        </Badge>
                                                    )}
                                                    <span>
                                                        Last login:{' '}
                                                        {formatDate(
                                                            user.last_login_at,
                                                        )}
                                                    </span>
                                                    <span>
                                                        Created:{' '}
                                                        {formatDate(
                                                            user.created_at,
                                                        )}
                                                    </span>
                                                </div>

                                                <EditRow
                                                    user={user}
                                                    roles={roles}
                                                    isCurrentUser={
                                                        user.id ===
                                                        currentUserId
                                                    }
                                                />
                                            </div>
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            )}
                        </div>

                        {users.last_page > 1 && (
                            <nav
                                aria-label="User pages"
                                className="flex flex-wrap items-center justify-center gap-2 border-t border-line p-4"
                            >
                                {users.links.map((link) =>
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
            </div>
        </>
    );
}

Users.layout = {
    breadcrumbs: [{ title: 'User Management', href: '/users' }],
};
