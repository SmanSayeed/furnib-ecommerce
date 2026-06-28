import { Head, useForm } from '@inertiajs/react';
import { ShieldAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

export default function Maintenance({
    enabled,
    message,
}: {
    enabled: boolean;
    message: string;
}) {
    const form = useForm({ enabled, message: message ?? '' });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/admin/maintenance', { preserveScroll: true });
    };

    return (
        <>
            <Head title="Maintenance" />
            <h1 className="sr-only">Maintenance</h1>

            <div className="mx-auto w-full max-w-2xl space-y-6 p-1">
                <Heading
                    variant="small"
                    title="Maintenance lock"
                    description="Owner-only. Temporarily closes the storefront with a notice. This only flips a flag — it never deletes data or files, and is fully reversible."
                />

                <form
                    onSubmit={submit}
                    className="space-y-5 rounded-xl border border-border bg-card p-5"
                >
                    <div
                        className={`flex items-start gap-3 rounded-lg border p-4 ${
                            form.data.enabled
                                ? 'border-amber-500/40 bg-amber-500/10'
                                : 'border-border bg-muted/30'
                        }`}
                    >
                        <ShieldAlert
                            className={`mt-0.5 size-5 ${
                                form.data.enabled ? 'text-amber-600' : 'text-muted-foreground'
                            }`}
                        />
                        <div className="flex-1">
                            <p className="text-sm font-medium">
                                Storefront is currently{' '}
                                {form.data.enabled ? 'CLOSED (maintenance on)' : 'OPEN'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Toggle below and save to apply.
                            </p>
                        </div>
                        <label className="flex cursor-pointer items-center gap-2">
                            <input
                                type="checkbox"
                                checked={form.data.enabled}
                                onChange={(e) => form.setData('enabled', e.target.checked)}
                                className="size-4 accent-[#e85d1f]"
                            />
                            <span className="text-sm font-medium">Maintenance on</span>
                        </label>
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="message">Notice shown to visitors (optional)</Label>
                        <textarea
                            id="message"
                            value={form.data.message}
                            onChange={(e) => form.setData('message', e.target.value)}
                            rows={3}
                            maxLength={500}
                            placeholder="We're briefly closed for maintenance — back soon."
                            className="w-full resize-none rounded-md border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring"
                        />
                        <InputError message={form.errors.message} />
                    </div>

                    <div className="flex items-center gap-3">
                        <Button type="submit" disabled={form.processing}>
                            Save
                        </Button>
                        {form.recentlySuccessful && (
                            <span className="text-sm text-green-600">Saved.</span>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

Maintenance.layout = {
    breadcrumbs: [{ title: 'Maintenance', href: '/admin/maintenance' }],
};
