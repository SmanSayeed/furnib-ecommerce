import Link from "next/link";
import { config } from "@/lib/config";
import type { Badge, SiteSettings } from "@/lib/types";
import { whatsappGeneral } from "@/lib/whatsapp";
import { Container } from "./Container";
import { NewsletterForm } from "./NewsletterForm";
import { WhatsAppIcon } from "./WhatsAppIcon";

// Minimal inline brand glyphs so the footer needs no icon dependency.
const SOCIAL_ICONS: Record<string, React.ReactNode> = {
  facebook: (
    <path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.300000000000001c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12Z" />
  ),
  instagram: (
    <path d="M12 2.2c3.2 0 3.6 0 4.9.07 1.2.06 1.8.26 2.2.43.6.2 1 .47 1.4.9.43.4.7.8.9 1.4.17.4.37 1 .43 2.2.06 1.3.07 1.7.07 4.9s0 3.6-.07 4.9c-.06 1.2-.26 1.8-.43 2.2-.2.6-.47 1-.9 1.4-.4.43-.8.7-1.4.9-.4.17-1 .37-2.2.43-1.3.06-1.7.07-4.9.07s-3.6 0-4.9-.07c-1.2-.06-1.8-.26-2.2-.43-.6-.2-1-.47-1.4-.9-.43-.4-.7-.8-.9-1.4-.17-.4-.37-1-.43-2.2C2.2 15.6 2.2 15.2 2.2 12s0-3.6.07-4.9c.06-1.2.26-1.8.43-2.2.2-.6.47-1 .9-1.4.4-.43.8-.7 1.4-.9.4-.17 1-.37 2.2-.43C8.4 2.2 8.8 2.2 12 2.2Zm0 3.2A6.6 6.6 0 1 0 18.6 12 6.6 6.6 0 0 0 12 5.4Zm0 10.9A4.3 4.3 0 1 1 16.3 12 4.3 4.3 0 0 1 12 16.3Zm6.8-11.1a1.5 1.5 0 1 1-1.5-1.5 1.5 1.5 0 0 1 1.5 1.5Z" />
  ),
  youtube: (
    <path d="M23 12s0-3.2-.4-4.7a2.5 2.5 0 0 0-1.8-1.8C19.3 5 12 5 12 5s-7.3 0-8.8.5A2.5 2.5 0 0 0 1.4 7.3C1 8.8 1 12 1 12s0 3.2.4 4.7a2.5 2.5 0 0 0 1.8 1.8C4.7 19 12 19 12 19s7.3 0 8.8-.5a2.5 2.5 0 0 0 1.8-1.8C23 15.2 23 12 23 12Zm-13 3V9l5.2 3Z" />
  ),
  linkedin: (
    <path d="M20.4 3H3.6A.6.6 0 0 0 3 3.6v16.8a.6.6 0 0 0 .6.6h16.8a.6.6 0 0 0 .6-.6V3.6a.6.6 0 0 0-.6-.6ZM8.3 18.3H5.6V9.7h2.7v8.6ZM7 8.5a1.6 1.6 0 1 1 1.6-1.6A1.6 1.6 0 0 1 7 8.5Zm11.3 9.8h-2.7v-4.2c0-1 0-2.3-1.4-2.3s-1.6 1.1-1.6 2.2v4.3h-2.7V9.7h2.6v1.2h.04a2.9 2.9 0 0 1 2.6-1.4c2.8 0 3.3 1.8 3.3 4.2Z" />
  ),
  x: (
    <path d="M18.2 2.2h3.3l-7.2 8.2 8.5 11.3h-6.7l-5.2-6.9-6 6.9H1.6l7.7-8.8L1.1 2.2h6.8l4.7 6.3 5.6-6.3Zm-1.2 17.7h1.8L7.1 4.1H5.1L17 19.9Z" />
  ),
  pinterest: (
    <path d="M12 2a10 10 0 0 0-3.6 19.3c-.05-.8-.1-2 .1-2.9l1.2-5s-.3-.6-.3-1.5c0-1.4.8-2.5 1.9-2.5.9 0 1.3.7 1.3 1.5 0 .9-.6 2.2-.9 3.5-.2 1 .5 1.9 1.6 1.9 1.9 0 3.2-2.4 3.2-5.3 0-2.2-1.5-3.8-4.1-3.8a4.7 4.7 0 0 0-4.9 4.7c0 .9.3 1.5.7 2 .2.2.2.3.1.6l-.2.9c-.1.3-.3.4-.6.2-1.2-.5-1.8-1.9-1.8-3.5 0-2.6 2.2-5.7 6.5-5.7 3.5 0 5.8 2.5 5.8 5.2 0 3.5-2 6.2-4.9 6.2-1 0-1.9-.5-2.2-1.1l-.6 2.4c-.2.8-.7 1.7-1 2.3A10 10 0 1 0 12 2Z" />
  ),
  tiktok: (
    <path d="M16.5 2h-3v13.1a2.5 2.5 0 1 1-2.1-2.5v-3a5.5 5.5 0 1 0 5.1 5.5V8.7a7 7 0 0 0 4 1.3V7a4 4 0 0 1-4-4Z" />
  ),
};

