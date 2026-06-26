import { CalendarDays } from 'lucide-react';

/** Server-side date presets — must match App\Support\Lists\DateRange::PRESETS. */
export type DatePreset =
    | 'all'
    | 'today'
    | 'yesterday'
    | 'last_7'
    | 'this_month'
    | 'last_month'
    | 'custom';

export type DateRangeValue = {
    range: DatePreset;
    from: string;
    to: string;
};

const PRESET_LABELS: { value: DatePreset; label: string }[] = [
    { value: 'all', label: 'All time' },
    { value: 'today', label: 'Today' },
    { value: 'yesterday', label: 'Yesterday' },
    { value: 'last_7', label: 'Last 7 days' },
    { value: 'this_month', label: 'This month' },
    { value: 'last_month', label: 'Last month' },
    { value: 'custom', label: 'Custom range' },
];

const inputClass = 'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';

/**
 * Preset dropdown plus custom from/to date inputs (shown only for the `custom`
 * preset). Emits the full {range, from, to} so the page can push it onto the
 * URL query string — the single source of truth for list state.
 */
export function DateRangeFilter({
    value,
    onChange,
}: {
    value: DateRangeValue;
    onChange: (next: DateRangeValue) => void;
}) {
    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div className="relative">
                <CalendarDays className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <select
                    aria-label="Filter by date"
                    value={value.range}
                    onChange={(e) =>
                        onChange({ ...value, range: e.target.value as DatePreset })
                    }
                    className={`${inputClass} pl-9`}
                >
                    {PRESET_LABELS.map((p) => (
                        <option key={p.value} value={p.value}>
                            {p.label}
                        </option>
                    ))}
                </select>
            </div>

            {value.range === 'custom' && (
                <div className="flex items-center gap-2">
                    <input
                        type="date"
                        aria-label="From date"
                        value={value.from}
                        max={value.to || undefined}
                        onChange={(e) => onChange({ ...value, from: e.target.value })}
                        className={inputClass}
                    />
                    <span className="text-sm text-muted-foreground">→</span>
                    <input
                        type="date"
                        aria-label="To date"
                        value={value.to}
                        min={value.from || undefined}
                        onChange={(e) => onChange({ ...value, to: e.target.value })}
                        className={inputClass}
                    />
                </div>
            )}
        </div>
    );
}
