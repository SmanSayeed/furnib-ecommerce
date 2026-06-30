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
    tiktok_pixel_id: string | null;
    fb_test_event_code: string | null;
    tiktok_test_event_code: string | null;
    fb_capi_token_set: boolean;
    tiktok_access_token_set: boolean;
    ga4_api_secret_set: boolean;
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
                                    Loaded on both the storefront and this admin. Manage the
                                    Pixel/GA4/Clarity/TikTok tags inside the GTM GUI.
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

                            <div className="grid gap-4 sm:grid-cols-2">
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
                                <div className="grid gap-2">
                                    <Label htmlFor="tiktok_pixel_id">TikTok Pixel code</Label>
                                    <Input
                                        id="tiktok_pixel_id"
                                        name="tiktok_pixel_id"
                                        defaultValue={marketing.tiktok_pixel_id ?? ''}
                                        placeholder="CXXXXXXXXXXXXXXXXX"
                                    />
                                    <InputError message={errors.tiktok_pixel_id} />
                                </div>
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

                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">
                                    TikTok Events API (server-side)
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="tiktok_access_token">
                                        Access token{' '}
                                        <span className="text-muted-foreground">
                                            {marketing.tiktok_access_token_set
                                                ? '(saved — leave blank to keep)'
                                                : '(not set)'}
                                        </span>
                                    </Label>
                                    <Input
                                        id="tiktok_access_token"
                                        name="tiktok_access_token"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="TikTok Events API token"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Stored encrypted. Server-side only — never sent to the
                                        browser or logs.
                                    </p>
                                    <InputError message={errors.tiktok_access_token} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="tiktok_test_event_code">
                                        Test event code{' '}
                                        <span className="text-muted-foreground">(QA only)</span>
                                    </Label>
                                    <Input
                                        id="tiktok_test_event_code"
                                        name="tiktok_test_event_code"
                                        defaultValue={marketing.tiktok_test_event_code ?? ''}
                                        placeholder="TEST12345"
                                    />
                                    <InputError message={errors.tiktok_test_event_code} />
                                </div>
                            </div>

                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">
                                    GA4 Measurement Protocol (server-side)
                                </p>

                                <div className="grid gap-2">
                                    <Label htmlFor="ga4_api_secret">
                                        API secret{' '}
                                        <span className="text-muted-foreground">
                                            {marketing.ga4_api_secret_set
                                                ? '(saved — leave blank to keep)'
                                                : '(not set)'}
                                        </span>
                                    </Label>
                                    <Input
                                        id="ga4_api_secret"
                                        name="ga4_api_secret"
                                        type="password"
                                        autoComplete="off"
                                        placeholder="GA4 Measurement Protocol API secret"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        From GA4 → Admin → Data Streams → Measurement Protocol API
                                        secrets. Uses the GA4 Measurement ID above. Stored encrypted.
                                    </p>
                                    <InputError message={errors.ga4_api_secret} />
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
