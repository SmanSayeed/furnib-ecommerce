import { router } from '@inertiajs/react';
import { useEffect } from 'react';

type DataLayerWindow = Window & { dataLayer?: Record<string, unknown>[] };

/**
 * Pushes a flashed `purchase` payload to the GTM dataLayer. The backend flashes
 * it once, when an order is first confirmed (Admin\OrderController). GTM (loaded
 * in app.blade.php) then fires the marketer's GA4 / Meta Pixel tags. The
 * server-side Meta CAPI copy shares the same `event_id`, so Meta de-duplicates.
 */
export function useFlashDataLayer(): void {
    useEffect(() => {
        return router.on('flash', (event) => {
            const flash = (event as CustomEvent).detail?.flash;
            const purchase = flash?.purchase as
                | ({ event: string } & Record<string, unknown>)
                | undefined;

            if (!purchase) {
                return;
            }

            const w = window as DataLayerWindow;
            w.dataLayer = w.dataLayer ?? [];
            const { event: name, ...rest } = purchase;
            // GA4 ecommerce requires clearing the previous object first.
            w.dataLayer.push({ ecommerce: null });
            w.dataLayer.push({ event: name, ...rest });
        });
    }, []);
}
