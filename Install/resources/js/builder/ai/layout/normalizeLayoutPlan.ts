export interface DraftLayoutPlanSection {
  type: string
  required?: boolean
  score?: number
  contentBrief?: Record<string, unknown>
  originalIndex?: number
}

export interface NormalizedLayoutPlanSection extends DraftLayoutPlanSection {
  type: string
  required: boolean
  score: number
}

const MAX_LAYOUT_SECTIONS = 12

function resolvePinnedOrder(type: string): number {
  if (type === 'header') {
    return 0
  }
  if (type === 'hero') {
    return 1
  }
  if (type === 'footer') {
    return 999
  }

  return 100
}

export function normalizeLayoutPlan(sections: DraftLayoutPlanSection[]): NormalizedLayoutPlanSection[] {
  const deduped = new Map<string, NormalizedLayoutPlanSection>()

  sections.forEach((section, index) => {
    const type = section.type.trim()
    if (type === '') {
      return
    }

    const existing = deduped.get(type)
    const candidate: NormalizedLayoutPlanSection = {
      ...section,
      type,
      required: section.required === true,
      score: Number.isFinite(section.score) ? Number(section.score) : 0,
      originalIndex: section.originalIndex ?? index,
    }

    if (!existing) {
      deduped.set(type, candidate)
      return
    }

    deduped.set(type, {
      ...existing,
      required: existing.required || candidate.required,
      score: Math.max(existing.score, candidate.score),
      contentBrief: existing.contentBrief ?? candidate.contentBrief,
      originalIndex: Math.min(existing.originalIndex ?? index, candidate.originalIndex ?? index),
    })
  })

  const ordered = [...deduped.values()].sort((left, right) => {
    const leftPinned = resolvePinnedOrder(left.type)
    const rightPinned = resolvePinnedOrder(right.type)
    if (leftPinned !== rightPinned) {
      return leftPinned - rightPinned
    }

    if ((left.originalIndex ?? 0) !== (right.originalIndex ?? 0)) {
      return (left.originalIndex ?? 0) - (right.originalIndex ?? 0)
    }

    if (right.score !== left.score) {
      return right.score - left.score
    }

    return left.type.localeCompare(right.type)
  })

  const footer = ordered.find((section) => section.type === 'footer') ?? null
  const withoutFooter = ordered.filter((section) => section.type !== 'footer')
  const limited = withoutFooter.slice(0, footer ? MAX_LAYOUT_SECTIONS - 1 : MAX_LAYOUT_SECTIONS)

  return footer ? [...limited, footer] : limited
}
