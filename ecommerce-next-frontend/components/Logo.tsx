import Link from "next/link";
import { config } from "@/lib/config";

/**
 * Brand logo. Swaps automatically with the theme via CSS (.dark).
 * URLs come from admin-managed settings when available, otherwise fall back to
 * the static files in /public/logo/. See /public/logo/README.md.
 */
export function Logo({
  className = "h-8 w-auto",
  lightUrl,
  darkUrl,
  onClick,
}: {
  className?: string;
  lightUrl?: string | null;
  darkUrl?: string | null;
  onClick?: () => void;
}) {
  const light = lightUrl || "/logo/furnib-light.png";
  const dark = darkUrl || "/logo/furnib-dark.png";

  return (
    <Link
      href="/"
      onClick={onClick}
      aria-label={`${config.siteName} home`}
      className="inline-flex items-center"
    >
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={light} alt={config.siteName} className={`block dark:hidden ${className}`} />
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src={dark} alt={config.siteName} className={`hidden dark:block ${className}`} />
    </Link>
  );
}
