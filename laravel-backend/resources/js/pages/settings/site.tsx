import { Form, Head } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FooterLink = { label: string; url: string };

type Branding = {
    site_name: string;
    tagline: string;
    whatsapp: string;
    contact_phone: string;
    contact_email: string;
    contact_address: string;
    social_facebook: string;
    social_instagram: string;
    social_youtube: string;
    social_linkedin: string;
    social_x: string;
    social_pinterest: string;
    social_tiktok: string;
    social_facebook_enabled: boolean;
    social_instagram_enabled: boolean;
    social_youtube_enabled: boolean;
    social_linkedin_enabled: boolean;
    social_x_enabled: boolean;
    social_pinterest_enabled: boolean;
    social_tiktok_enabled: boolean;
    about_links: FooterLink[];
    logo_light_url: string | null;
    logo_dark_url: string | null;
    logo_footer_url: string | null;
    logo_invoice_url: string | null;
    favicon_url: string | null;
    banner_1_url: string | null;
    banner_2_url: string | null;
};

const SOCIALS: {
    key: keyof Branding;
    enabledKey: keyof Branding;
    label: string;
    placeholder: string;
}[] = [
    { key: 'social_facebook', enabledKey: 'social_facebook_enabled', label: 'Facebook', placeholder: 'https://facebook.com/furnib' },
    { key: 'social_instagram', enabledKey: 'social_instagram_enabled', label: 'Instagram', placeholder: 'https://instagram.com/furnib' },
    { key: 'social_youtube', enabledKey: 'social_youtube_enabled', label: 'YouTube', placeholder: 'https://youtube.com/@furnib' },
    { key: 'social_linkedin', enabledKey: 'social_linkedin_enabled', label: 'LinkedIn', placeholder: 'https://linkedin.com/company/furnib' },
    { key: 'social_x', enabledKey: 'social_x_enabled', label: 'X (Twitter)', placeholder: 'https://x.com/furnib' },
    { key: 'social_pinterest', enabledKey: 'social_pinterest_enabled', label: 'Pinterest', placeholder: 'https://pinterest.com/furnib' },
    { key: 'social_tiktok', enabledKey: 'social_tiktok_enabled', label: 'TikTok', placeholder: 'https://tiktok.com/@furnib' },
];

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
    const [links, setLinks] = useState<FooterLink[]>(branding.about_links ?? []);

    const setLink = (index: number, field: keyof FooterLink, value: string) =>
        setLinks((prev) => prev.map((l, i) => (i === index ? { ...l, [field]: value } : l)));

    const addLink = () => setLinks((prev) => [...prev, { label: '', url: '' }]);
    const removeLink = (index: number) =>
        setLinks((prev) => prev.filter((_, i) => i !== index));

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
                                    {/* Footer is brand-orange, so preview on a matching bg. */}
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

                            {/* Footer — Follow us */}
                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <div>
                                    <p className="text-sm font-medium">Follow us</p>
                                    <p className="text-xs text-muted-foreground">
                                        Full https:// URLs. Untick “Show” to hide a button
                                        without losing its link.
                                    </p>
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {SOCIALS.map((s) => (
                                        <div key={s.key} className="grid gap-2">
                                            <div className="flex items-center justify-between">
                                                <Label htmlFor={s.key}>{s.label}</Label>
                                                {/* Hidden field guarantees a value is sent when
                                                    the box is unticked; checked sends "1" last. */}
                                                <input
                                                    type="hidden"
                                                    name={s.enabledKey}
                                                    value="0"
                                                />
                                                <label className="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                                    <input
                                                        type="checkbox"
                                                        name={s.enabledKey}
                                                        value="1"
                                                        defaultChecked={
                                                            branding[s.enabledKey] as boolean
                                                        }
                                                        className="size-4 accent-[#e85d1f]"
                                                    />
                                                    Show
                                                </label>
                                            </div>
                                            <Input
                                                id={s.key}
                                                name={s.key}
                                                type="url"
                                                defaultValue={branding[s.key] as string}
                                                placeholder={s.placeholder}
                                            />
                                            <InputError
                                                message={
                                                    (errors as Record<string, string | undefined>)[
                                                        s.key
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Footer — quick links */}
                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <div>
                                    <p className="text-sm font-medium">Footer — quick links</p>
                                    <p className="text-xs text-muted-foreground">
                                        e.g. Privacy Policy, Terms, Delivery &amp; Return. URL must
                                        be a full https:// link or a path starting with /.
                                    </p>
                                </div>
                                {links.map((link, i) => {
                                    const errs = errors as Record<string, string | undefined>;

                                    return (
                                        <div
                                            key={i}
                                            className="flex flex-wrap items-start gap-2 sm:flex-nowrap"
                                        >
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`about_links[${i}][label]`}
                                                    value={link.label}
                                                    onChange={(e) =>
                                                        setLink(i, 'label', e.target.value)
                                                    }
                                                    placeholder="Label (e.g. Privacy Policy)"
                                                />
                                                <InputError
                                                    message={errs[`about_links.${i}.label`]}
                                                />
                                            </div>
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`about_links[${i}][url]`}
                                                    value={link.url}
                                                    onChange={(e) =>
                                                        setLink(i, 'url', e.target.value)
                                                    }
                                                    placeholder="/privacy or https://…"
                                                />
                                                <InputError
                                                    message={errs[`about_links.${i}.url`]}
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                aria-label="Remove link"
                                                onClick={() => removeLink(i)}
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                    );
                                })}
                                {/* Marker so an empty list still submits the key and
                                    clears previously saved links server-side. */}
                                {links.length === 0 && (
                                    <input type="hidden" name="about_links" value="" />
                                )}
                                {links.length < 12 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={addLink}
                                    >
                                        <Plus className="size-4" /> Add link
                                    </Button>
                                )}
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
