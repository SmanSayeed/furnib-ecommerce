import { usePage } from '@inertiajs/react';

const FALLBACK_LIGHT = '/logo/furnib-light.png';
const FALLBACK_DARK = '/logo/furnib-dark.png';

type Props = {
    /**
     * `auto` swaps the light/dark logo with the active theme (page chrome).
     * `onDark` always renders the white logo — use it on the orange brand panel.
     */
    variant?: 'auto' | 'onDark';
    className?: string;
};

/**
 * Brand logo for auth/admin chrome. Prefers admin-managed logos shared via the
 * `branding` Inertia prop, falling back to the bundled static PNGs so the brand
 * always renders even before any logo is uploaded.
 */
export default function BrandMark({ variant = 'auto', className = 'h-9 w-auto' }: Props) {
    const { branding, name } = usePage().props;
    const alt = branding?.site_name || name || 'Furnib';

    const light = branding?.logo_light || FALLBACK_LIGHT;
    const dark = branding?.logo_dark || FALLBACK_DARK;

    if (variant === 'onDark') {
        return <img src={dark} alt={alt} className={className} />;
    }

    return (
        <>
            <img src={light} alt={alt} className={`${className} block dark:hidden`} />
            <img src={dark} alt={alt} className={`${className} hidden dark:block`} />
        </>
    );
}
