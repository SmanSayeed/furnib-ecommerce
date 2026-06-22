import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Marketing = {
    gtm_id: string | null;
    ga4_id: string | null;
    fb_pixel_id: string | null;
    clarity_id: string | null;
    fb_test_event_code: string | null;
    fb_capi_token_set: boolean;
};

export default function MarketingSettings({ marketing }: { marketing: Marketing }) {
    return (
        <>
            <Head title="Marketing & tracking" />

            <h1 className="sr-only">Marketing & tracking</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Marketing & tracking"
                    description="Connect Google Tag Manager, Meta Pixel + Conversions API, GA4 and Clarity. Only the CAPI token is a server-side secret."
                />

                <Form
                    action="/settings/marketing"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="gtm_id">
                                    Google Tag Manager container ID
                                </Label>
                                <Input
                                    id="gtm_id"
                                    name="gtm_id"
                                    defaultValue={marketing.gtm_id ?? ''}
                                    placeholder="GTM-XXXXXXX"
                                />
                                <p className="text-xs text-muted-foreground">
                                    The storefront loads GTM only after a visitor accepts cookies.
                                    Manage the Pixel/GA4/Clarity tags inside the GTM GUI.
                                </p>
                                <InputError message={errors.gtm_id} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="fb_pixel_id">Meta Pixel ID</Label>
                                    <Input
                                        id="fb_pixel_id"
                                        name="fb_pixel_id"
                                        defaultValue={marketing.fb_pixel_id ?? ''}
                                        inputMode="numeric"
                                        placeholder="1234567890"
                                    />
                                    <InputError message={errors.fb_pixel_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="ga4_id">GA4 Measurement ID</Label>
                                    <Input
                                        id="ga4_id"
                                        name="ga4_id"
                                        defaultValue={marketing.ga4_id ?? ''}
                                        placeholder="G-XXXXXXX"
                                    />
                                    <InputError message={errors.ga4_id} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="clarity_id">Microsoft Clarity ID</Label>
                                <Input
                                    id="clarity_id"
                                    name="clarity_id"
                                    defaultValue={marketing.clarity_id ?? ''}
                                    placeholder="abcdef1234"
                                />
                                <InputError message={errors.clarity_id} />
                            </div>

                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">
                                    Meta Conversions API (server-side)
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="fb_capi_token">
                                        CAPI access token{' '}
                                        <span className="text-muted-foreground">
                                            {marketing.fb_capi_token_set
                                                ? '(saved — leave blank to keep)'
                                                : '(not set)'}
                                        </span>
                                    </Label>
                                    <Input
                                        id="fb_capi_token"
                                        name="fb_capi_token"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="EAA…"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Stored encrypted. Never sent to the browser, logs, or the
                                        product feed. Server-side only.
                                    </p>
                                    <InputError message={errors.fb_capi_token} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="fb_test_event_code">
                                        Test event code{' '}
                                        <span className="text-muted-foreground">(QA only)</span>
                                    </Label>
                                    <Input
                                        id="fb_test_event_code"
                                        name="fb_test_event_code"
                                        defaultValue={marketing.fb_test_event_code ?? ''}
                                        placeholder="TEST12345"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        From Events Manager → Test Events. Leave blank in
                                        production.
                                    </p>
                                    <InputError message={errors.fb_test_event_code} />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} data-test="save-marketing-settings">
                                    Save
                                </Button>
                                {recentlySuccessful && (
                                    <p className="text-sm text-green-600">Saved.</p>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

MarketingSettings.layout = {
    breadcrumbs: [
        {
            title: 'Marketing & tracking',
            href: '/settings/marketing',
        },
    ],
};
