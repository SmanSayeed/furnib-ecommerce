import { Link, usePage } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import BrandMark from '@/components/brand-mark';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({ children, title, description }: AuthLayoutProps) {
    const { branding, name } = usePage().props;
    const siteName = branding?.site_name || name || 'Furnib';
    const tagline = branding?.tagline || 'Premium furniture, beautifully managed.';

    return (
        <div className="grid min-h-svh lg:grid-cols-[1.05fr_1fr]">
            {/* Brand panel — desktop only */}
            <div className="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex xl:p-14">
                <div
                    className="absolute inset-0"
                    style={{
                        backgroundImage:
                            'linear-gradient(135deg, #f97316 0%, #ea580c 45%, #c2410c 100%)',
                    }}
                />
                {/* Soft decorative glows */}
                <div className="pointer-events-none absolute -left-24 -top-24 size-96 rounded-full bg-white/15 blur-3xl" />
                <div className="pointer-events-none absolute -bottom-32 -right-16 size-[28rem] rounded-full bg-amber-300/20 blur-3xl" />
                <div
                    className="pointer-events-none absolute inset-0 opacity-[0.07]"
                    style={{
                        backgroundImage:
                            'radial-gradient(circle at 1px 1px, #fff 1px, transparent 0)',
                        backgroundSize: '22px 22px',
                    }}
                />

                <Link href={home()} className="relative z-10 inline-flex items-center gap-3">
                    <span className="flex h-12 items-center rounded-xl bg-white/15 px-3 backdrop-blur-sm ring-1 ring-white/25">
                        <BrandMark variant="onDark" className="h-7 w-auto" />
                    </span>
                    <span className="text-lg font-semibold tracking-tight">{siteName}</span>
                </Link>

                <div className="relative z-10 max-w-md">
                    <h2 className="text-3xl font-semibold leading-tight tracking-tight xl:text-4xl">
                        {tagline}
                    </h2>
                    <p className="mt-4 text-sm leading-relaxed text-white/80">
                        Sign in to your {siteName} admin dashboard to manage products, categories,
                        orders, and your storefront — all in one place.
                    </p>
                </div>

                <div className="relative z-10 flex items-center gap-2 text-sm text-white/75">
                    <ShieldCheck className="size-4" />
                    <span>Secure, role-based access</span>
                </div>
            </div>

            {/* Form panel */}
            <div className="flex flex-col items-center justify-center bg-background px-6 py-10 sm:px-10">
                <div className="w-full max-w-sm">
                    {/* Mobile brand (panel hidden) */}
                    <Link
                        href={home()}
                        className="mb-8 flex items-center justify-center gap-2 lg:hidden"
                    >
                        <BrandMark variant="auto" className="h-9 w-auto" />
                        <span className="text-lg font-semibold tracking-tight">{siteName}</span>
                    </Link>

                    <div className="mb-6 flex flex-col gap-1.5 text-center sm:text-left">
                        <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
                        {description && (
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>

                    {children}
                </div>
            </div>
        </div>
    );
}
