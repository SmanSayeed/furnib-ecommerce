import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

/**
 * Compact light/dark toggle for the admin topbar. Toggles between light and
 * dark (persisted via the shared appearance store). Icons swap purely via the
 * `.dark` class so there is no flash or hydration mismatch.
 */
export function AppearanceToggle({ className }: { className?: string }) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const isDark = resolvedAppearance === 'dark';

    return (
        <Button
            variant="ghost"
            size="icon"
            className={className}
            aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            title={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
            onClick={() => updateAppearance(isDark ? 'light' : 'dark')}
        >
            <Sun className="hidden size-5 dark:block" />
            <Moon className="block size-5 dark:hidden" />
        </Button>
    );
}
