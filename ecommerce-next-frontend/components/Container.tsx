import type { ReactNode } from "react";

/**
 * Standard page wrapper. Caps content at 1600px and centres it with a
 * consistent gutter so the header logo, the product feed and the footer all
 * line up to the same left/right edges on every screen size.
 */
export function Container({
  className = "",
  children,
}: {
  className?: string;
  children: ReactNode;
}) {
  return (
    <div className={`mx-auto w-full max-w-[1600px] px-4 sm:px-6 lg:px-8 ${className}`}>
      {children}
    </div>
  );
}
