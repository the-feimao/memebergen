import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { LockKeyhole, Settings2, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PageIntro from '@/components/page-intro';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PageProps = {
    settings: {
        max_kilobytes: number;
        max_rows: number;
        chunk_size: number;
    };
    defaults: {
        max_kilobytes: number;
        max_rows: number;
        chunk_size: number;
    };
    numbering: {
        width: number;
        expands_at: number;
        gap_fill: boolean;
    };
    upgradeRules: {
        transition: string;
        path: string;
    };
    links: {
        upgrade_types: string;
    };
};

type SharedProps = {
    flash?: {
        success?: string | null;
    };
};

export default function SystemSettings({
    settings,
    defaults,
    numbering,
    upgradeRules,
    links,
}: PageProps) {
    const { flash } = usePage<SharedProps>().props;
    const form = useForm({
        max_kilobytes: settings.max_kilobytes,
        max_rows: settings.max_rows,
        chunk_size: settings.chunk_size,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch('/settings/system', {
            preserveScroll: true,
        });
    }

    return (
        <>
            <Head title="System Settings" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Settings"
                    title="System settings"
                    description="Control import limits and review the fixed registry policies. Sensitive changes are restricted and recorded in the audit log."
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

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="gap-0 overflow-hidden lg:col-span-2">
                        <CardHeader className="border-b border-line bg-muted/30 py-5">
                            <CardTitle className="flex items-center gap-2 font-display text-xl font-normal">
                                <Settings2 className="size-5 text-verdigris" />
                                Import limits
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="py-6">
                            <form
                                onSubmit={submit}
                                className="grid gap-5 sm:grid-cols-3"
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="max_kilobytes">
                                        Maximum file size (KB)
                                    </Label>
                                    <Input
                                        id="max_kilobytes"
                                        type="number"
                                        min={1}
                                        max={102400}
                                        value={form.data.max_kilobytes}
                                        onChange={(event) =>
                                            form.setData(
                                                'max_kilobytes',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.max_kilobytes}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Environment default:{' '}
                                        {defaults.max_kilobytes.toLocaleString()}{' '}
                                        KB
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="max_rows">
                                        Maximum rows per file
                                    </Label>
                                    <Input
                                        id="max_rows"
                                        type="number"
                                        min={1}
                                        max={100000}
                                        value={form.data.max_rows}
                                        onChange={(event) =>
                                            form.setData(
                                                'max_rows',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.max_rows}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Environment default:{' '}
                                        {defaults.max_rows.toLocaleString()}{' '}
                                        rows
                                    </p>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="chunk_size">
                                        Validation chunk size
                                    </Label>
                                    <Input
                                        id="chunk_size"
                                        type="number"
                                        min={1}
                                        max={10000}
                                        value={form.data.chunk_size}
                                        onChange={(event) =>
                                            form.setData(
                                                'chunk_size',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                    <InputError
                                        message={form.errors.chunk_size}
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Environment default:{' '}
                                        {defaults.chunk_size.toLocaleString()}{' '}
                                        rows
                                    </p>
                                </div>

                                <div className="flex flex-wrap items-center gap-3 sm:col-span-3">
                                    <Button
                                        type="submit"
                                        disabled={form.processing}
                                    >
                                        {form.processing
                                            ? 'Saving…'
                                            : 'Save import limits'}
                                    </Button>
                                    <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                        <LockKeyhole className="size-3.5" />
                                        Changes apply to new uploads and are
                                        audited.
                                    </span>
                                </div>
                            </form>
                        </CardContent>
                    </Card>

                    <section className="space-y-4">
                        <Card className="gap-0 overflow-hidden">
                            <CardHeader className="border-b border-line bg-muted/30 py-5">
                                <CardTitle className="flex items-center gap-2 font-display text-xl font-normal">
                                    <ShieldCheck className="size-5 text-verdigris" />
                                    Fixed registry policies
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4 py-6 text-sm">
                                <div>
                                    <p className="font-medium text-ink">
                                        Membership numbers
                                    </p>
                                    <p className="mt-1 leading-relaxed text-muted-foreground">
                                        {numbering.width} digits by default,
                                        expanding beyond six digits only after
                                        {numbering.expands_at.toLocaleString()}{' '}
                                        is exhausted. Free numbers inside the
                                        six-digit range are filled first. This
                                        policy is derived from the register and
                                        cannot be overridden here.
                                    </p>
                                </div>
                                <div className="border-t border-line pt-4">
                                    <p className="font-medium text-ink">
                                        Grade transitions
                                    </p>
                                    <p className="mt-1 leading-relaxed text-muted-foreground">
                                        One promotion at a time:{' '}
                                        {upgradeRules.path}. The transition rule
                                        is enforced by the upgrade validator.
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="gap-0 overflow-hidden">
                            <CardHeader className="border-b border-line bg-muted/30 py-5">
                                <CardTitle className="font-display text-xl font-normal">
                                    Upgrade types
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="py-6">
                                <p className="text-sm leading-relaxed text-muted-foreground">
                                    Maintain the approved programme list used by
                                    upgrade uploads.
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-4"
                                >
                                    <Link href={links.upgrade_types}>
                                        Manage upgrade types
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </section>
                </div>
            </div>
        </>
    );
}

SystemSettings.layout = {
    breadcrumbs: [{ title: 'System Settings', href: '/settings/system' }],
};
