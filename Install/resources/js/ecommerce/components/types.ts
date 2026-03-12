export interface SectionInjectedProps {
  basePath?: string;
  pageRoute?: string;
}

export function path(basePath: string | undefined, pathname: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const p = pathname.startsWith('/') ? pathname : `/${pathname}`;
  return base ? `${base}${p}` : p;
}
