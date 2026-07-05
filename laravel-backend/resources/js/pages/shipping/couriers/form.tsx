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
    sandbox?: boolean;
    // Per-driver "is set" flags (only the chosen driver's keys are present).
    api_key_set?: boolean;
    secret_key_set?: boolean;
    access_token_set?: boolean;
    pickup_store_id_set?: boolean;
    client_id_set?: boolean;
    client_secret_set?: boolean;
    username_set?: boolean;
    password_set?: boolean;
    store_id_set?: boolean;
};

const SELECT_CLASS =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

const DRIVER_LABELS: Record<string, string> = {
    manual: 'Manual — no API (booked by hand)',
    steadfast: 'Steadfast (API)',
    redx: 'RedX (API)',
    pathao: 'Pathao (API)',
};

const API_DRIVERS = ['steadfast', 'redx', 'pathao'];

function SecretHint({ set }: { set: boolean }) {
    return (
        <span className="text-xs text-muted-foreground">
            {set ? '(set — leave blank to keep)' : '(not set)'}
        </span>
    );
}

function CredField({
    name,
    label,
    set,
    error,
    type = 'password',
}: {
    name: string;
    label: string;
    set: boolean;
    error?: string;
    type?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>
                {label} <SecretHint set={set} />
            </Label>
            <Input id={name} name={name} type={type} autoComplete="off" />
            <InputError message={error} />
        </div>
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
    const [sandbox, setSandbox] = useState<boolean>(courier?.sandbox ?? false);

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
                        <input type="hidden" name="sandbox" value={sandbox ? '1' : '0'} />

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

                        {API_DRIVERS.includes(driver) && (
                            <div className="mt-4 space-y-4 rounded-xl border bg-card p-4 md:p-6">
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <h2 className="text-sm font-medium">
                                            {DRIVER_LABELS[driver] ?? driver} credentials
                                        </h2>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Stored encrypted. Leave a field blank to keep the saved value.
                                        </p>
                                    </div>
                                    <label className="flex shrink-0 items-center gap-2 text-xs text-muted-foreground">
                                        <Checkbox
                                            checked={sandbox}
                                            onCheckedChange={(v) => setSandbox(v === true)}
                                        />
                                        Sandbox mode
                                    </label>
                                </div>

                                {driver === 'steadfast' && (
                                    <>
                                        <CredField name="api_key" label="API Key" set={courier?.api_key_set ?? false} error={errors.api_key} />
                                        <CredField name="secret_key" label="Secret Key" set={courier?.secret_key_set ?? false} error={errors.secret_key} />
                                    </>
                                )}

                                {driver === 'redx' && (
                                    <>
                                        <CredField name="access_token" label="Access Token (JWT)" set={courier?.access_token_set ?? false} error={errors.access_token} />
                                        <CredField name="pickup_store_id" label="Pickup Store ID" set={courier?.pickup_store_id_set ?? false} error={errors.pickup_store_id} type="text" />
                                    </>
                                )}

                                {driver === 'pathao' && (
                                    <>
                                        <CredField name="client_id" label="Client ID" set={courier?.client_id_set ?? false} error={errors.client_id} />
                                        <CredField name="client_secret" label="Client Secret" set={courier?.client_secret_set ?? false} error={errors.client_secret} />
                                        <CredField name="username" label="Username" set={courier?.username_set ?? false} error={errors.username} type="text" />
                                        <CredField name="password" label="Password" set={courier?.password_set ?? false} error={errors.password} />
                                        <CredField name="store_id" label="Store ID" set={courier?.store_id_set ?? false} error={errors.store_id} type="text" />
                                    </>
                                )}
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
