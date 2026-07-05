import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Courier = {
    id: number;
    name: string;
    slug: string;
    driver: string;
    is_active: boolean;
    is_default: boolean;
    position_order: number;
    api_key_set: boolean;
    secret_key_set: boolean;
};

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const DRIVER_LABELS: Record<string, string> = {
    manual: 'Manual — no API (booked by hand)',
    steadfast: 'Steadfast (API)',
};

function SecretHint({ set }: { set: boolean }) {
    return (
        <span className="text-xs text-muted-foreground">
            {set ? '(set — leave blank to keep)' : '(not set)'}
        </span>
    );
}

export default function CourierForm({
    courier,
    drivers,
}: {
    courier: Courier | null;
    drivers: string[];
}) {
    const editing = Boolean(courier);
    const [driver, setDriver] = useState<string>(courier?.driver ?? 'manual');
    const [active, setActive] = useState<boolean>(courier?.is_active ?? true);
    const [isDefault, setIsDefault] = useState<boolean>(courier?.is_default ?? false);

    const action = editing ? `/admin/shipping/couriers/${courier!.id}` : '/admin/shipping/couriers';

    return (
        <>
            <Head title={editing ? 'Edit courier' : 'New courier'} />
            <Form
                action={action}
                method={editing ? 'put' : 'post'}
                options={{ preserveScroll: true }}
                className="mx-auto w-full max-w-xl p-4 pb-24"
            >
                {({ processing, errors }) => (
                    <>
                        <h1 className="mb-4 text-lg font-semibold">
                            {editing ? 'Edit courier' : 'New courier'}
                        </h1>

                        <input type="hidden" name="is_active" value={active ? '1' : '0'} />
                        <input type="hidden" name="is_default" value={isDefault ? '1' : '0'} />

                        <div className="space-y-6 rounded-xl border bg-card p-4 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Courier name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={courier?.name ?? ''}
                                    required
                                    placeholder="e.g. Sundarban Courier"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="driver">Type</Label>
                                    <select
                                        id="driver"
                                        name="driver"
                                        value={driver}
                                        onChange={(e) => setDriver(e.target.value)}
                                        className={SELECT_CLASS}
                                    >
                                        {drivers.map((d) => (
                                            <option key={d} value={d}>
                                                {DRIVER_LABELS[d] ?? d}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.driver} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="position_order">Sort order</Label>
                                    <Input
                                        id="position_order"
                                        name="position_order"
                                        type="number"
                                        min={0}
                                        defaultValue={courier?.position_order ?? 0}
                                    />
                                    <InputError message={errors.position_order} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="slug">Slug (optional)</Label>
                                <Input
                                    id="slug"
                                    name="slug"
                                    defaultValue={courier?.slug ?? ''}
                                    placeholder="auto from name"
                                />
                                <InputError message={errors.slug} />
                            </div>

                            <div className="flex flex-wrap gap-6">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_active"
                                        checked={active}
                                        onCheckedChange={(v) => setActive(v === true)}
                                    />
                                    <Label htmlFor="is_active">Active (selectable on orders)</Label>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="is_default"
                                        checked={isDefault}
                                        onCheckedChange={(v) => setIsDefault(v === true)}
                                    />
                                    <Label htmlFor="is_default">Default (auto-book on confirm)</Label>
                                </div>
                            </div>
                        </div>

                        {driver === 'steadfast' && (
                            <div className="mt-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                                <div>
                                    <h2 className="text-sm font-medium">Steadfast API credentials</h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Stored encrypted. Leave a field blank to keep the saved value.
                                    </p>
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="api_key">
                                        API Key <SecretHint set={courier?.api_key_set ?? false} />
                                    </Label>
                                    <Input id="api_key" name="api_key" type="password" autoComplete="off" />
                                    <InputError message={errors.api_key} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="secret_key">
                                        Secret Key <SecretHint set={courier?.secret_key_set ?? false} />
                                    </Label>
                                    <Input id="secret_key" name="secret_key" type="password" autoComplete="off" />
                                    <InputError message={errors.secret_key} />
                                </div>
                            </div>
                        )}

                        {driver === 'manual' && (
                            <p className="mt-4 rounded-xl border border-dashed bg-muted/30 p-4 text-xs text-muted-foreground">
                                Manual couriers have no API. When you book an order with this courier,
                                it is recorded and the name prints on the shipping label — you arrange
                                the pickup yourself and update the status by hand.
                            </p>
                        )}

                        <div className="sticky bottom-0 mt-4 flex items-center justify-end gap-2 border-t bg-background/95 py-3 backdrop-blur">
                            <Button variant="outline" asChild>
                                <Link href="/admin/shipping/couriers">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save changes' : 'Create courier'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

CourierForm.layout = {
    breadcrumbs: [
        { title: 'Couriers', href: '/admin/shipping/couriers' },
        { title: 'Edit', href: '#' },
    ],
};
