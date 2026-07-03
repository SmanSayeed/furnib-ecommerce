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
    <header className="w-full border-b border-border bg-background">
      <Container className="flex h-16 items-center gap-3 sm:h-20 sm:gap-4">
        <Logo className="h-9 w-auto shrink-0 sm:h-11" lightUrl={logoLight} darkUrl={logoDark} />
        <HeaderSearch whatsapp={whatsapp} inquiryEnabled={inquiryEnabled} />
        <HeaderNav />
      </Container>
    </header>
  );
}
