import { Head, router } from '@inertiajs/react';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';

type Staff = {
    id: number;
    name: string;
    email: string;
    role: string | null;
    is_active: boolean;
    is_self: boolean;
    is_owner: boolean;
};

export default function StaffIndex({
    staff,
    roles,
    canAssignOwner,
}: {
    staff: Staff[];
    roles: string[];
    canAssignOwner: boolean;
}) {
    // Owner is only offered when the actor may grant it.
    const selectableRoles = canAssignOwner ? roles : roles.filter((r) => r !== 'owner');

    const changeRole = (u: Staff, role: string) => {
        if (role === u.role) {
            return;
        }

        router.put(`/admin/staff/${u.id}/role`, { role }, { preserveScroll: true });
    };

    const toggleActive = (u: Staff) =>
        router.put(
            `/admin/staff/${u.id}/active`,
            { is_active: !u.is_active },
            { preserveScroll: true },
        );

    const StatusBadge = ({ active }: { active: boolean }) => (
        <span
            className={`inline-block rounded-md px-2 py-0.5 text-xs font-medium ${
                active
                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : 'bg-red-500/15 text-red-600 dark:text-red-400'
            }`}
        >
            {active ? 'Active' : 'Deactivated'}
        </span>
    );

    const RoleControl = ({ u }: { u: Staff }) => {
        // Owner rows and your own row are locked to avoid privilege escalation
        // and self-lockout (the server enforces this too).
        if (u.is_owner || u.is_self) {
            return (
                <span className="text-sm font-medium capitalize text-muted-foreground">
                    {u.role ?? '—'}
                    {u.is_owner && ' (owner)'}
                    {u.is_self && !u.is_owner && ' (you)'}
                </span>
            );
        }

        return (
            <select
                value={u.role ?? ''}
                onChange={(e) => changeRole(u, e.target.value)}
                className="rounded-md border border-input bg-background px-2 py-1 text-sm capitalize outline-none focus:border-ring"
            >
                {u.role && !selectableRoles.includes(u.role) && (
                    <option value={u.role}>{u.role}</option>
                )}
                {selectableRoles.map((r) => (
                    <option key={r} value={r}>
                        {r}
                    </option>
                ))}
            </select>
        );
    };

    const ActiveControl = ({ u }: { u: Staff }) => {
        if (u.is_owner || u.is_self) {
            return <StatusBadge active={u.is_active} />;
        }

        return (
            <div className="flex items-center gap-2">
                <StatusBadge active={u.is_active} />
                <Button
                    variant={u.is_active ? 'outline' : 'default'}
                    size="sm"
                    onClick={() => toggleActive(u)}
                >
                    {u.is_active ? 'Deactivate' : 'Activate'}
                </Button>
            </div>
        );
    };

    return (
        <>
            <Head title="Staff & roles" />
            <div className="mx-auto w-full max-w-5xl p-4">
                <PageHeader
                    title="Staff & roles"
                    description="Manage the role and access of existing staff. The owner account and your own row are protected."
                />

                {/* Desktop table */}
                <div className="hidden overflow-hidden rounded-xl border border-border md:block">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/40 text-left text-xs uppercase text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3">Name</th>
                                <th className="px-4 py-3">Role</th>
                                <th className="px-4 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {staff.map((u) => (
                                <tr key={u.id} className="border-t border-border">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{u.name}</div>
                                        <div className="text-xs text-muted-foreground">{u.email}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <RoleControl u={u} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <ActiveControl u={u} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {/* Mobile cards */}
                <div className="space-y-3 md:hidden">
                    {staff.map((u) => (
                        <div key={u.id} className="rounded-xl border border-border p-4">
                            <div className="font-medium">{u.name}</div>
                            <div className="text-xs text-muted-foreground">{u.email}</div>
                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                <RoleControl u={u} />
                                <ActiveControl u={u} />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff & roles', href: '/admin/staff' }],
};
