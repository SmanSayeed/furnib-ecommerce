import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Social = {
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
};

const PLATFORMS: {
    key: keyof Social;
    enabledKey: keyof Social;
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

export default function FooterSocial({ social }: { social: Social }) {
    return (
        <>
            <Head title="Footer social icons" />
            <h1 className="sr-only">Footer social icons</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Footer — Follow us"
                    description="Social links shown in the storefront footer. Full https:// URLs; untick “Show” to hide a button without losing its link."
                />

                <Form
                    action="/settings/footer/social"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {PLATFORMS.map((p) => (
                                    <div key={p.key} className="grid gap-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor={p.key}>{p.label}</Label>
                                            <input type="hidden" name={p.enabledKey} value="0" />
                                            <label className="flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground">
                                                <input
                                                    type="checkbox"
                                                    name={p.enabledKey}
                                                    value="1"
                                                    defaultChecked={social[p.enabledKey] as boolean}
                                                    className="size-4 accent-[#e85d1f]"
                                                />
                                                Show
                                            </label>
                                        </div>
                                        <Input
                                            id={p.key}
                                            name={p.key}
                                            type="url"
                                            defaultValue={social[p.key] as string}
                                            placeholder={p.placeholder}
                                        />
                                        <InputError
                                            message={
                                                (errors as Record<string, string | undefined>)[p.key]
                                            }
                                        />
                                    </div>
                                ))}
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Save</Button>
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

FooterSocial.layout = {
    breadcrumbs: [{ title: 'Footer social icons', href: '/settings/footer/social' }],
};
