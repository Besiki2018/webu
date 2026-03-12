/**
 * Types for the Webu Design Intelligence System.
 * Used by AI when generating or modifying layouts and components.
 */

export interface ContainerWidths {
  desktop: string;
  tablet: string;
  mobile: string;
}

export interface SpacingRule {
  top: string;
  bottom: string;
}

export interface TypographyScale {
  h1: { desktop: string; tablet: string; mobile: string };
  h2: { desktop: string; tablet: string; mobile: string };
  h3: string;
  paragraph: string;
  lineHeight: string;
}

export interface GridSystem {
  desktopColumns: number;
  featureColumns: readonly number[] | number | readonly [number, number];
  productColumns: number;
  mobileColumns: number;
}

export interface Breakpoints {
  tablet: string;
  mobile: string;
}

export interface SectionComposition {
  landing: readonly string[];
  business: readonly string[];
}

export interface DesignRulesSpec {
  containers: ContainerWidths;
  spacing: {
    section: SpacingRule;
    medium: SpacingRule;
    small: SpacingRule;
  };
  typography: TypographyScale;
  grid: GridSystem;
  breakpoints: Breakpoints;
  sectionStructures: SectionComposition;
}
