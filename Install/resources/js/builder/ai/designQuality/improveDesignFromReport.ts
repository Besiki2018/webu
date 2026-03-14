import { cloneData } from '../../runtime/clone'
import { setValueAtPath } from '../../state/sectionProps'
import { buildSectionContexts } from './buildDesignQualityReport'
import type {
  DesignQualitySectionContext,
  DesignQualitySuggestion,
  ImproveDesignFromReportInput,
  ImproveDesignFromReportResult,
} from './types'

const SPACING_TOKENS = [32, 48, 64, 72, 80, 96, 120] as const
const MAX_WIDTH_TOKENS = [640, 720, 760, 820, 960, 1200] as const
const COLOR_TOKENS = ['#ffffff', '#0f172a', '#1d4ed8', '#f8fafc', '#e2e8f0', '#1e293b'] as const

function clampToken(value: number, tokens: readonly number[]): number {
  return tokens.reduce((closest, token) => (
    Math.abs(token - value) < Math.abs(closest - value) ? token : closest
  ), tokens[0]!)
}

function clampColor(value: string): string {
  return COLOR_TOKENS.includes(value as (typeof COLOR_TOKENS)[number]) ? value : COLOR_TOKENS[0]
}

function supportsPath(section: DesignQualitySectionContext, path: string): boolean {
  return section.schemaFieldPaths.has(path)
}

function resolveSupportedPath(section: DesignQualitySectionContext, ...paths: string[]): string | null {
  return paths.find((path) => supportsPath(section, path)) ?? null
}

function resolvePrimaryCtaLabel(input: ImproveDesignFromReportInput): string {
  const signals = [
    input.blueprint.projectType,
    input.blueprint.businessType,
    input.blueprint.pageGoal,
    input.blueprint.audience,
    ...input.blueprint.styleKeywords,
  ].join(' ').toLowerCase()

  if (/(vet|veterin|pet|animal)/.test(signals)) {
    return 'Book a visit'
  }

  if (/(restaurant|dining|menu|reservation)/.test(signals)) {
    return 'Reserve a table'
  }

  if (input.blueprint.projectType === 'ecommerce' || /(shop|store|product|buy)/.test(signals)) {
    return 'Shop the collection'
  }

  if (input.blueprint.projectType === 'saas' || /(software|platform|dashboard|automation)/.test(signals)) {
    return 'Book demo'
  }

  if (input.blueprint.projectType === 'portfolio' || /(portfolio|designer|photographer|creative)/.test(signals)) {
    return 'View the work'
  }

  return 'Book consultation'
}

function applyPropSuggestion(
  section: ImproveDesignFromReportResult['siteSections'][number],
  sectionContext: DesignQualitySectionContext,
  suggestion: DesignQualitySuggestion,
): ImproveDesignFromReportResult['siteSections'][number] {
  if (!suggestion.path) {
    return section
  }

  if (!supportsPath(sectionContext, suggestion.path)) {
    if (suggestion.action === 'increase_padding_y' && supportsPath(sectionContext, 'advanced.padding_top') && supportsPath(sectionContext, 'advanced.padding_bottom')) {
      const token = clampToken(Number(suggestion.value ?? 64), SPACING_TOKENS)
      return {
        ...section,
        props: setValueAtPath(
          setValueAtPath(section.props ?? {}, 'advanced.padding_top', token),
          'advanced.padding_bottom',
          token,
        ),
      }
    }
    if (suggestion.action === 'decrease_padding_y' && supportsPath(sectionContext, 'advanced.padding_top') && supportsPath(sectionContext, 'advanced.padding_bottom')) {
      const token = clampToken(Number(suggestion.value ?? 72), SPACING_TOKENS)
      return {
        ...section,
        props: setValueAtPath(
          setValueAtPath(section.props ?? {}, 'advanced.padding_top', token),
          'advanced.padding_bottom',
          token,
        ),
      }
    }
    return section
  }

  if (suggestion.action === 'increase_padding_y' || suggestion.action === 'decrease_padding_y' || suggestion.action === 'set_gap') {
    const token = clampToken(Number(suggestion.value ?? 64), SPACING_TOKENS)
    if (suggestion.action === 'increase_padding_y' && suggestion.path === 'advanced.padding_top' && supportsPath(sectionContext, 'advanced.padding_bottom')) {
      return {
        ...section,
        props: setValueAtPath(
          setValueAtPath(section.props ?? {}, 'advanced.padding_top', token),
          'advanced.padding_bottom',
          token,
        ),
      }
    }
    if (suggestion.action === 'decrease_padding_y' && suggestion.path === 'advanced.padding_top' && supportsPath(sectionContext, 'advanced.padding_bottom')) {
      return {
        ...section,
        props: setValueAtPath(
          setValueAtPath(section.props ?? {}, 'advanced.padding_top', token),
          'advanced.padding_bottom',
          token,
        ),
      }
    }

    return {
      ...section,
      props: setValueAtPath(section.props ?? {}, suggestion.path, token),
    }
  }

  if (suggestion.action === 'set_max_width') {
    const token = clampToken(Number(suggestion.value ?? 760), MAX_WIDTH_TOKENS)
    return {
      ...section,
      props: setValueAtPath(section.props ?? {}, suggestion.path, token),
    }
  }

  if (suggestion.action === 'set_text_color' || suggestion.action === 'set_background_color') {
    const path = suggestion.action === 'set_text_color'
      ? (resolveSupportedPath(sectionContext, suggestion.path, 'textColor', 'text_color') ?? suggestion.path)
      : (resolveSupportedPath(sectionContext, suggestion.path, 'backgroundColor', 'background_color') ?? suggestion.path)
    if (!path) {
      return section
    }

    let nextProps = setValueAtPath(section.props ?? {}, path, clampColor(String(suggestion.value ?? '#0f172a')))
    if (
      suggestion.action === 'set_background_color'
      && /#0f172a|#1d4ed8|#1e293b/i.test(String(suggestion.value ?? ''))
    ) {
      const textPath = resolveSupportedPath(sectionContext, 'textColor', 'text_color')
      if (textPath) {
        nextProps = setValueAtPath(nextProps, textPath, '#ffffff')
      }
    }

    return {
      ...section,
      props: nextProps,
    }
  }

  return section
}

