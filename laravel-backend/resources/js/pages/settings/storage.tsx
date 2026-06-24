import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Storage = {
    driver: 'server' | 'r2';
    r2_endpoint: string | null;
    r2_bucket: string | null;
    r2_url: string | null;
    r2_region: string | null;
    r2_access_key_set: boolean;
    r2_secret_key_set: boolean;
};

export default function StorageSettings({ storage }: { storage: Storage }) {
    const [driver, setDriver] = useState<'server' | 'r2'>(storage.driver ?? 'server');

    return (
        <>
            <Head title="Storage" />
            <h1 className="sr-only">Storage</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Media storage"
                    description="Choose where product & category images are stored. Cloudflare R2 serves images from a fast public URL; the access keys are stored encrypted."
                />

                <Form
                    action="/settings/storage"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-3">
                                <Label>Active driver</Label>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    <label
                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 ${
                                            driver === 'server'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="driver"
                                            value="server"
                                            checked={driver === 'server'}
                                            onChange={() => setDriver('server')}
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="block text-sm font-medium">Server disk</span>
                                            <span className="block text-xs text-muted-foreground">
                                                Stores files on the app container (ephemeral — not
                                                recommended for production).
                                            </span>
                                        </span>
                                    </label>

                                    <label
                                        className={`flex cursor-pointer items-start gap-3 rounded-lg border p-4 ${
                                            driver === 'r2'
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border'
                                        }`}
                                    >
                                        <input
                                            type="radio"
                                            name="driver"
                                            value="r2"
                                            checked={driver === 'r2'}
                                            onChange={() => setDriver('r2')}
                                            className="mt-1"
                                        />
                                        <span>
                                            <span className="block text-sm font-medium">Cloudflare R2</span>
                                            <span className="block text-xs text-muted-foreground">
                                                S3-compatible object storage with a public CDN URL
                                                (recommended).
                                            </span>
                                        </span>
                                    </label>
                                </div>
                                <InputError message={errors.driver} />
                            </div>

                            <div className="space-y-4 rounded-lg border border-border p-4">
                                <p className="text-sm font-medium">Cloudflare R2 connection</p>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="r2_bucket">Bucket</Label>
                                        <Input
                                            id="r2_bucket"
                                            name="r2_bucket"
                                            defaultValue={storage.r2_bucket ?? ''}
                                            placeholder="furnib-ecommerce"
                                        />
                                        <InputError message={errors.r2_bucket} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="r2_region">Region</Label>
                                        <Input
                                            id="r2_region"
                                            name="r2_region"
                                            defaultValue={storage.r2_region ?? 'auto'}
                                            placeholder="auto"
                                        />
                                        <InputError message={errors.r2_region} />
                                    </div>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="r2_endpoint">Endpoint</Label>
                                    <Input
                                        id="r2_endpoint"
                                        name="r2_endpoint"
                                        defaultValue={storage.r2_endpoint ?? ''}
                                        placeholder="https://<account>.r2.cloudflarestorage.com"
                                    />
                                    <InputError message={errors.r2_endpoint} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="r2_url">Public URL</Label>
                                    <Input
                                        id="r2_url"
                                        name="r2_url"
                                        defaultValue={storage.r2_url ?? ''}
                                        placeholder="https://pub-xxxx.r2.dev"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        The public base URL images are served from (r2.dev or a custom domain).
                                    </p>
                                    <InputError message={errors.r2_url} />
                                </div>

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label htmlFor="r2_access_key">
                                            Access Key ID{' '}
                                            <span className="text-muted-foreground">
                                                {storage.r2_access_key_set ? '(set — blank keeps)' : '(not set)'}
                                            </span>
                                        </Label>
                                        <Input
                                            id="r2_access_key"
                                            name="r2_access_key"
                                            type="password"
                                            autoComplete="off"
                                            placeholder="••••••••"
                                        />
                                        <InputError message={errors.r2_access_key} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="r2_secret_key">
                                            Secret Access Key{' '}
                                            <span className="text-muted-foreground">
                                                {storage.r2_secret_key_set ? '(set — blank keeps)' : '(not set)'}
                                            </span>
                                        </Label>
                                        <Input
                                            id="r2_secret_key"
                                            name="r2_secret_key"
                                            type="password"
                                            autoComplete="off"
                                            placeholder="••••••••"
                                        />
                                        <InputError message={errors.r2_secret_key} />
                                    </div>
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Keys are stored encrypted and never sent back to the browser. Leave blank
                                    to keep the saved value.
                                </p>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing} data-test="save-storage-settings">
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

StorageSettings.layout = {
    breadcrumbs: [
        {
            title: 'Storage',
            href: '/settings/storage',
        },
    ],
};
