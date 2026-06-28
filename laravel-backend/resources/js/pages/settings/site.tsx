import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Branding = {
    site_name: string;
    tagline: string;
    whatsapp: string;
    logo_light_url: string | null;
    logo_dark_url: string | null;
    logo_footer_url: string | null;
    logo_invoice_url: string | null;
    favicon_url: string | null;
    banner_1_url: string | null;
    banner_2_url: string | null;
};

function FilePreview({ url, dark = false }: { url: string | null; dark?: boolean }) {
    if (!url) {
        return <span className="text-xs text-muted-foreground">No file uploaded</span>;
    }

    return (
        <div
            className={`flex h-14 items-center rounded-md border border-border px-3 ${
                dark ? 'bg-neutral-900' : 'bg-white'
            }`}
        >
            <img src={url} alt="Current" className="h-8 w-auto" />
        </div>
    );
}

export default function Site({ branding }: { branding: Branding }) {
    return (
        <>
            <Head title="Site settings" />

            <h1 className="sr-only">Site settings</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Site & branding"
                    description="Store name, WhatsApp number, logos and favicon. Footer social links and footer details live under Footer settings."
                />

                <Form
                    action="/settings/site"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="site_name">Site name</Label>
                                <Input
                                    id="site_name"
                                    name="site_name"
                                    defaultValue={branding.site_name}
                                    required
                                    placeholder="Furnib.com"
                                />
                                <InputError message={errors.site_name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="tagline">Tagline</Label>
                                <Input
                                    id="tagline"
                                    name="tagline"
                                    defaultValue={branding.tagline}
                                    placeholder="Feel the Comfort"
                                />
                                <InputError message={errors.tagline} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="whatsapp">
                                    WhatsApp number (digits only, with country code)
                                </Label>
                                <Input
                                    id="whatsapp"
                                    name="whatsapp"
                                    defaultValue={branding.whatsapp}
                                    inputMode="numeric"
                                    placeholder="8801712345678"
                                />
                                <InputError message={errors.whatsapp} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="logo_light">
                                        Logo — light theme (PNG/JPG/WebP)
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Transparent PNG, recommended ~240×64 px, max 2 MB.
                                    </p>
                                    <FilePreview url={branding.logo_light_url} />
                                    <Input
                                        id="logo_light"
                                        type="file"
                                        name="logo_light"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <InputError message={errors.logo_light} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="logo_dark">
                                        Logo — dark theme (PNG/JPG/WebP)
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Transparent PNG, recommended ~240×64 px, max 2 MB.
                                    </p>
                                    <FilePreview url={branding.logo_dark_url} dark />
                                    <Input
                                        id="logo_dark"
                                        type="file"
                                        name="logo_dark"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <InputError message={errors.logo_dark} />
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="logo_footer">
                                        Logo — footer (PNG/JPG/WebP)
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Shown on the orange footer — use a white/transparent
                                        PNG, ~240×64 px, max 2 MB. Falls back to the store name
                                        if empty.
                                    </p>
                                    <div className="flex h-14 items-center rounded-md border border-border bg-[#e85d1f] px-3">
                                        {branding.logo_footer_url ? (
                                            <img
                                                src={branding.logo_footer_url}
                                                alt="Footer logo"
                                                className="h-8 w-auto"
                                            />
                                        ) : (
                                            <span className="text-xs text-white/80">
                                                No file uploaded
                                            </span>
                                        )}
                                    </div>
                                    <Input
                                        id="logo_footer"
                                        type="file"
                                        name="logo_footer"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <InputError message={errors.logo_footer} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="logo_invoice">
                                        Logo — invoice PDF (PNG/JPG/WebP)
                                    </Label>
                                    <p className="text-xs text-muted-foreground">
                                        Printed at the top of invoice PDFs, ~220×48 px, max
                                        2 MB. Falls back to the light logo if empty.
                                    </p>
                                    <FilePreview url={branding.logo_invoice_url} />
                                    <Input
                                        id="logo_invoice"
                                        type="file"
                                        name="logo_invoice"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <InputError message={errors.logo_invoice} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="favicon">Favicon (PNG/ICO)</Label>
                                <p className="text-xs text-muted-foreground">
                                    Square 512×512 px PNG/ICO, max 512 KB. Also used as the
                                    brand avatar on storefront product cards.
                                </p>
                                <FilePreview url={branding.favicon_url} />
                                <Input
                                    id="favicon"
                                    type="file"
                                    name="favicon"
                                    accept="image/png,image/x-icon,.ico"
                                />
                                <InputError message={errors.favicon} />
                            </div>

                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">
                                    Home page banners (PNG/JPG/WebP/AVIF)
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Wide banners — recommended 1600×600 px, max 3 MB each.
                                </p>
                                <div className="grid gap-2">
                                    <Label htmlFor="banner_1">Banner 1</Label>
                                    {branding.banner_1_url && (
                                        <img
                                            src={branding.banner_1_url}
                                            alt="Banner 1"
                                            className="h-20 w-full rounded-md border border-border object-cover"
                                        />
                                    )}
                                    <Input
                                        id="banner_1"
                                        type="file"
                                        name="banner_1"
                                        accept="image/png,image/jpeg,image/webp,image/avif"
                                    />
                                    <InputError message={errors.banner_1} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="banner_2">Banner 2</Label>
                                    {branding.banner_2_url && (
                                        <img
                                            src={branding.banner_2_url}
                                            alt="Banner 2"
                                            className="h-20 w-full rounded-md border border-border object-cover"
                                        />
                                    )}
                                    <Input
                                        id="banner_2"
                                        type="file"
                                        name="banner_2"
                                        accept="image/png,image/jpeg,image/webp,image/avif"
                                    />
                                    <InputError message={errors.banner_2} />
                                </div>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                SVG uploads are disabled for security. Use a transparent PNG
                                for best results.
                            </p>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} data-test="save-site-settings">
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

Site.layout = {
    breadcrumbs: [
        {
            title: 'Site settings',
            href: '/settings/site',
        },
    ],
};
