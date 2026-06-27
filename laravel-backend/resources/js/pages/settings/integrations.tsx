import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Sslcommerz = {
    store_id: string;
    sandbox: boolean;
    store_passwd_set: boolean;
};

type Steadfast = {
    api_key_set: boolean;
    secret_key_set: boolean;
};

function SecretHint({ isSet }: { isSet: boolean }) {
    return (
        <span className="text-muted-foreground">
            {isSet ? '(set — blank keeps)' : '(not set)'}
        </span>
    );
}

export default function Integrations({
    sslcommerz,
    steadfast,
}: {
    sslcommerz: Sslcommerz;
    steadfast: Steadfast;
}) {
    const [sandbox, setSandbox] = useState<boolean>(sslcommerz.sandbox);

    return (
        <>
            <Head title="Integrations" />
            <h1 className="sr-only">Integrations</h1>

            <div className="space-y-8">
                <Heading
                    variant="small"
                    title="Payment & courier integrations"
                    description="Connect SSLCommerz (online payments) and SteadFast (courier). All secret keys are stored encrypted and are never sent back to the browser — leave a secret blank to keep the saved value."
                />

                {/* SSLCommerz */}
                <Form
                    action="/settings/sslcommerz"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">SSLCommerz — online payments</p>
                                <span
                                    className={`rounded-md px-2 py-0.5 text-xs font-medium ${
                                        sslcommerz.store_passwd_set
                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                    }`}
                                >
                                    {sslcommerz.store_passwd_set ? 'Configured' : 'Not configured'}
                                </span>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="store_id">Store ID</Label>
                                <Input
                                    id="store_id"
                                    name="store_id"
                                    defaultValue={sslcommerz.store_id}
                                    placeholder="furnib_live"
                                    autoComplete="off"
                                />
                                <InputError message={errors.store_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="store_passwd">
                                    Store Password <SecretHint isSet={sslcommerz.store_passwd_set} />
                                </Label>
                                <Input
                                    id="store_passwd"
                                    name="store_passwd"
                                    type="password"
                                    autoComplete="off"
                                    placeholder="••••••••"
                                />
                                <InputError message={errors.store_passwd} />
                            </div>

                            <div className="grid gap-2">
                                <Label>Mode</Label>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <label
                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                                            sandbox ? 'border-primary bg-primary/5' : 'border-border'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="sandbox"
                                            value="1"
                                            checked={sandbox}
                                            onChange={() => setSandbox(true)}
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="block text-sm font-medium">Sandbox</span>
                                            <span className="block text-xs text-muted-foreground">
                                                Test gateway — no real money moves.
                                            </span>
                                        </span>
                                    </label>
                                    <label
                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-3 ${
                                            !sandbox ? 'border-primary bg-primary/5' : 'border-border'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="sandbox"
                                            value="0"
                                            checked={!sandbox}
                                            onChange={() => setSandbox(false)}
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="block text-sm font-medium">Live</span>
                                            <span className="block text-xs text-muted-foreground">
                                                Production gateway — real payments.
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <InputError message={errors.sandbox} />
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} data-test="save-sslcommerz">
                                    Save SSLCommerz
                                </Button>
                                {recentlySuccessful && <p className="text-sm text-green-600">Saved.</p>}
                            </div>
                        </>
                    )}
                </Form>

                {/* SteadFast */}
                <Form
                    action="/settings/steadfast"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">SteadFast — courier</p>
                                <span
                                    className={`rounded-md px-2 py-0.5 text-xs font-medium ${
                                        steadfast.api_key_set && steadfast.secret_key_set
                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                    }`}
                                >
                                    {steadfast.api_key_set && steadfast.secret_key_set
                                        ? 'Configured'
                                        : 'Not configured'}
                                </span>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="api_key">
                                        API Key <SecretHint isSet={steadfast.api_key_set} />
                                    </Label>
                                    <Input
                                        id="api_key"
                                        name="api_key"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="••••••••"
                                    />
                                    <InputError message={errors.api_key} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="secret_key">
                                        Secret Key <SecretHint isSet={steadfast.secret_key_set} />
                                    </Label>
                                    <Input
                                        id="secret_key"
                                        name="secret_key"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="••••••••"
                                    />
                                    <InputError message={errors.secret_key} />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} data-test="save-steadfast">
                                    Save SteadFast
                                </Button>
                                {recentlySuccessful && <p className="text-sm text-green-600">Saved.</p>}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Integrations.layout = {
    breadcrumbs: [
        {
            title: 'Integrations',
            href: '/settings/integrations',
        },
    ],
};
