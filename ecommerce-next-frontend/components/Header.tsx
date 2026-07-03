import { Container } from "./Container";
import { HeaderNav } from "./HeaderNav";
import { HeaderSearch } from "./HeaderSearch";
import { Logo } from "./Logo";

export function Header({
  logoLight,
  logoDark,
  whatsapp,
  inquiryEnabled = true,
}: {
  logoLight?: string | null;
  logoDark?: string | null;
  whatsapp?: string | null;
  inquiryEnabled?: boolean;
}) {
  return (
    // `relative` anchors the mobile full-width search panel (dropped from the
    // search icon) to the header, so it spans edge-to-edge below it.
    <header className="relative w-full border-b border-border bg-background">
      <Container className="flex h-16 items-center gap-2 sm:h-20 sm:gap-4">
        {/* Left — logo */}
        <div className="flex flex-1 items-center">
          <Logo className="h-9 w-auto shrink-0 sm:h-11" lightUrl={logoLight} darkUrl={logoDark} />
        </div>

        {/* Centre — search box (desktop only), truly centred in the header */}
        <div className="hidden flex-1 justify-center md:flex">
          <HeaderSearch variant="desktop" whatsapp={whatsapp} inquiryEnabled={inquiryEnabled} />
        </div>

        {/* Right — mobile search icon + Home (desktop) + theme toggle */}
        <div className="flex flex-1 items-center justify-end gap-1 sm:gap-2">
          <HeaderSearch variant="mobile" whatsapp={whatsapp} inquiryEnabled={inquiryEnabled} />
          <HeaderNav />
        </div>
      </Container>
    </header>
  );
}
