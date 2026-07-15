import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Feed = {
    enabled: boolean;
    url: string | null;
    username: string | null;
    password_set: boolean;
    catalog_id: string | null;
    business_id: string | null;
};

type CategoryOption = { id: number; title: string };

type Props = {
    feed: Feed;
    newFeedPassword: string | null;
    categories: CategoryOption[];
};

export default function FacebookCommerce({ feed, newFeedPassword, categories }: Props) {
    const [copied, setCopied] = useState(false);
    const [selected, setSelected] = useState<number[]>([]);

    const copy = (text: string) => {
        navigator.clipboard?.writeText(text).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        });
    };

    const toggleCategory = (id: number) =>
        setSelected((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));

    const downloadUrl = () => {
        const params = selected.map((id) => `category_ids[]=${id}`).join('&');

        return `/settings/facebook-commerce/download${params ? `?${params}` : ''}`;
    };

    return (
        <>
            <Head title="Facebook Commerce" />
            <h1 className="sr-only">Facebook Commerce</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Facebook Commerce"
                    description="A secured product feed for Meta catalog ads, Instagram tagging and WhatsApp/Messenger sharing. In Bangladesh checkout links out to the storefront, so this uses Meta's scheduled feed (it pulls the CSV hourly) — no native Shops order sync."
                />

                {newFeedPassword && (
                    <div className="rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-sm">
                        <p className="font-semibold text-amber-700 dark:text-amber-400">Copy this feed password now — it won&apos;t be shown again:</p>
                        <div className="mt-2 flex items-center gap-2">
                            <code className="rounded bg-background px-2 py-1 font-mono text-xs">{newFeedPassword}</code>
                            <Button type="button" size="sm" variant="outline" onClick={() => copy(newFeedPassword)}>
                                {copied ? 'Copied ✓' : 'Copy'}
                            </Button>
                        </div>
                    </div>
                )}

                {/* Connection / IDs + enable */}
                <Form action="/settings/facebook-commerce" method="post" options={{ preserveScroll: true }} className="space-y-4">
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="catalog_id">Catalog ID (optional)</Label>
                                    <Input id="catalog_id" name="catalog_id" defaultValue={feed.catalog_id ?? ''} placeholder="From Commerce Manager" />
                                    <InputError message={errors.catalog_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="business_id">Business ID (optional)</Label>
                                    <Input id="business_id" name="business_id" defaultValue={feed.business_id ?? ''} />
                                    <InputError message={errors.business_id} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="feed_username">Feed username</Label>
                                    <Input id="feed_username" name="feed_username" defaultValue={feed.username ?? 'furnib-feed'} />
                                    <InputError message={errors.feed_username} />
                                </div>
                            </div>

                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="feed_enabled" value="1" defaultChecked={feed.enabled} />
                                Enable the feed URL (required for Meta to fetch it)
                            </label>
                            <p className="text-xs text-muted-foreground">
                                Enabling mints an unguessable feed URL + Basic-auth password (shown once). The feed is
                                otherwise a 404 and always requires the password — the full catalogue is never public.
                            </p>

                            <Button type="submit" disabled={processing}>Save</Button>
                        </>
                    )}
                </Form>

                {/* Feed URL */}
                <div className="rounded-lg border p-4">
                    <h2 className="mb-2 text-sm font-medium text-muted-foreground">Scheduled feed URL</h2>
                    {feed.enabled && feed.url ? (
                        <>
                            <div className="flex flex-wrap items-center gap-2">
                                <code className="min-w-0 flex-1 truncate rounded bg-muted/40 px-2 py-1 font-mono text-xs">{feed.url}</code>
                                <Button type="button" size="sm" variant="outline" onClick={() => copy(feed.url!)}>Copy</Button>
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                In Commerce Manager → Catalog → Data sources → <strong>Scheduled feed</strong>, paste this URL,
                                set username <code>{feed.username}</code> + the password above, and an interval of <strong>1 hour</strong>.
                            </p>
                            <Form action="/settings/facebook-commerce/regenerate" method="post" options={{ preserveScroll: true }} className="mt-3">
                                {({ processing }) => (
                                    <Button type="submit" size="sm" variant="outline" disabled={processing}>
                                        Regenerate URL &amp; password
                                    </Button>
                                )}
                            </Form>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">Enable the feed above to generate a URL.</p>
                    )}
                </div>

                {/* Export */}
                <div className="rounded-lg border p-4">
                    <h2 className="mb-2 text-sm font-medium text-muted-foreground">Download CSV</h2>
                    <p className="mb-3 text-xs text-muted-foreground">
                        Export the catalogue now — all categories, or only the ones you tick.
                    </p>
                    {categories.length > 0 && (
                        <div className="mb-3 flex flex-wrap gap-2">
                            {categories.map((c) => (
                                <label key={c.id} className="flex items-center gap-1.5 rounded-md border px-2 py-1 text-sm">
                                    <input type="checkbox" checked={selected.includes(c.id)} onChange={() => toggleCategory(c.id)} />
                                    {c.title}
                                </label>
                            ))}
                        </div>
                    )}
                    <Button type="button" variant="outline" onClick={() => {
 window.location.href = downloadUrl(); 
}}>
                        Download{selected.length > 0 ? ` (${selected.length} categories)` : ' (all)'}
                    </Button>
                </div>
            </div>
        </>
    );
}

FacebookCommerce.layout = {
    breadcrumbs: [{ title: 'Facebook Commerce', href: '/settings/facebook-commerce' }],
};