// Inline contact glyphs — inherit the surrounding text colour via currentColor,
// so they always match the footer font (white / white-80) and hover states.
function PhoneIcon({ className = "" }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
      <path d="M6.62 10.79c1.44 2.83 3.76 5.15 6.59 6.59l2.2-2.2c.28-.28.68-.36 1.02-.25 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1C10.07 21 3 13.93 3 5c0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z" />
    </svg>
  );
}

function MailIcon({ className = "" }: { className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="currentColor" className={className} aria-hidden="true">
      <path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z" />
    </svg>
  );
}

function SocialIcon({ href, name }: { href: string; name: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={name}
      className="flex size-9 items-center justify-center rounded-full border border-white/40 text-white transition hover:border-white hover:bg-white hover:font-bold hover:text-[#e85d1f]!"
    >
      <svg viewBox="0 0 24 24" fill="currentColor" className="size-4" aria-hidden="true">
        {SOCIAL_ICONS[name]}
      </svg>
    </a>
  );
}

// One partner badge — dark logos sit on a white rounded card. Wrapped in a
// link only when a URL is set. Grayscale → colour on hover.
function PartnerBadge({ badge }: { badge: Badge }) {
  const card = (
    <div className="rounded-lg bg-white px-5 py-3">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src={badge.image_url as string}
        alt={badge.heading}
        className="h-10 w-auto grayscale transition duration-300 group-hover:grayscale-0"
      />
    </div>
  );
  return (
    <div className="flex flex-col items-center gap-2 text-center">
      {badge.heading && (
        <span className="text-xs font-semibold uppercase tracking-wider text-white/70">
          {badge.heading}
        </span>
      )}
      {badge.url ? (
        <a
          href={badge.url}
          target="_blank"
          rel="noopener noreferrer"
          className="group"
        >
          {card}
        </a>
      ) : (
        <div className="group">{card}</div>
      )}
    </div>
  );
}

