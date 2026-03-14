import type { BuilderComponentInstance } from '../core/types'
import {
  getAllowedComponents,
  getComponentRuntimeEntry,
  resolveComponentRegistryKey,
} from '../componentRegistry'
import type { ProjectType } from '../projectTypes'
import { normalizeProjectSiteType } from '../projectTypes'
import { isHiddenAtBreakpoint } from '../responsiveProps'
import { getCatalogEntry } from './componentCatalog'

export type GeneratedSiteValidationMode = 'blueprint' | 'direct-structure' | 'emergency-fallback'

export interface GeneratedSiteValidationIssue {
  code:
    | 'header_count_invalid'
    | 'hero_missing'
    | 'footer_count_invalid'
    | 'duplicate_section_id'
    | 'required_props_missing'
    | 'unknown_component_key'
    | 'unsupported_component_key'
    | 'emergency_fallback_overflow'
  message: string
  componentKey?: string
  sectionId?: string
}

export interface ValidateGeneratedSiteInput {
  projectType: ProjectType
  tree: BuilderComponentInstance[]
  supportedComponentKeys?: string[]
  plannedSections?: Array<{
    componentKey: string
    props?: Record<string, unknown>
    metadata?: Record<string, unknown>
    sectionId?: string
  }>
  generationMode?: GeneratedSiteValidationMode | null
  usedEmergencyFallback?: boolean
}

export interface ValidateGeneratedSiteResult {
  ok: boolean
  issues: GeneratedSiteValidationIssue[]
}

export const GENERATED_SITE_VALIDATION_ERROR_PREFIX = 'Generated site validation failed:'

function hasScalarValue(value: unknown): boolean {
  if (typeof value === 'string') {
    const normalized = value.trim()
    return normalized !== '' && normalized !== '[]' && normalized !== '{}'
  }

  if (typeof value === 'number' || typeof value === 'boolean') {
    return true
  }

  if (Array.isArray(value)) {
    return value.length > 0
  }

  if (value && typeof value === 'object') {
    return Object.keys(value as Record<string, unknown>).length > 0
  }

  return false
}

function hasCollectionValue(value: unknown): boolean {
  if (Array.isArray(value)) {
    return value.length > 0
  }

  if (typeof value === 'string') {
    const normalized = value.trim()
    if (normalized === '' || normalized === '[]') {
      return false
    }

    try {
      const parsed = JSON.parse(normalized) as unknown
      return Array.isArray(parsed) ? parsed.length > 0 : hasScalarValue(parsed)
    } catch {
      return false
    }
  }

  return hasScalarValue(value)
}

function isEmergencyFallbackMarker(value: unknown): boolean {
  return value === true
}

function isNodeVisible(node: BuilderComponentInstance): boolean {
  const props = node.props ?? {}

  if ((props.hidden as boolean | undefined) === true) {
    return false
  }

  if (typeof props.visibility === 'string' && props.visibility.toLowerCase() === 'hidden') {
    return false
  }

  if (typeof props.display === 'string' && props.display.toLowerCase() === 'none') {
    return false
  }

  return !(
    isHiddenAtBreakpoint(props, 'desktop')
    && isHiddenAtBreakpoint(props, 'tablet')
    && isHiddenAtBreakpoint(props, 'mobile')
  )
}

function readFirstProp(props: Record<string, unknown>, aliases: string[]): unknown {
  for (const alias of aliases) {
    if (alias in props) {
      return props[alias]
    }
  }

  return undefined
}

