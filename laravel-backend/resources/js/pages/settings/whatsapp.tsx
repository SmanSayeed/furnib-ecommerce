import { Form, Head } from '@inertiajs/react';
import { MessageCircle } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type WhatsAppData = {
    whatsapp: string;
    floating_enabled: boolean;
    inquiry_enabled: boolean;
    footer_enabled: boolean;
};

const BUTTONS: { key: 'floating' | 'inquiry' | 'footer'; title: string; description: string }[] = [
    {
        key: 'floating',
        title: 'Floating button',
        description: 'The round WhatsApp bubble (desktop corner + mobile bottom bar).',
    },
    {
        key: 'inquiry',
        title: 'Product inquiry button',
        description: 'The green “Inquiry” button on product cards and product pages.',
    },
    {
        key: 'footer',
        title: 'Footer button',
        description: 'The WhatsApp number pill in the storefront footer “Contact Us”.',
    },
];

export default function WhatsAppSettings({ whatsapp }: { whatsapp: WhatsAppData }) {
    const enabled: Record<string, boolean> = {
        floating: whatsapp.floating_enabled,
        inquiry: whatsapp.inquiry_enabled,
        footer: whatsapp.footer_enabled,
    };

    return (
        <>
            <Head title="WhatsApp" />
            <h1 className="sr-only">WhatsApp</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="WhatsApp"
                    description="One WhatsApp number used across the storefront. Tick where it should appear."
                />

                <Form
                    action="/settings/whatsapp"
                    method="post"
                    options={{ preserveScroll: true }}
                    className="space-y-6"
                >
                    {({ processing, errors, recentlySuccessful }) => {
                        const errs = errors as Record<string, string | undefined>;

                        return (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="whatsapp">WhatsApp number</Label>
                                    <p className="text-xs text-muted-foreground">
                                        Digits only with country code, <strong>no +</strong> (e.g.
                                        8801748870651). This one number is used everywhere below.
                                    </p>
                                    <Input
                                        id="whatsapp"
                                        name="whatsapp"
                                        inputMode="numeric"
                                        defaultValue={whatsapp.whatsapp}
                                        placeholder="8801748870651"
                                    />
                                    <InputError message={errs.whatsapp} />
                                </div>

                                <div className="space-y-3 rounded-lg border border-border p-4">
                                    <div>
                                        <p className="flex items-center gap-2 text-sm font-medium">
                                            <MessageCircle className="size-4" /> Show the number on
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Untick a button to hide it from the storefront.
                                        </p>
                                    </div>

                                    {BUTTONS.map((b) => (
                                        <label
                                            key={b.key}
                                            className="flex items-start gap-3 rounded-md border border-border p-3"
                                        >
                                            <input
                                                type="checkbox"
                                                name={`${b.key}_enabled`}
                                                value="1"
                                                defaultChecked={enabled[b.key]}
                                                className="mt-0.5 size-4 rounded border-input"
                                            />
                                            <span>
                                                <span className="block text-sm font-medium">
                                                    {b.title}
                                                </span>
                                                <span className="block text-xs text-muted-foreground">
                                                    {b.description}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
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

WhatsAppSettings.layout = {
    breadcrumbs: [{ title: 'WhatsApp', href: '/settings/whatsapp' }],
};
