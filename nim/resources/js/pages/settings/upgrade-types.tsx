import { Head, useForm, usePage } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { Plus, ShieldCheck } from 'lucide-react';
import InputError from '@/components/input-error';
import PageIntro from '@/components/page-intro';
import { Accordion, AccordionItem } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type UpgradeType = {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
};

type PageProps = {
    upgradeTypes: UpgradeType[];
};

type SharedProps = {
    flash?: { success?: string | null };
};

function EditRow({ type }: { type: UpgradeType }) {
    const form = useForm({
        name: type.name,
        description: type.description ?? '',
        is_active: type.is_active,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.patch(`/settings/upgrade-types/${type.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="grid gap-4 rounded-lg border border-line bg-card p-4 text-ink"
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-medium text-ink">Edit {type.name}</p>
                <Badge
                    variant={form.data.is_active ? 'secondary' : 'destructive'}
                >
                    {form.data.is_active ? 'Active' : 'Inactive'}
                </Badge>
            </div>

            <div className="grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor={`name-${type.id}`}>Name</Label>
                    <Input
                        id={`name-${type.id}`}
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                        required
                    />
                    <InputError message={form.errors.name} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor={`desc-${type.id}`}>Description</Label>
                    <Input
                        id={`desc-${type.id}`}
                        value={form.data.description}
                        onChange={(e) =>
                            form.setData('description', e.target.value)
                        }
                    />
                    <InputError message={form.errors.description} />
                </div>
            </div>

            <label className="flex items-center gap-2 text-sm text-ink">
                <input
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(e) =>
                        form.setData('is_active', e.target.checked)
                    }
                />
                Active (available for upgrade batches)
            </label>

            <div>
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Saving…' : 'Save changes'}
                </Button>
            </div>
        </form>
    );
}

export default function UpgradeTypesSettings({ upgradeTypes }: PageProps) {
    const { flash } = usePage<SharedProps>().props;
    const createForm = useForm({
        name: '',
        description: '',
        is_active: true as boolean,
    });

    function submitCreate(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        createForm.post('/settings/upgrade-types', {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    }

    return (
        <>
            <Head title="Upgrade Types" />

            <div className="space-y-6 p-4 lg:p-6">
                <PageIntro
                    label="Settings"
                    title="Upgrade types"
                    description="Maintain the approved programme list used when recording grade upgrades. Only active types appear on the upgrade upload screen."
                    actions={
                        <Badge
                            variant="outline"
                            className="border-verdigris/40 text-verdigris"
                        >
                            Configuration
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
                    <Card className="gap-0 overflow-hidden lg:col-span-1">
                        <CardHeader className="border-b border-line bg-muted/30 py-5">
                            <CardTitle className="flex items-center gap-2 font-display text-xl font-normal">
                                <Plus className="size-5 text-verdigris" />
                                Add upgrade type
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="py-6">
                            <form
                                onSubmit={submitCreate}
                                className="grid gap-4"
                            >
                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-name">Name</Label>
                                    <Input
                                        id="new-name"
                                        value={createForm.data.name}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'name',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. ITMA"
                                        required
                                    />
                                    <InputError
                                        message={createForm.errors.name}
                                    />
                                </div>

                                <div className="grid gap-1.5">
                                    <Label htmlFor="new-desc">
                                        Description (optional)
                                    </Label>
                                    <Input
                                        id="new-desc"
                                        value={createForm.data.description}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'description',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        message={createForm.errors.description}
                                    />
                                </div>

                                <label className="flex items-center gap-2 text-sm text-ink">
                                    <input
                                        type="checkbox"
                                        checked={createForm.data.is_active}
                                        onChange={(e) =>
                                            createForm.setData(
                                                'is_active',
                                                e.target.checked,
                                            )
                                        }
                                    />
                                    Active
                                </label>

                                <Button
                                    type="submit"
                                    disabled={createForm.processing}
                                >
                                    <Plus className="size-4" />
                                    {createForm.processing
                                        ? 'Creating…'
                                        : 'Create upgrade type'}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <section className="overflow-hidden rounded-xl border border-line bg-card lg:col-span-2">
                        <div className="flex items-center gap-3 border-b border-line bg-ink px-5 py-4 text-white">
                            <ShieldCheck className="size-5 text-brass" />
                            <div>
                                <h2 className="font-medium text-white">
                                    Configured upgrade types
                                </h2>
                                <p className="mt-0.5 text-xs text-white/70">
                                    Expand a type to edit it. Deactivating keeps
                                    historical references intact.
                                </p>
                            </div>
                        </div>

                        <div className="px-5">
                            {upgradeTypes.length === 0 ? (
                                <p className="p-8 text-center text-sm text-muted-foreground">
                                    No upgrade types configured yet.
                                </p>
                            ) : (
                                <Accordion>
                                    {upgradeTypes.map((type) => (
                                        <AccordionItem
                                            key={type.id}
                                            title={type.name}
                                            description={`${type.is_active ? 'Active' : 'Inactive'} · expand to edit`}
                                        >
                                            <EditRow type={type} />
                                        </AccordionItem>
                                    ))}
                                </Accordion>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

UpgradeTypesSettings.layout = {
    breadcrumbs: [{ title: 'Upgrade Types', href: '/settings/upgrade-types' }],
};
