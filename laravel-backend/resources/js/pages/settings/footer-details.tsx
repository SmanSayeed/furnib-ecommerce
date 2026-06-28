import { Form, Head } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type FooterLink = { label: string; url: string };

type FooterData = {
    logo_footer_url: string | null;
    contact_phone: string;
    contact_email: string;
    contact_address: string;
    about_links: FooterLink[];
};

export default function FooterDetails({ footer }: { footer: FooterData }) {
    const [links, setLinks] = useState<FooterLink[]>(footer.about_links ?? []);

    const setLink = (index: number, field: keyof FooterLink, value: string) =>
        setLinks((prev) => prev.map((l, i) => (i === index ? { ...l, [field]: value } : l)));
    const addLink = () => setLinks((prev) => [...prev, { label: '', url: '' }]);
    const removeLink = (index: number) => setLinks((prev) => prev.filter((_, i) => i !== index));

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
                                        max 2 MB. Leave empty to show the store name as text.
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

                                <div className="space-y-4 rounded-lg border border-border p-4">
                                    <div>
                                        <p className="text-sm font-medium">Footer quick links</p>
                                        <p className="text-xs text-muted-foreground">
                                            e.g. About us, Privacy Policy, Terms. URL must be a
                                            full https:// link or a path starting with /
                                            (e.g. <code>/p/privacy-policy</code>).
                                        </p>
                                    </div>
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
