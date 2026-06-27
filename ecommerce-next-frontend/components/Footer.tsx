import { config } from "@/lib/config";
import type { SiteSettings } from "@/lib/types";
import { whatsappGeneral } from "@/lib/whatsapp";
import { NewsletterForm } from "./NewsletterForm";

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
};

function SocialIcon({ href, name }: { href: string; name: string }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      aria-label={name}
      className="flex size-9 items-center justify-center rounded-full border border-border text-muted transition hover:border-accent hover:text-accent"
    >
      <svg viewBox="0 0 24 24" fill="currentColor" className="size-4" aria-hidden="true">
        {SOCIAL_ICONS[name]}
      </svg>
    </a>
  );
}

export function Footer({ settings }: { settings?: SiteSettings | null }) {
  const name = settings?.site_name || config.contact.company;
  const address = settings?.contact.address || config.contact.address;
  const phone = settings?.contact.phone || config.contact.phone;
  const email = settings?.contact.email || config.contact.email;
  const socials = settings?.socials ?? {};
  const links = settings?.footer_links ?? [];
  const socialEntries = Object.entries(socials).filter(
    ([, url]) => typeof url === "string" && url !== "",
  );

  return (
    <footer className="mt-20 border-t border-border bg-surface">
      <div className="mx-auto max-w-6xl px-6 py-14">
        {/* Four columns on desktop, stacked on mobile */}
        <div className="grid grid-cols-1 gap-10 sm:grid-cols-2 lg:grid-cols-4">
          {/* Brand + socials */}
          <div>
            <h2 className="text-xl font-bold tracking-tight">{name}</h2>
            <p className="mt-3 text-sm leading-relaxed text-muted">
              {settings?.tagline ||
                "Elegant, refined furniture for modern living and professional spaces."}
            </p>
            {socialEntries.length > 0 && (
              <div className="mt-4 flex gap-2">
                {socialEntries.map(([key, url]) => (
                  <SocialIcon key={key} href={url as string} name={key} />
                ))}
              </div>
            )}
          </div>

          {/* Quick links */}
          {links.length > 0 && (
            <nav aria-label="Footer links">
              <h3 className="text-sm font-semibold uppercase tracking-wider text-muted/80">
                Quick links
              </h3>
              <ul className="mt-4 space-y-2 text-sm">
                {links.map((link) => (
                  <li key={`${link.label}-${link.url}`}>
                    <a
                      href={link.url}
                      className="text-muted transition hover:text-accent"
                    >
                      {link.label}
                    </a>
                  </li>
                ))}
              </ul>
            </nav>
          )}

          {/* Contact */}
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-muted/80">
              Contact
            </h3>
            <ul className="mt-4 space-y-2 text-sm text-muted">
              {address && <li>{address}</li>}
              {phone && (
                <li>
                  <a href={`tel:${phone}`} className="hover:text-accent">
                    {phone}
                  </a>
                </li>
              )}
              {email && (
                <li>
                  <a href={`mailto:${email}`} className="hover:text-accent">
                    {email}
                  </a>
                </li>
              )}
            </ul>
            <a
              href={whatsappGeneral(settings?.whatsapp)}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-4 inline-flex items-center gap-2 rounded-full bg-accent px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-accent-hover"
            >
              WhatsApp us
            </a>
          </div>

          {/* Newsletter */}
          <div>
            <h3 className="text-sm font-semibold uppercase tracking-wider text-muted/80">
              Newsletter
            </h3>
            <p className="mt-4 text-sm text-muted">
              Get new arrivals &amp; offers in your inbox.
            </p>
            <NewsletterForm />
          </div>
        </div>

        {/* Payment + copyright */}
        <div className="mt-12 flex flex-col items-center gap-4 border-t border-border pt-8">
          <span className="text-xs uppercase tracking-wider text-muted/70">
            Pay securely with
          </span>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src="/sslcommerz.avif"
            alt="Pay with SSLCommerz"
            className="h-auto w-full max-w-md"
          />
          <p className="text-xs text-muted/60">
            © {settings?.site_name || config.siteName} — All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  );
}