function validateRequiredProps(node: BuilderComponentInstance, layoutType: string): GeneratedSiteValidationIssue[] {
  if (!isNodeVisible(node)) {
    return []
  }

  const props = node.props ?? {}
  const issues: GeneratedSiteValidationIssue[] = []
  const checks: Array<{
    label: string
    aliases: string[]
    kind: 'scalar' | 'collection'
  }> = []

  switch (layoutType) {
    case 'header':
      checks.push({ label: 'logo', aliases: ['logoText', 'logo_url', 'logoFallback'], kind: 'scalar' })
      break
    case 'hero':
      checks.push({ label: 'title', aliases: ['title', 'headline'], kind: 'scalar' })
      checks.push({ label: 'supporting copy', aliases: ['subtitle', 'description', 'body'], kind: 'scalar' })
      checks.push({ label: 'primary CTA', aliases: ['buttonText', 'buttonLabel', 'ctaText', 'cta_label'], kind: 'scalar' })
      break
    case 'features':
    case 'cards':
    case 'grid':
    case 'testimonials':
    case 'faq':
      checks.push({ label: 'title', aliases: ['title', 'headline'], kind: 'scalar' })
      checks.push({ label: 'items', aliases: ['items', 'testimonials'], kind: 'collection' })
      break
    case 'product-grid':
      checks.push({ label: 'title', aliases: ['title', 'headline'], kind: 'scalar' })
      checks.push({ label: 'products', aliases: ['items', 'products', 'selectedProducts', 'productCount'], kind: 'scalar' })
      break
    case 'cta':
    case 'banner':
      checks.push({ label: 'title', aliases: ['title', 'headline'], kind: 'scalar' })
      checks.push({ label: 'CTA label', aliases: ['buttonText', 'buttonLabel', 'ctaText', 'cta_label'], kind: 'scalar' })
      break
    case 'form':
      checks.push({ label: 'title', aliases: ['title', 'headline'], kind: 'scalar' })
      checks.push({ label: 'submit label', aliases: ['submit_label', 'buttonText', 'buttonLabel'], kind: 'scalar' })
      break
    case 'footer':
      checks.push({ label: 'copyright', aliases: ['copyright'], kind: 'scalar' })
      break
    default:
      return issues
  }

  checks.forEach((check) => {
    const value = readFirstProp(props, check.aliases)
    const hasValue = check.kind === 'collection'
      ? hasCollectionValue(value)
      : hasScalarValue(value)

    if (!hasValue) {
      issues.push({
        code: 'required_props_missing',
        message: `${layoutType} section "${node.id}" is missing required ${check.label}.`,
        componentKey: node.componentKey,
        sectionId: node.id,
      })
    }
  })

  return issues
}

function countEmergencyFallbackSections(input: ValidateGeneratedSiteInput): number {
  const fallbackSections = new Set<string>()

  if (input.usedEmergencyFallback === true) {
    if (input.plannedSections?.length) {
      input.plannedSections.forEach((section, index) => {
        fallbackSections.add(section.sectionId ?? `planned-fallback-${index + 1}`)
      })
    } else {
      input.tree.forEach((section, index) => {
        fallbackSections.add(section.id || `tree-fallback-${index + 1}`)
      })
    }
  }

  input.plannedSections?.forEach((section, index) => {
    if (
      isEmergencyFallbackMarker(section.metadata?.emergencyFallback)
      || isEmergencyFallbackMarker(section.props?.__emergencyFallback)
    ) {
      fallbackSections.add(section.sectionId ?? `planned-fallback-${index + 1}`)
    }
  })

  input.tree.forEach((node, index) => {
    if (
      isEmergencyFallbackMarker(node.metadata?.emergencyFallback)
      || isEmergencyFallbackMarker(node.props?.__emergencyFallback)
    ) {
      fallbackSections.add(node.id || `tree-fallback-${index + 1}`)
    }
  })

  return fallbackSections.size
}

function validateComponentReference(input: {
  componentKey: string
  projectType: ProjectType
  supportedComponentKeys: Set<string>
  location: string
  sectionId?: string
}): {
  issues: GeneratedSiteValidationIssue[]
  canonicalKey: string | null
  layoutType: string | null
} {
  const canonicalKey = resolveComponentRegistryKey(input.componentKey)

  if (!canonicalKey) {
    return {
      issues: [{
        code: 'unknown_component_key',
        message: `Unknown component key "${input.componentKey}" at ${input.location}.`,
        componentKey: input.componentKey,
        ...(input.sectionId ? { sectionId: input.sectionId } : {}),
      }],
      canonicalKey: null,
      layoutType: null,
    }
  }

  const runtimeEntry = getComponentRuntimeEntry(canonicalKey)
  if (!runtimeEntry) {
    return {
      issues: [{
        code: 'unknown_component_key',
        message: `Unknown component key "${input.componentKey}" at ${input.location}.`,
        componentKey: input.componentKey,
        ...(input.sectionId ? { sectionId: input.sectionId } : {}),
      }],
      canonicalKey,
      layoutType: null,
    }
  }

  if (!input.supportedComponentKeys.has(canonicalKey)) {
    return {
      issues: [{
        code: 'unsupported_component_key',
        message: `Component "${canonicalKey}" is not allowed for project type "${input.projectType}" at ${input.location}.`,
        componentKey: canonicalKey,
        ...(input.sectionId ? { sectionId: input.sectionId } : {}),
      }],
      canonicalKey,
      layoutType: null,
    }
  }

  return {
    issues: [],
    canonicalKey,
    layoutType: getCatalogEntry(canonicalKey)?.layoutType
      ?? runtimeEntry.schema.sectionType
      ?? runtimeEntry.schema.category,
  }
}

