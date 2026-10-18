import type { ReactNode } from "react";

export function PageHeader({ title, description, actions }: { title: string; description?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="flex flex-wrap items-start justify-between gap-4">
      <div className="min-w-0">
        <h1 className="text-xl font-bold break-words">{title}</h1>
        {description && <p className="mt-1 text-sm text-muted">{description}</p>}
      </div>
      {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
    </div>
  );
}

/** Contêiner padrão das páginas de configuração. */
export function PageBody({ children }: { children: ReactNode }) {
  return <div className="mx-auto flex w-full max-w-[var(--layout-content-max)] flex-col gap-6 px-4 py-6 sm:px-6 lg:py-8">{children}</div>;
}
