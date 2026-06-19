import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Zone = {
    id: number;
    name: string;
    cost: number;
    status: boolean;
    position_order: number;
};

export default function ZoneForm({ zone }: { zone: Zone | null }) {
    const editing = Boolean(zone);
    const [status, setStatus] = useState<boolean>(zone?.status ?? true);

    const action = editing ? `/admin/shipping/zones/${zone!.id}` : '/admin/shipping/zones';

    return (
        <>
            <Head title={editing ? 'Edit shipping zone' : 'New shipping zone'} />
            <Form
                action={action}
                method={editing ? 'put' : 'post'}
                options={{ preserveScroll: true }}
                className="mx-auto w-full max-w-xl p-4 pb-24"
            >
                {({ processing, errors }) => (
                    <>
                        <h1 className="mb-4 text-lg font-semibold">
                            {editing ? 'Edit shipping zone' : 'New shipping zone'}
                        </h1>

                        <input type="hidden" name="status" value={status ? '1' : '0'} />

                        <div className="space-y-6 rounded-xl border bg-card p-4 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Zone name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={zone?.name ?? ''}
                                    required
                                    placeholder="e.g. Inside Dhaka"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="cost">Shipping cost (৳)</Label>
                                    <Input
                                        id="cost"
                                        name="cost"
                                        type="number"
                                        step="0.01"
                                        min={0}
                                        defaultValue={zone?.cost ?? 0}
                                        required
                                    />
                                    <InputError message={errors.cost} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="position_order">Sort order</Label>
                                    <Input
                                        id="position_order"
                                        name="position_order"
                                        type="number"
                                        min={0}
                                        defaultValue={zone?.position_order ?? 0}
                                    />
                                    <InputError message={errors.position_order} />
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="status"
                                    checked={status}
                                    onCheckedChange={(v) => setStatus(v === true)}
                                />
                                <Label htmlFor="status">Active (selectable at checkout)</Label>
                            </div>
                        </div>

                        <div className="sticky bottom-0 mt-4 flex items-center justify-end gap-2 border-t bg-background/95 py-3 backdrop-blur">
                            <Button variant="outline" asChild>
                                <Link href="/admin/shipping/zones">Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {editing ? 'Save changes' : 'Create zone'}
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

ZoneForm.layout = {
    breadcrumbs: [
        { title: 'Shipping zones', href: '/admin/shipping/zones' },
        { title: 'Edit', href: '#' },
    ],
};
