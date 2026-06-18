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
      <div className="mx-auto flex h-16 max-w-6xl items-center justify-between px-4 sm:px-6">
        <Logo className="h-7 w-auto sm:h-8" lightUrl={logoLight} darkUrl={logoDark} />
        <HeaderNav />
      </div>
    </header>
  );
}
