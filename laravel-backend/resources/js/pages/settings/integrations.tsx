import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Sslcommerz = {
    sandbox: boolean;
    sandbox_store_id: string;
    sandbox_store_passwd_set: boolean;
    live_store_id: string;
    live_store_passwd_set: boolean;
};

type Steadfast = {
    api_key_set: boolean;
    secret_key_set: boolean;
};

type SmsEvent = {
    key: string;
    enabled: boolean;
    template: string;
};

type SmsSettings = {
    enabled: boolean;
    sender_id: string;
    api_key_set: boolean;
    events: SmsEvent[];
    dlr: {
        configured: boolean;
        success_url: string | null;
        failed_url: string | null;
    };
};

const SMS_EVENT_LABELS: Record<string, string> = {
    placed: 'Order placed (pay link)',
    confirmed: 'Order confirmed',
    shipped: 'Shipped (with tracking)',
    delivered: 'Delivered',
    cancelled: 'Cancelled',
    returned: 'Returned',
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
    sms,
}: {
    sslcommerz: Sslcommerz;
    steadfast: Steadfast;
    sms: SmsSettings;
}) {
    const [sandbox, setSandbox] = useState<boolean>(sslcommerz.sandbox);
    const [smsEnabled, setSmsEnabled] = useState<boolean>(sms.enabled);
    const [smsEvents, setSmsEvents] = useState<Record<string, boolean>>(
        () => Object.fromEntries(sms.events.map((e) => [e.key, e.enabled])),
    );

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
                                        (sandbox ? sslcommerz.sandbox_store_passwd_set : sslcommerz.live_store_passwd_set)
                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                    }`}
                                >
                                    {(sandbox ? sslcommerz.sandbox_store_passwd_set : sslcommerz.live_store_passwd_set)
                                        ? `${sandbox ? 'Sandbox' : 'Live'} configured`
                                        : `${sandbox ? 'Sandbox' : 'Live'} not configured`}
                                </span>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Sandbox and Live credentials are stored separately — filling one never wipes the
                                other. The Mode below decides which set is used. Leave a password blank to keep it.
                            </p>

                            {/* Sandbox credentials */}
                            <div className="space-y-3 rounded-lg border border-border p-3">
                                <p className="text-xs font-semibold text-muted-foreground">Sandbox credentials</p>
                                <div className="grid gap-2">
                                    <Label htmlFor="sandbox_store_id">Sandbox Store ID</Label>
                                    <Input
                                        id="sandbox_store_id"
                                        name="sandbox_store_id"
                                        defaultValue={sslcommerz.sandbox_store_id}
                                        placeholder="testbox"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.sandbox_store_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="sandbox_store_passwd">
                                        Sandbox Store Password <SecretHint isSet={sslcommerz.sandbox_store_passwd_set} />
                                    </Label>
                                    <Input
                                        id="sandbox_store_passwd"
                                        name="sandbox_store_passwd"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="••••••••"
                                    />
                                    <InputError message={errors.sandbox_store_passwd} />
                                </div>
                            </div>

                            {/* Live credentials */}
                            <div className="space-y-3 rounded-lg border border-border p-3">
                                <p className="text-xs font-semibold text-muted-foreground">Live credentials</p>
                                <div className="grid gap-2">
                                    <Label htmlFor="live_store_id">Live Store ID</Label>
                                    <Input
                                        id="live_store_id"
                                        name="live_store_id"
                                        defaultValue={sslcommerz.live_store_id}
                                        placeholder="furniblive"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.live_store_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="live_store_passwd">
                                        Live Store Password <SecretHint isSet={sslcommerz.live_store_passwd_set} />
                                    </Label>
                                    <Input
                                        id="live_store_passwd"
                                        name="live_store_passwd"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="••••••••"
                                    />
                                    <InputError message={errors.live_store_passwd} />
                                </div>
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

                {/* SMS (Automas) — customer order-status notifications */}
                <Form
                    action="/settings/sms"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-4 rounded-lg border border-border p-4"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="flex items-center justify-between">
                                <p className="text-sm font-medium">SMS — customer notifications (Automas)</p>
                                <span
                                    className={`rounded-md px-2 py-0.5 text-xs font-medium ${
                                        sms.api_key_set
                                            ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                            : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'
                                    }`}
                                >
                                    {sms.api_key_set ? 'Configured' : 'Not configured'}
                                </span>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                Order-status SMS to customers, in Bangla (Unicode) per BTRC rules. Paste your
                                BTRC-vetted text into each template. Placeholders:{' '}
                                <code>{'{name}'}</code> <code>{'{order_no}'}</code> <code>{'{total}'}</code>{' '}
                                <code>{'{due}'}</code> <code>{'{tracking}'}</code>.
                            </p>

                            {/* Master enable */}
                            <input type="hidden" name="enabled" value={smsEnabled ? '1' : '0'} />
                            <label className="flex cursor-pointer items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={smsEnabled}
                                    onChange={(e) => setSmsEnabled(e.target.checked)}
                                />
                                <span className="text-sm font-medium">Enable SMS notifications</span>
                            </label>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="sender_id">Sender ID</Label>
                                    <Input
                                        id="sender_id"
                                        name="sender_id"
                                        defaultValue={sms.sender_id}
                                        placeholder="8809617635160"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.sender_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="sms_api_key">
                                        API Key <SecretHint isSet={sms.api_key_set} />
                                    </Label>
                                    <Input
                                        id="sms_api_key"
                                        name="api_key"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="••••••••"
                                    />
                                    <InputError message={errors.api_key} />
                                </div>
                            </div>

                            {/* Per-event toggle + editable Bangla template */}
                            <div className="space-y-3">
                                {sms.events.map((ev) => (
                                    <div key={ev.key} className="rounded-lg border border-border p-3">
                                        <input
                                            type="hidden"
                                            name={`event_${ev.key}`}
                                            value={smsEvents[ev.key] ? '1' : '0'}
                                        />
                                        <label className="flex cursor-pointer items-center gap-3">
                                            <input
                                                type="checkbox"
                                                checked={smsEvents[ev.key] ?? false}
                                                onChange={(e) =>
                                                    setSmsEvents((s) => ({ ...s, [ev.key]: e.target.checked }))
                                                }
                                            />
                                            <span className="text-sm font-medium">
                                                {SMS_EVENT_LABELS[ev.key] ?? ev.key}
                                            </span>
                                        </label>
                                        <textarea
                                            name={`tpl_${ev.key}`}
                                            defaultValue={ev.template}
                                            rows={2}
                                            maxLength={500}
                                            dir="auto"
                                            className="mt-2 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs"
                                        />
                                        <InputError message={errors[`tpl_${ev.key}`]} />
                                    </div>
                                ))}
                            </div>

                            {/* DLR (delivery report) push URLs for the Automas panel */}
                            {sms.dlr.configured ? (
                                <div className="space-y-2 rounded-lg border border-border bg-muted/30 p-3">
                                    <p className="text-sm font-medium">Delivery reports (DLR) — optional</p>
                                    <p className="text-xs text-muted-foreground">
                                        In the Automas panel → Developer Options → DLR Push Configuration, add these
                                        URLs so delivered/failed status is tracked here. Keep them secret.
                                    </p>
                                    <div className="grid gap-2">
                                        <Label className="text-xs">Success URL</Label>
                                        <Input readOnly value={sms.dlr.success_url ?? ''} onFocus={(e) => e.target.select()} />
                                        <Label className="text-xs">Fail URL</Label>
                                        <Input readOnly value={sms.dlr.failed_url ?? ''} onFocus={(e) => e.target.select()} />
                                    </div>
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground">
                                    Save once to generate your DLR (delivery report) push URLs.
                                </p>
                            )}

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} data-test="save-sms">
                                    Save SMS
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