export function Footer({ settings }: { settings?: SiteSettings | null }) {
  const name = settings?.site_name || config.contact.company;
  const address = settings?.contact.address || config.contact.address;
  const phone = settings?.contact.phone || config.contact.phone;
  const phone2 = settings?.contact.phone_2 || null;
  const email = settings?.contact.email || config.contact.email;
  // Single WhatsApp number, shared with the floating button — falls back to the
  // build config when the admin hasn't set one yet, so the link is never empty.
  const whatsappNumber = settings?.whatsapp || config.whatsapp;
  const showWhatsapp = settings?.whatsapp_buttons?.footer ?? true;
  const socials = settings?.socials ?? {};

  // "About Us" column — every published, footer-visible page the admin left on,
  // in display order (→ /p/{slug}). Managed from admin Footer details.
  const aboutLinks = (settings?.footer_pages ?? []).map((page) => ({
    label: page.title,
    url: `/p/${page.slug}`,
  }));

  const socialEntries = Object.entries(socials).filter(
    ([, url]) => typeof url === "string" && url !== "",
  );

  const hours = settings?.footer_contact?.hours || null;

  // Partner badges — each rendered only when enabled AND has an image.
  const badges = settings?.footer_badges ?? null;
  const memberBadge =
    badges?.member_of?.enabled && badges.member_of.image_url ? badges.member_of : null;
  const deliveryBadge =
    badges?.delivery_partner?.enabled && badges.delivery_partner.image_url
      ? badges.delivery_partner
      : null;
  const hasBadges = Boolean(memberBadge) || Boolean(deliveryBadge);

  // Admin-managed extra payment-methods banner (kept beside the gateway logo).
  const paymentBannerUrl = settings?.compliance?.payment_banner_url || null;

  return (
    <footer className="mt-20 bg-brand text-white">
      <Container className="py-14">
        {/* BAND 1 — four columns on desktop, two on tablet, stacked on mobile */}
        <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
          {/* 1. Brand + contact details */}
          <div>
            {settings?.logo_footer ? (
              // eslint-disable-next-line @next/next/no-img-element
              <img src={settings.logo_footer} alt={name} className="h-10 w-auto" />
            ) : (
              <h2 className="text-xl font-bold tracking-tight">{name}</h2>
            )}
            <div className="mt-4 space-y-3 text-sm text-white/80">
              {address && <p className="leading-relaxed">{address}</p>}
              {phone && (
                <a
                  href={`tel:${phone}`}
                  className="flex items-center gap-2.5 transition hover:text-white"
                >
                  <PhoneIcon className="size-4 shrink-0" />
                  <span className="hover:underline">{phone}</span>
                </a>
              )}
              {phone2 && (
                <a
                  href={`tel:${phone2}`}
                  className="flex items-center gap-2.5 transition hover:text-white"
                >
                  <PhoneIcon className="size-4 shrink-0" />
                  <span className="hover:underline">{phone2}</span>
                </a>
              )}
              {email && (
                <a
                  href={`mailto:${email}`}
                  className="flex items-center gap-2.5 transition hover:text-white"
                >
                  <MailIcon className="size-4 shrink-0" />
                  <span className="break-all hover:underline">{email}</span>
                </a>
              )}
            </div>
          </div>

          {/* 2. About Us — merged, deduped page links */}
          <nav aria-label="About Us">
            <h3 className="text-sm font-semibold uppercase tracking-wider text-white/70">
              About Us
            </h3>
            {aboutLinks.length > 0 && (
              <ul className="mt-4 space-y-2 text-sm">
                {aboutLinks.map((link) => (
                  <li key={link.url}>
                    <a
                      href={link.url}
                      className="inline-block text-white/80 transition-all duration-200 hover:translate-x-1 hover:text-white"
                    >
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            )}
          </nav>

          {/* 3. Contact Us — hours + three pill buttons */}
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-white/70">
              Contact Us
            </h3>
            {hours && <p className="mt-4 text-sm text-white/80">{hours}</p>}
            <div className="mt-4 flex flex-col gap-3">
              {phone && (
                <a
                  href={`tel:${phone}`}
                  className="rounded-full border border-white/40 px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-white hover:font-bold hover:text-[#e85d1f]!"
                >
                  Call Us
                </a>
              )}
              {showWhatsapp && (
                <a
                  href={whatsappGeneral(whatsappNumber)}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center justify-center gap-2 rounded-full border border-white/40 px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-white hover:font-bold hover:text-[#e85d1f]!"
                >
                  <WhatsAppIcon size={16} />+{whatsappNumber}
                </a>
              )}
              <Link
                href="/"
                className="rounded-full border border-white/40 px-5 py-2.5 text-center text-sm font-semibold text-white transition hover:bg-white hover:font-bold hover:text-[#e85d1f]!"
              >
                {name}
              </Link>
            </div>
          </div>

          {/* 4. Follow Us — socials + newsletter */}
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-white/70">
              Follow Us
            </h3>
            {socialEntries.length > 0 && (
              <div className="mt-4 flex flex-wrap gap-2">
                {socialEntries.map(([key, url]) => (
                  <SocialIcon key={key} href={url as string} name={key} />
                ))}
              </div>
            )}
            <p className="mt-4 text-sm text-white/80">
              Yes! Send me exclusive offers, unique gift ideas, and
              personalised tips for shopping on Furnib.
            </p>
            <div className="mt-4">
              <NewsletterForm />
            </div>
          </div>
        </div>

        {/* BAND 2 — partner badges (only when enabled + image present) */}
        {hasBadges && (
          <div className="mt-12 flex flex-wrap justify-center gap-10 border-t border-white/20 pt-10">
            {memberBadge && <PartnerBadge badge={memberBadge} />}
            {deliveryBadge && <PartnerBadge badge={deliveryBadge} />}
          </div>
        )}

        {/* BAND 3 — payment + copyright */}
        <div className="mt-12 flex flex-col items-center gap-4 border-t border-white/20 pt-8">
          <span className="text-xs uppercase tracking-wider text-white/70">
            Pay securely with
          </span>
          {/* On a white card so the multi-colour gateway logo stays legible.
              Much larger on desktop (~3×) where there is room. */}
          <div className="w-full max-w-md rounded-lg bg-white px-4 py-4 sm:max-w-2xl lg:max-w-6xl">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src="/sslcommerz.avif"
              alt="Pay with SSLCommerz"
              className="mx-auto h-auto w-full"
            />
          </div>

          {/* Admin-managed payment methods banner (#8) — beside the gateway logo. */}
          {paymentBannerUrl && (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={paymentBannerUrl}
              alt="Accepted payment methods"
              className="mx-auto h-auto w-full max-w-md rounded-lg sm:max-w-2xl lg:max-w-4xl"
            />
          )}
          <p className="text-xs text-white/70">
            © {settings?.site_name || config.siteName} — All rights reserved.
          </p>
        </div>
      </Container>
    </footer>
  );
}