function visitTree(
  nodes: BuilderComponentInstance[],
  visitor: (node: BuilderComponentInstance, context: { depth: number; index: number; location: string }) => void,
  depth = 0,
  parentLocation = 'top-level tree',
): void {
  nodes.forEach((node, index) => {
    const location = depth === 0
      ? `section "${node.id}"`
      : `${parentLocation} child ${index + 1}`
    visitor(node, { depth, index, location })
    if (Array.isArray(node.children) && node.children.length > 0) {
      visitTree(node.children, visitor, depth + 1, location)
    }
  })
}

export function formatGeneratedSiteValidationIssues(issues: GeneratedSiteValidationIssue[]): string {
  return `${GENERATED_SITE_VALIDATION_ERROR_PREFIX} ${issues.map((issue) => issue.message).join(' ')}`
}

export function isGeneratedSiteValidationMessage(message: string | null | undefined): boolean {
  return typeof message === 'string' && message.startsWith(GENERATED_SITE_VALIDATION_ERROR_PREFIX)
}

export function validateGeneratedSite(input: ValidateGeneratedSiteInput): ValidateGeneratedSiteResult {
  const issues: GeneratedSiteValidationIssue[] = []
  const seenIds = new Set<string>()
  const siteType = normalizeProjectSiteType(input.projectType)
  const supportedComponentKeys = new Set(
    (input.supportedComponentKeys ?? getAllowedComponents(siteType).map((entry) => entry.type))
      .map((key) => resolveComponentRegistryKey(key) ?? key)
  )

  let headerCount = 0
  let heroCount = 0
  let footerCount = 0

  input.plannedSections?.forEach((section, index) => {
    const location = `planned section ${index + 1}`
    const validation = validateComponentReference({
      componentKey: section.componentKey,
      projectType: input.projectType,
      supportedComponentKeys,
      location,
      sectionId: section.sectionId,
    })
    issues.push(...validation.issues)
  })

  input.tree.forEach((node) => {
    if (seenIds.has(node.id)) {
      issues.push({
        code: 'duplicate_section_id',
        message: `Duplicate top-level section id "${node.id}" detected.`,
        componentKey: node.componentKey,
        sectionId: node.id,
      })
    } else {
      seenIds.add(node.id)
    }
  })

  visitTree(input.tree, (node, context) => {
    const validation = validateComponentReference({
      componentKey: node.componentKey,
      projectType: input.projectType,
      supportedComponentKeys,
      location: context.location,
      sectionId: node.id,
    })
    issues.push(...validation.issues)
    if (!validation.canonicalKey || !validation.layoutType) {
      return
    }

    if (context.depth === 0) {
      if (validation.layoutType === 'header') {
        headerCount += 1
      } else if (validation.layoutType === 'hero') {
        heroCount += 1
      } else if (validation.layoutType === 'footer') {
        footerCount += 1
      }
    }

    issues.push(...validateRequiredProps({
      ...node,
      componentKey: validation.canonicalKey,
    }, validation.layoutType))
  })

  if (headerCount !== 1) {
    issues.push({
      code: 'header_count_invalid',
      message: `Generated site must contain exactly one header. Received ${headerCount}.`,
    })
  }

  if (heroCount < 1) {
    issues.push({
      code: 'hero_missing',
      message: 'Generated site must contain at least one hero section.',
    })
  }

  if (footerCount !== 1) {
    issues.push({
      code: 'footer_count_invalid',
      message: `Generated site must contain exactly one footer. Received ${footerCount}.`,
    })
  }

  const emergencyFallbackSectionCount = countEmergencyFallbackSections(input)
  if ((input.generationMode ?? 'blueprint') !== 'emergency-fallback' && emergencyFallbackSectionCount > 1) {
    issues.push({
      code: 'emergency_fallback_overflow',
      message: `Emergency fallback expanded to ${emergencyFallbackSectionCount} sections without explicit fallback mode.`,
    })
  }

  return {
    ok: issues.length === 0,
    issues,
  }
}
