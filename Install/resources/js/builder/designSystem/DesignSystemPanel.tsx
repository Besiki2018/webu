/**
 * Part 11 — Design System UI panel.
 *
 * User can edit: primary color, fonts, spacing scale, radius, buttons.
 * Changes propagate across the site via CSS variables injected into the preview.
 * Part 13: "Regenerate Design System" runs AI and updates tokens; components adapt.
 */

import * as React from 'react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { DESIGN_TOKEN_FONTS, getDesignTokensAsCssVars } from '@/builder/designTokens';
import type { GeneratedDesignSystem } from '@/builder/ai/designSystemGenerator';

export interface DesignSystemOverrides {
  primaryColor?: string;
  headingFont?: string;
  bodyFont?: string;
  spacingScale?: 'tight' | 'moderate' | 'spacious';
  radiusScale?: 'sharp' | 'rounded' | 'pill';
}

const SPACING_OPTIONS: { value: DesignSystemOverrides['spacingScale']; labelKey: string; fallback: string }[] = [
  { value: 'tight', labelKey: 'Tight', fallback: 'მჭიდრო' },
  { value: 'moderate', labelKey: 'Moderate', fallback: 'საშუალო' },
  { value: 'spacious', labelKey: 'Spacious', fallback: 'ფართო' },
];

const RADIUS_OPTIONS: { value: DesignSystemOverrides['radiusScale']; labelKey: string; fallback: string }[] = [
  { value: 'sharp', labelKey: 'Sharp', fallback: 'მკვეთრი' },
  { value: 'rounded', labelKey: 'Rounded', fallback: 'მომრგვალებული' },
  { value: 'pill', labelKey: 'Pill', fallback: 'კაფსულა' },
];

const FONT_OPTIONS = [
  { value: 'sans', labelKey: 'Sans', fallback: 'Sans' },
  { value: 'serif', labelKey: 'Serif', fallback: 'Serif' },
  { value: 'heading', labelKey: 'Heading', fallback: 'სათაური' },
  { value: 'body', labelKey: 'Body', fallback: 'ტექსტი' },
  { value: 'mono', labelKey: 'Mono', fallback: 'Mono' },
];

export interface DesignSystemPanelProps {
  overrides: DesignSystemOverrides;
  onChange: (next: DesignSystemOverrides) => void;
  t?: (key: string) => string;
  /** Part 13: When provided, shows "Regenerate Design System" button. Call to run AI and update tokens. */
  onRegenerate?: () => void;
}

