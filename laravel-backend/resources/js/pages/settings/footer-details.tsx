import { Form, Head, Link, router } from '@inertiajs/react';
import { Lock, Plus, SquarePen, X } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PageRow = {
    id: number;
    slug: string;
    title: string;
    is_system: boolean;
    show_in_footer: boolean;
};

type FooterData = {
    logo_footer_url: string | null;
    contact_phone: string;
    contact_phone_2: string;
    contact_email: string;
    contact_address: string;
    contact_hours: string;
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
    pages: PageRow[];
}) {
    // Add / remove a published page from the storefront footer. A partial-state
    // visit keeps any unsaved edits in the contact form below intact.
    const togglePage = (p: PageRow) =>
        router.patch(
            `/settings/footer/pages/${p.id}`,
            {},
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title="Footer details" />
            <h1 className="sr-only">Footer details</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Footer details"
                    description="Contact block, footer pages and trust badges shown in the storefront footer."
                />

                {/* Footer pages — published pages shown in the storefront footer
                    "About Us" column. Toggling is an independent action (not part
                    of the contact form below). */}
                <div className="space-y-4 rounded-lg border border-border p-4">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p className="text-sm font-medium">Footer pages</p>
                            <p className="text-xs text-muted-foreground">
                                Every published page below shows in the footer &ldquo;About Us&rdquo;
                                column. Remove one with{' '}
                                <X className="inline size-3" aria-hidden />, or add it back. Legal
                                (System) pages are always shown.
                            </p>
                        </div>
                        <Button asChild variant="outline" size="sm">
                            <Link href="/admin/pages/create">
                                <SquarePen className="size-4" /> Create footer page
                            </Link>
                        </Button>
                    </div>

                    {pages.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No published pages yet.{' '}
                            <Link href="/admin/pages/create" className="underline">
                                Create one
                            </Link>{' '}
                            to show it in the footer.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border rounded-md border border-border">
                            {pages.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex items-center justify-between gap-3 px-3 py-2.5"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2 text-sm font-medium">
                                            <span className="truncate">{p.title}</span>
                                            {p.is_system && (
                                                <span className="inline-flex items-center gap-1 rounded bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                                    <Lock className="size-3" /> System
                                                </span>
                                            )}
                                            {!p.show_in_footer && (
                                                <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                                                    Hidden
                                                </span>
                                            )}
                                        </div>
                                        <div className="truncate text-xs text-muted-foreground">
                                            /p/{p.slug}
                                        </div>
                                    </div>

                                    {p.is_system ? (
                                        <span
                                            className="shrink-0 p-2 text-muted-foreground"
                                            title="Legal page — always shown in the footer"
                                        >
                                            <Lock className="size-4" />
                                        </span>
                                    ) : p.show_in_footer ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0"
                                            aria-label={`Remove ${p.title} from footer`}
                                            title="Remove from footer"
                                            onClick={() => togglePage(p)}
                                        >
                                            <X className="size-4" />
                                        </Button>
                                    ) : (
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="shrink-0"
                                            onClick={() => togglePage(p)}
                                        >
                                            <Plus className="size-4" /> Add to footer
                                        </Button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

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
                                            placeholder="01748870651"
                                        />
                                        <InputError message={errs.contact_phone} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="contact_phone_2">
                                            Second phone (optional)
                                        </Label>
                                        <Input
                                            id="contact_phone_2"
                                            name="contact_phone_2"
                                            defaultValue={footer.contact_phone_2}
                                            placeholder="09638209209"
                                        />
                                        <InputError message={errs.contact_phone_2} />
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
