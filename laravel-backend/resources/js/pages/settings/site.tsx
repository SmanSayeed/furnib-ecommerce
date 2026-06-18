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
    contact_phone: string;
    contact_email: string;
    contact_address: string;
    logo_light_url: string | null;
    logo_dark_url: string | null;
    favicon_url: string | null;
    banner_1_url: string | null;
    banner_2_url: string | null;
};

function FilePreview({
    url,
    dark = false,
}: {
    url: string | null;
    dark?: boolean;
}) {
    if (!url) {
        return (
            <span className="text-xs text-muted-foreground">No file uploaded</span>
        );
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
                    description="Manage your store name, contact details, logo and favicon"
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
                                    <Label htmlFor="contact_phone">Contact phone</Label>
                                    <Input
                                        id="contact_phone"
                                        name="contact_phone"
                                        defaultValue={branding.contact_phone}
                                        placeholder="+880 1712-345678"
                                    />
                                    <InputError message={errors.contact_phone} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contact_email">Contact email</Label>
                                    <Input
                                        id="contact_email"
                                        type="email"
                                        name="contact_email"
                                        defaultValue={branding.contact_email}
                                        placeholder="hello@furnib.com"
                                    />
                                    <InputError message={errors.contact_email} />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="contact_address">Address</Label>
                                <Input
                                    id="contact_address"
                                    name="contact_address"
                                    defaultValue={branding.contact_address}
                                    placeholder="Dhaka, Bangladesh"
                                />
                                <InputError message={errors.contact_address} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="logo_light">
                                        Logo — light theme (PNG/JPG/WebP)
                                    </Label>
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

                            <div className="grid gap-2">
                                <Label htmlFor="favicon">Favicon (PNG/ICO)</Label>
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
                                SVG uploads are disabled for security. Use a
                                transparent PNG for best results.
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
