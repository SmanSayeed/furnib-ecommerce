import { Container } from "./Container";
import { HeaderNav } from "./HeaderNav";
import { Logo } from "./Logo";

export function Header({
  logoLight,
  logoDark,
}: {
  logoLight?: string | null;
  logoDark?: string | null;
}) {
  return (
    <header className="w-full border-b border-border bg-background">
      <Container className="flex h-16 items-center justify-between sm:h-20">
        <Logo className="h-9 w-auto sm:h-11" lightUrl={logoLight} darkUrl={logoDark} />
        <HeaderNav />
      </Container>
    </header>
  );
}
