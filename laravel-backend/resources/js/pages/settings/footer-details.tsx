import { Form, Head, Link } from '@inertiajs/react';
import { Plus, SquarePen, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FooterLink = { label: string; url: string };
type PageOption = { slug: string; title: string };

type FooterData = {
    logo_footer_url: string | null;
    contact_phone: string;
    contact_email: string;
    contact_address: string;
    contact_hours: string;
    about_links: FooterLink[];
    // Payment-gateway compliance fields.
    payment_banner_url: string | null;
    trade_license_no: string;
    registered_address: string;
    delivery_inside_dhaka: string;
    delivery_outside_dhaka: string;
    // Trust badges.
    member_of_enabled: boolean;
    member_of_heading: string;
    member_of_url: string;
    member_of_image_url: string | null;
    delivery_partner_enabled: boolean;
    delivery_partner_heading: string;
    delivery_partner_url: string;
    delivery_partner_image_url: string | null;
};

export default function FooterDetails({
    footer,
    pages,
}: {
    footer: FooterData;
    pages: PageOption[];
}) {
    const [links, setLinks] = useState<FooterLink[]>(footer.about_links ?? []);

    const setLink = (index: number, field: keyof FooterLink, value: string) =>
        setLinks((prev) => prev.map((l, i) => (i === index ? { ...l, [field]: value } : l)));
    const addLink = () => setLinks((prev) => [...prev, { label: '', url: '' }]);
    const removeLink = (index: number) => setLinks((prev) => prev.filter((_, i) => i !== index));

    // Append a footer page as a quick link (label = title, url = /p/slug).
    const addPageLink = (slug: string) => {
        const page = pages.find((p) => p.slug === slug);

        if (!page) {
            return;
        }

        const url = `/p/${page.slug}`;
        setLinks((prev) =>
            prev.some((l) => l.url === url) ? prev : [...prev, { label: page.title, url }],
        );
    };

    return (
        <>
            <Head title="Footer details" />
            <h1 className="sr-only">Footer details</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Footer details"
                    description="Contact block and the footer quick links (About us, Privacy, …) shown in the storefront footer."
                />

                <Form
                    action="/settings/footer/details"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => {
                        const errs = errors as Record<string, string | undefined>;

                        return (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="logo_footer">Footer logo (PNG/JPG/WebP)</Label>
                                    <p className="text-xs text-muted-foreground">
                                        Shown on the brand-orange footer — use a{' '}
                                        <strong>white / transparent PNG</strong>, ~240×64 px,
                                        max 20 MB. Leave empty to show the store name as text.
                                    </p>
                                    {/* Preview on a matching brand-orange background. */}
                                    <div className="flex h-16 items-center rounded-md border border-border bg-[#e85d1f] px-4">
                                        {footer.logo_footer_url ? (
                                            <img
                                                src={footer.logo_footer_url}
                                                alt="Footer logo"
                                                className="h-9 w-auto"
                                            />
                                        ) : (
                                            <span className="text-sm text-white/80">
                                                No footer logo uploaded
                                            </span>
                                        )}
                                    </div>
                                    <Input
                                        id="logo_footer"
                                        type="file"
                                        name="logo_footer"
                                        accept="image/png,image/jpeg,image/webp"
                                    />
                                    <InputError message={errs.logo_footer} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="contact_phone">Contact phone</Label>
                                        <Input
                                            id="contact_phone"
                                            name="contact_phone"
                                            defaultValue={footer.contact_phone}
                                            placeholder="+880 1712-345678"
                                        />
                                        <InputError message={errs.contact_phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="contact_email">Contact email</Label>
                                        <Input
                                            id="contact_email"
                                            type="email"
                                            name="contact_email"
                                            defaultValue={footer.contact_email}
                                            placeholder="hello@furnib.com"
                                        />
                                        <InputError message={errs.contact_email} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="contact_address">Address</Label>
                                    <Input
                                        id="contact_address"
                                        name="contact_address"
                                        defaultValue={footer.contact_address}
                                        placeholder="Dhaka, Bangladesh"
                                    />
                                    <InputError message={errs.contact_address} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="contact_hours">Contact hours</Label>
                                    <Input
                                        id="contact_hours"
                                        name="contact_hours"
                                        defaultValue={footer.contact_hours}
                                        placeholder="Every Day 9 AM To 2 AM"
                                    />
                                    <InputError message={errs.contact_hours} />
                                </div>

                                <div className="space-y-4 rounded-lg border border-border p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="text-sm font-medium">Footer quick links</p>
                                            <p className="text-xs text-muted-foreground">
                                                Add a Footer page below, or a custom link. URL must
                                                be a full https:// link or a path starting with /.
                                            </p>
                                        </div>
                                        <Button asChild variant="outline" size="sm">
                                            <Link href="/admin/pages/create">
                                                <SquarePen className="size-4" /> Create footer page
                                            </Link>
                                        </Button>
                                    </div>

                                    {/* Pick an existing page → adds it as a labelled link. */}
                                    {pages.length > 0 && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <select
                                                value=""
                                                onChange={(e) => {
                                                    addPageLink(e.target.value);
                                                    e.target.value = '';
                                                }}
                                                className="rounded-md border border-input bg-background px-2 py-1.5 text-sm outline-none focus:border-ring"
                                            >
                                                <option value="">+ Add a footer page…</option>
                                                {pages.map((p) => (
                                                    <option key={p.slug} value={p.slug}>
                                                        {p.title} (/p/{p.slug})
                                                    </option>
                                                ))}
                                            </select>
                                            <span className="text-xs text-muted-foreground">
                                                picks a page link with its title as the label
                                            </span>
                                        </div>
                                    )}
                                    {links.map((link, i) => (
                                        <div
                                            key={i}
                                            className="flex flex-wrap items-start gap-2 sm:flex-nowrap"
                                        >
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`about_links[${i}][label]`}
                                                    value={link.label}
                                                    onChange={(e) => setLink(i, 'label', e.target.value)}
                                                    placeholder="Label (e.g. About us)"
                                                />
                                                <InputError message={errs[`about_links.${i}.label`]} />
                                            </div>
                                            <div className="grid flex-1 gap-1">
                                                <Input
                                                    name={`about_links[${i}][url]`}
                                                    value={link.url}
                                                    onChange={(e) => setLink(i, 'url', e.target.value)}
                                                    placeholder="/p/privacy-policy or https://…"
                                                />
                                                <InputError message={errs[`about_links.${i}.url`]} />
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
                                    ))}
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

                                <div className="space-y-4 rounded-lg border border-border p-4">
                                    <div>
                                        <p className="text-sm font-medium">
                                            Payment-gateway compliance
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Required by the payment gateway. Shown on the
                                            storefront footer and policy pages.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="trade_license_no">
                                            Trade licence no.
                                        </Label>
                                        <Input
                                            id="trade_license_no"
                                            name="trade_license_no"
                                            defaultValue={footer.trade_license_no}
                                            placeholder="e.g. TRAD/DNCC/012345/2026"
                                        />
                                        <InputError message={errs.trade_license_no} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="registered_address">
                                            Registered address
                                        </Label>
                                        <textarea
                                            id="registered_address"
                                            name="registered_address"
                                            defaultValue={footer.registered_address}
                                            rows={3}
                                            placeholder="House, Road, Area, Dhaka, Bangladesh"
                                            className="rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring"
                                        />
                                        <InputError message={errs.registered_address} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="grid gap-2">
                                            <Label htmlFor="delivery_inside_dhaka">
                                                Delivery time — inside Dhaka
                                            </Label>
                                            <Input
                                                id="delivery_inside_dhaka"
                                                name="delivery_inside_dhaka"
                                                defaultValue={footer.delivery_inside_dhaka}
                                                placeholder="Inside Dhaka: 5 days"
                                            />
                                            <InputError message={errs.delivery_inside_dhaka} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="delivery_outside_dhaka">
                                                Delivery time — outside Dhaka
                                            </Label>
                                            <Input
                                                id="delivery_outside_dhaka"
                                                name="delivery_outside_dhaka"
                                                defaultValue={footer.delivery_outside_dhaka}
                                                placeholder="Outside Dhaka: 10 days"
                                            />
                                            <InputError message={errs.delivery_outside_dhaka} />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="payment_banner">
                                            Payment methods banner (PNG/JPG/WebP)
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            The gateway-provided image showing accepted cards /
                                            mobile wallets. Max 20 MB.
                                        </p>
                                        {footer.payment_banner_url && (
                                            <div className="flex items-center rounded-md border border-border bg-muted px-4 py-3">
                                                <img
                                                    src={footer.payment_banner_url}
                                                    alt="Payment methods banner"
                                                    className="h-10 w-auto"
                                                />
                                            </div>
                                        )}
                                        <Input
                                            id="payment_banner"
                                            type="file"
                                            name="payment_banner"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <InputError message={errs.payment_banner} />
                                    </div>
                                </div>

                                {/* Trust badge — Member's Of. */}
                                <div className="space-y-4 rounded-lg border border-border p-4">
                                    <div>
                                        <label className="flex items-center gap-2 text-sm font-medium">
                                            <input
                                                type="checkbox"
                                                name="member_of_enabled"
                                                value="1"
                                                defaultChecked={footer.member_of_enabled}
                                                className="size-4 rounded border-input"
                                            />
                                            Show &ldquo;Member&rsquo;s Of&rdquo; badge
                                        </label>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            An association / membership logo shown in the storefront
                                            footer.
                                        </p>
                                        <InputError message={errs.member_of_enabled} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="member_of_heading">Heading</Label>
                                        <Input
                                            id="member_of_heading"
                                            name="member_of_heading"
                                            defaultValue={footer.member_of_heading}
                                            placeholder="Member's Of"
                                        />
                                        <InputError message={errs.member_of_heading} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="member_of_image">
                                            Badge image (PNG/JPG/WebP)
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Max 20 MB. Shown on a neutral card.
                                        </p>
                                        {footer.member_of_image_url && (
                                            <div className="flex items-center rounded-md border border-border bg-muted px-4 py-3">
                                                <img
                                                    src={footer.member_of_image_url}
                                                    alt="Member's Of badge"
                                                    className="h-10 w-auto"
                                                />
                                            </div>
                                        )}
                                        <Input
                                            id="member_of_image"
                                            type="file"
                                            name="member_of_image"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <InputError message={errs.member_of_image} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="member_of_url">Link (optional)</Label>
                                        <Input
                                            id="member_of_url"
                                            name="member_of_url"
                                            defaultValue={footer.member_of_url}
                                            placeholder="https://… or /p/…"
                                        />
                                        <InputError message={errs.member_of_url} />
                                    </div>
                                </div>

                                {/* Trust badge — Delivery Partner. */}
                                <div className="space-y-4 rounded-lg border border-border p-4">
                                    <div>
                                        <label className="flex items-center gap-2 text-sm font-medium">
                                            <input
                                                type="checkbox"
                                                name="delivery_partner_enabled"
                                                value="1"
                                                defaultChecked={footer.delivery_partner_enabled}
                                                className="size-4 rounded border-input"
                                            />
                                            Show &ldquo;Delivery Partner&rdquo; badge
                                        </label>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Your courier / logistics partner logo shown in the
                                            storefront footer.
                                        </p>
                                        <InputError message={errs.delivery_partner_enabled} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery_partner_heading">Heading</Label>
                                        <Input
                                            id="delivery_partner_heading"
                                            name="delivery_partner_heading"
                                            defaultValue={footer.delivery_partner_heading}
                                            placeholder="Delivery Partner"
                                        />
                                        <InputError message={errs.delivery_partner_heading} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery_partner_image">
                                            Badge image (PNG/JPG/WebP)
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            Max 20 MB. Shown on a neutral card.
                                        </p>
                                        {footer.delivery_partner_image_url && (
                                            <div className="flex items-center rounded-md border border-border bg-muted px-4 py-3">
                                                <img
                                                    src={footer.delivery_partner_image_url}
                                                    alt="Delivery Partner badge"
                                                    className="h-10 w-auto"
                                                />
                                            </div>
                                        )}
                                        <Input
                                            id="delivery_partner_image"
                                            type="file"
                                            name="delivery_partner_image"
                                            accept="image/png,image/jpeg,image/webp"
                                        />
                                        <InputError message={errs.delivery_partner_image} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="delivery_partner_url">
                                            Link (optional)
                                        </Label>
                                        <Input
                                            id="delivery_partner_url"
                                            name="delivery_partner_url"
                                            defaultValue={footer.delivery_partner_url}
                                            placeholder="https://… or /p/…"
                                        />
                                        <InputError message={errs.delivery_partner_url} />
                                    </div>
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>Save</Button>
                                    {recentlySuccessful && (
                                        <p className="text-sm text-green-600">Saved.</p>
                                    )}
                                </div>
                            </>
                        );
                    }}
                </Form>
            </div>
        </>
    );
}

FooterDetails.layout = {
    breadcrumbs: [{ title: 'Footer details', href: '/settings/footer/details' }],
};
