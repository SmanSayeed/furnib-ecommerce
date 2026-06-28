<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Read-only audit log. Gated by the `audit.view` permission at the route.
 * There is intentionally no create/update/delete path. The activity log only
 * stores non-sensitive fields (models opt in via getActivitylogOptions).
 */
final class AuditLogController
{
    public function index(): Response
    {
        $activities = Activity::query()
            ->with('causer')
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(function (Activity $a): array {
                $causer = $a->causer;

                return [
                    'id' => $a->id,
                    'log_name' => $a->log_name,
                    'event' => $a->event,
                    'description' => $a->description,
                    'subject' => $a->subject_type !== null
                        ? class_basename($a->subject_type).' #'.$a->subject_id
                        : null,
                    'causer' => $causer instanceof User ? $causer->name : 'System',
                    'at' => $a->created_at?->toDateTimeString(),
                ];
            })
            ->all();

        return Inertia::render('system/audit', ['activities' => $activities]);
    }
}