function applyVariantSuggestion(
  section: ImproveDesignFromReportResult['siteSections'][number],
  sectionContext: DesignQualitySectionContext,
): ImproveDesignFromReportResult['siteSections'][number] {
  if (sectionContext.variantOptions.length < 2) {
    return section
  }

  const currentIndex = sectionContext.variantOptions.findIndex((variant) => variant === section.variant)
  const nextVariant = sectionContext.variantOptions.find((variant, index) => (
    variant !== section.variant && index > currentIndex
  )) ?? sectionContext.variantOptions.find((variant) => variant !== section.variant)
  if (!nextVariant) {
    return section
  }

  return {
    ...section,
    variant: nextVariant,
    props: setValueAtPath(section.props ?? {}, 'variant', nextVariant),
  }
}

function applyCtaLabelSuggestion(
  section: ImproveDesignFromReportResult['siteSections'][number],
  sectionContext: DesignQualitySectionContext,
  input: ImproveDesignFromReportInput,
): ImproveDesignFromReportResult['siteSections'][number] {
  const label = resolvePrimaryCtaLabel(input)
  const candidatePaths = ['buttonText', 'buttonLabel', 'ctaLabel', 'submit_label']
  for (const path of candidatePaths) {
    if (!supportsPath(sectionContext, path)) {
      continue
    }

    return {
      ...section,
      props: setValueAtPath(section.props ?? {}, path, label),
    }
  }

  return section
}

function applySectionSuggestion(
  sections: ImproveDesignFromReportResult['siteSections'],
  contexts: DesignQualitySectionContext[],
  suggestion: DesignQualitySuggestion,
  input: ImproveDesignFromReportInput,
): ImproveDesignFromReportResult['siteSections'] {
  if (typeof suggestion.sectionIndex !== 'number' || suggestion.sectionIndex < 0 || suggestion.sectionIndex >= sections.length) {
    return sections
  }

  if (suggestion.action === 'promote_cta_section') {
    const sourceIndex = suggestion.sectionIndex
    const desiredIndex = Math.min(Math.max(3, Math.floor(sections.length / 2)), sections.length - 2)
    if (sourceIndex <= desiredIndex) {
      return sections
    }

    const nextSections = [...sections]
    const [section] = nextSections.splice(sourceIndex, 1)
    nextSections.splice(desiredIndex, 0, section!)
    return nextSections
  }

  const nextSections = [...sections]
  const section = nextSections[suggestion.sectionIndex]
  const context = contexts[suggestion.sectionIndex]

  if (!section || !context) {
    return sections
  }

  if (suggestion.action === 'swap_variant') {
    const nextSection = applyVariantSuggestion(section, context)
    if (nextSection === section) {
      return sections
    }

    nextSections[suggestion.sectionIndex] = nextSection
    return nextSections
  }

  if (suggestion.action === 'strengthen_cta_label') {
    const nextSection = applyCtaLabelSuggestion(section, context, input)
    if (nextSection === section) {
      return sections
    }

    nextSections[suggestion.sectionIndex] = nextSection
    return nextSections
  }

  const nextSection = applyPropSuggestion(section, context, suggestion)
  if (nextSection === section) {
    return sections
  }

  nextSections[suggestion.sectionIndex] = nextSection
  return nextSections
}

export function improveDesignFromReport(input: ImproveDesignFromReportInput): ImproveDesignFromReportResult {
  let nextSections = cloneData(input.siteSections)
  const applied = new Set<string>()
  const changesApplied: string[] = []
  const orderedSuggestions = [
    ...input.report.improvements.filter((suggestion) => suggestion.action !== 'promote_cta_section'),
    ...input.report.improvements.filter((suggestion) => suggestion.action === 'promote_cta_section'),
  ]

  orderedSuggestions.forEach((suggestion) => {
    const contexts = buildSectionContexts({
      blueprint: input.blueprint,
      siteSections: nextSections,
      tree: input.tree,
      registryIndex: input.registryIndex,
      threshold: input.report.threshold,
    })
    const nextCandidate = applySectionSuggestion(nextSections, contexts, suggestion, input)
    if (nextCandidate === nextSections) {
      return
    }
    nextSections = nextCandidate

    const label = suggestion.detail ?? `${suggestion.action} on ${suggestion.target}`
    if (!applied.has(label)) {
      applied.add(label)
      changesApplied.push(label)
    }
  })

  return {
    siteSections: nextSections,
    changesApplied,
  }
}