export function DesignSystemPanel({ overrides, onChange, t = (k) => k, onRegenerate }: DesignSystemPanelProps) {
  const set = (patch: Partial<DesignSystemOverrides>) => {
    onChange({ ...overrides, ...patch });
  };
  const tt = (key: string, fallback: string) => {
    const translated = t(key);
    return translated === key ? fallback : translated;
  };

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <Label className="text-xs font-medium">{tt('Primary color', 'მთავარი ფერი')}</Label>
        <div className="flex items-center gap-2">
          <Input
            type="color"
            value={overrides.primaryColor ?? '#0f172a'}
            onChange={(e) => set({ primaryColor: e.target.value })}
            className="h-9 w-14 cursor-pointer p-1"
          />
          <Input
            type="text"
            value={overrides.primaryColor ?? '#0f172a'}
            onChange={(e) => set({ primaryColor: e.target.value })}
            className="font-mono text-xs"
          />
        </div>
      </div>

      <div className="space-y-2">
        <Label className="text-xs font-medium">{tt('Heading font', 'სათაურის ფონტი')}</Label>
        <Select
          value={overrides.headingFont ?? 'heading'}
          onValueChange={(v) => set({ headingFont: v })}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {FONT_OPTIONS.map((o) => (
              <SelectItem key={o.value} value={o.value}>
                {tt(o.labelKey, o.fallback)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-2">
        <Label className="text-xs font-medium">{tt('Body font', 'ტექსტის ფონტი')}</Label>
        <Select
          value={overrides.bodyFont ?? 'body'}
          onValueChange={(v) => set({ bodyFont: v })}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {FONT_OPTIONS.map((o) => (
              <SelectItem key={o.value} value={o.value}>
                {tt(o.labelKey, o.fallback)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-2">
        <Label className="text-xs font-medium">{tt('Spacing scale', 'დაშორებების მასშტაბი')}</Label>
        <Select
          value={overrides.spacingScale ?? 'moderate'}
          onValueChange={(v) => set({ spacingScale: v as DesignSystemOverrides['spacingScale'] })}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {SPACING_OPTIONS.map((o) => (
              <SelectItem key={o.value} value={o.value!}>
                {tt(o.labelKey, o.fallback)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      <div className="space-y-2">
        <Label className="text-xs font-medium">{tt('Radius', 'მომრგვალება')}</Label>
        <Select
          value={overrides.radiusScale ?? 'rounded'}
          onValueChange={(v) => set({ radiusScale: v as DesignSystemOverrides['radiusScale'] })}
        >
          <SelectTrigger className="h-8 text-xs">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            {RADIUS_OPTIONS.map((o) => (
              <SelectItem key={o.value} value={o.value!}>
                {tt(o.labelKey, o.fallback)}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </div>

      {onRegenerate ? (
        <Button type="button" variant="outline" size="sm" className="w-full" onClick={onRegenerate}>
          {tt('Regenerate Design System', 'დიზაინ სისტემის თავიდან გენერაცია')}
        </Button>
      ) : null}

      <p className="text-[11px] text-muted-foreground">
        {tt('Changes apply to the whole site. Buttons use primary color and radius.', 'ცვლილებები მთელ საიტზე ვრცელდება. ღილაკები იყენებს მთავარ ფერს და მომრგვალებას.')}
      </p>
    </div>
  );
}

/**
 * Part 13 — Map a generated design system to panel overrides.
 * Used when user runs "Regenerate Design System": AI updates tokens, we apply to overrides so they propagate.
 */
export function generatedSystemToOverrides(system: GeneratedDesignSystem): DesignSystemOverrides {
  const headingFamily = system.typography?.fontFamily?.heading ?? system.typography?.fontFamily?.sans ?? '';
  const bodyFamily = system.typography?.fontFamily?.body ?? system.typography?.fontFamily?.sans ?? '';
  const fontKey = (val: string) => {
    if (!val) return 'body';
    const v = val.toLowerCase();
    if (v.includes('serif')) return 'serif';
    if (v.includes('mono')) return 'mono';
    return 'heading';
  };

  const mdSpacing = system.spacing?.md ?? '1rem';
  const spacingScale: DesignSystemOverrides['spacingScale'] =
    mdSpacing.includes('0.75') || parseFloat(mdSpacing) < 14 ? 'tight'
    : parseFloat(mdSpacing) > 18 || mdSpacing.includes('1.25') ? 'spacious'
    : 'moderate';

  const radiusVal = system.radius?.button ?? system.radius?.md ?? '0.375rem';
  const radiusScale: DesignSystemOverrides['radiusScale'] =
    radiusVal === '0' || radiusVal === '0px' ? 'sharp'
    : radiusVal.includes('9999') ? 'pill'
    : 'rounded';

  return {
    primaryColor: system.colors?.primary ?? undefined,
    headingFont: fontKey(headingFamily),
    bodyFont: fontKey(bodyFamily),
    spacingScale,
    radiusScale,
  };
}

/**
 * Build CSS custom properties for :root from base tokens + overrides.
 * Used to inject into the builder preview so design system changes propagate.
 */
export function buildDesignSystemCssVars(overrides: DesignSystemOverrides = {}): Record<string, string> {
  const base = getDesignTokensAsCssVars();
  const vars = { ...base };

  if (overrides.primaryColor) {
    vars['--color-primary'] = overrides.primaryColor;
  }

  if (overrides.headingFont && DESIGN_TOKEN_FONTS[overrides.headingFont as keyof typeof DESIGN_TOKEN_FONTS]) {
    vars['--font-heading'] = DESIGN_TOKEN_FONTS[overrides.headingFont as keyof typeof DESIGN_TOKEN_FONTS];
  }
  if (overrides.bodyFont && DESIGN_TOKEN_FONTS[overrides.bodyFont as keyof typeof DESIGN_TOKEN_FONTS]) {
    vars['--font-body'] = DESIGN_TOKEN_FONTS[overrides.bodyFont as keyof typeof DESIGN_TOKEN_FONTS];
  }

  // Spacing scale presets (simplified: we only override a couple of key vars for demo)
  if (overrides.spacingScale === 'tight') {
    vars['--spacing-md'] = '0.75rem';
    vars['--spacing-lg'] = '1rem';
  } else if (overrides.spacingScale === 'spacious') {
    vars['--spacing-md'] = '1.25rem';
    vars['--spacing-lg'] = '2rem';
  }

  if (overrides.radiusScale === 'sharp') {
    vars['--radius-md'] = '0';
    vars['--radius-lg'] = '0';
  } else if (overrides.radiusScale === 'pill') {
    vars['--radius-md'] = '9999px';
    vars['--radius-lg'] = '9999px';
  }

  return vars;
}

/**
 * Generate a <style> block content for :root with design system CSS variables.
 * Also applies tokens to builder sections and primary/secondary CTAs so the design is visible even when props are empty.
 */
export function designSystemVarsToStyleContent(overrides: DesignSystemOverrides = {}): string {
  const vars = buildDesignSystemCssVars(overrides);
  const declarations = Object.entries(vars)
    .map(([key, value]) => `  ${key}: ${value};`)
    .join('\n');
  return `:root {\n${declarations}\n}\n` + [
    '/* Apply tokens to sections and buttons so design system is visible in preview */',
    '[data-webu-section] { background-color: var(--color-background, #fff); color: var(--color-foreground, #0f172a); }',
    '.webu-hero__cta--primary, .webu-hero__cta--editorial, [data-webu-role="cta-primary"] { background: var(--color-primary); color: var(--color-primary-foreground); border-radius: var(--radius-md, 0.375rem); padding: var(--spacing-sm, 0.5rem) var(--spacing-md, 1rem); }',
    '.webu-hero__cta--secondary, .webu-hero__cta--editorial-secondary { background: var(--color-secondary); color: var(--color-secondary-foreground); border-radius: var(--radius-md, 0.375rem); }',
    'a.webu-hero__cta, button.webu-hero__cta { text-decoration: none; font-weight: 500; }',
  ].join('\n') + '\n';
}
