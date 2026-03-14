import type { DesignQualityAnalysisContext, DesignQualityAnalyzerResult, DesignQualitySuggestion } from './types'
import { getValueAtPath } from '../../state/sectionProps'

interface RgbColor {
  r: number
  g: number
  b: number
}

function normalizeHex(value: string): string | null {
  const trimmed = value.trim()
  if (!trimmed.startsWith('#')) {
    return null
  }

  if (/^#[0-9a-f]{3}$/i.test(trimmed)) {
    return `#${trimmed.slice(1).split('').map((part) => part + part).join('')}`
  }

  if (/^#[0-9a-f]{6}$/i.test(trimmed)) {
    return trimmed.toLowerCase()
  }

  return null
}

function parseHexColor(value: unknown): RgbColor | null {
  if (typeof value !== 'string') {
    return null
  }

  const normalized = normalizeHex(value)
  if (!normalized) {
    return null
  }

  const raw = normalized.slice(1)

  return {
    r: Number.parseInt(raw.slice(0, 2), 16),
    g: Number.parseInt(raw.slice(2, 4), 16),
    b: Number.parseInt(raw.slice(4, 6), 16),
  }
}

function toChannel(value: number): number {
  const normalized = value / 255
  return normalized <= 0.03928
    ? normalized / 12.92
    : ((normalized + 0.055) / 1.055) ** 2.4
}

function luminance(color: RgbColor): number {
  return (0.2126 * toChannel(color.r)) + (0.7152 * toChannel(color.g)) + (0.0722 * toChannel(color.b))
}

function contrastRatio(foreground: RgbColor, background: RgbColor): number {
  const light = Math.max(luminance(foreground), luminance(background))
  const dark = Math.min(luminance(foreground), luminance(background))
  return (light + 0.05) / (dark + 0.05)
}

function resolveColors(section: DesignQualityAnalysisContext['sections'][number]): { foreground: string | null, background: string | null } {
  const foreground = getValueAtPath(section.resolvedProps, 'textColor')
    ?? getValueAtPath(section.resolvedProps, 'text_color')
    ?? '#0f172a'
  const background = getValueAtPath(section.resolvedProps, 'backgroundColor')
    ?? getValueAtPath(section.resolvedProps, 'background_color')
    ?? '#ffffff'

  return {
    foreground: typeof foreground === 'string' ? foreground : null,
    background: typeof background === 'string' ? background : null,
  }
}

function buildSuggestion(
  section: DesignQualityAnalysisContext['sections'][number],
  action: DesignQualitySuggestion['action'],
  path: string,
  value: string,
  detail: string,
): DesignQualitySuggestion {
  return {
    category: 'contrast',
    target: section.nodeId,
    sectionIndex: section.sectionIndex,
    action,
    value,
    path,
    detail,
  }
}

function supportsField(section: DesignQualityAnalysisContext['sections'][number], ...paths: string[]): string | null {
  return paths.find((path) => section.schemaFieldPaths.has(path)) ?? null
}

function isVeryLight(color: RgbColor): boolean {
  return luminance(color) >= 0.72
}

function isVeryDark(color: RgbColor): boolean {
  return luminance(color) <= 0.18
}

export function analyzeContrast(context: DesignQualityAnalysisContext): DesignQualityAnalyzerResult {
  const issues: string[] = []
  const suggestions: DesignQualitySuggestion[] = []
  let score = 100

  context.sections.forEach((sectionContext) => {
    const { foreground, background } = resolveColors(sectionContext)
    const parsedForeground = parseHexColor(foreground)
    const parsedBackground = parseHexColor(background)

    if (!parsedForeground || !parsedBackground) {
      return
    }

    const ratio = contrastRatio(parsedForeground, parsedBackground)
    const requiredRatio = sectionContext.sectionType === 'cta'
      ? 5
      : sectionContext.sectionType === 'hero'
        ? 4.7
        : 4.5
    const textPath = supportsField(sectionContext, 'textColor', 'text_color')
    const backgroundPath = supportsField(sectionContext, 'backgroundColor', 'background_color')
    const lowContrast = ratio < requiredRatio
    const faintOnLight = isVeryLight(parsedForeground) && isVeryLight(parsedBackground)
    const muddyOnDark = isVeryDark(parsedForeground) && isVeryDark(parsedBackground)

    if (!lowContrast && !faintOnLight && !muddyOnDark) {
      return
    }

    if (faintOnLight) {
      issues.push(`${sectionContext.sectionType} muted text is too faint`)
      score -= 10
    } else if (muddyOnDark) {
      issues.push(`${sectionContext.sectionType} text disappears into a dark surface`)
      score -= 10
    } else {
      issues.push(`${sectionContext.sectionType} text contrast is too low`)
      score -= sectionContext.sectionType === 'cta' ? 12 : 8
    }

    if (textPath) {
      suggestions.push(buildSuggestion(
        sectionContext,
        'set_text_color',
        textPath,
        isVeryDark(parsedBackground) ? '#ffffff' : '#0f172a',
        'Strengthen text contrast against the current background.',
      ))
    }

    if ((sectionContext.sectionType === 'cta' || sectionContext.sectionType === 'hero') && backgroundPath) {
      suggestions.push(buildSuggestion(
        sectionContext,
        'set_background_color',
        backgroundPath,
        sectionContext.sectionType === 'cta' ? '#1d4ed8' : '#0f172a',
        sectionContext.sectionType === 'cta'
          ? 'Give the CTA section a higher-contrast accent background.'
          : 'Give the hero a clearer high-contrast surface.',
      ))
    }

    if ((lowContrast || faintOnLight || muddyOnDark) && sectionContext.variantOptions.length > 1) {
      suggestions.push({
        category: 'contrast',
        target: sectionContext.nodeId,
        sectionIndex: sectionContext.sectionIndex,
        action: 'swap_variant',
        detail: 'Switch to a variant with stronger visual separation.',
      })
    }
  })

  return {
    score: Math.max(30, score),
    issues,
    suggestions,
  }
}
