/**
 * Global Layout System – single source for container widths and breakpoints.
 * Builder components and layout logic should reference this config.
 * CSS equivalents: design-system/tokens.css (--container-width-*, --breakpoint-*).
 */

export const LAYOUT_CONFIG = {
  /** Desktop: max container width (px) */
  containerDesktop: 1290,
  /** Laptop: max container width (px) */
  containerLaptop: 1140,
  /** Tablet: max container width (px) */
  containerTablet: 960,
  /** Mobile: use 100% width; padding from containerPaddingMobile */
  containerMobile: '100%',
  /** Mobile horizontal padding (px) */
  containerPaddingMobile: 16,

  /** Breakpoint: viewport width below this use laptop container (px) */
  breakpointLaptop: 1140,
  /** Breakpoint: viewport width below this use tablet container (px) */
  breakpointTablet: 1024,
  /** Breakpoint: viewport width below this use mobile rules (px) */
  breakpointMobile: 640,
} as const;

/** Layout structure: SiteLayout > GlobalHeader > PageSections (Section > Container > Component) > GlobalFooter */
export const LAYOUT_STRUCTURE = {
  /** Class applied to the global container wrapping section content */
  containerClass: 'webu-container',
  /** Class applied to each page section wrapper */
  sectionClass: 'webu-section',
  /** Header and footer are global layout components (stored in theme_settings.layout) */
  globalHeaderKey: 'webu_header_01',
  globalFooterKey: 'webu_footer_01',
} as const;
