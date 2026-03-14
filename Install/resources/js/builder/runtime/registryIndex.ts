export interface RegistryIndexableEntry {
  key?: string
  componentKey?: string
  sectionType?: string | null
}

export interface RegistryIndex<T extends RegistryIndexableEntry> {
  entries: readonly T[]
  byKey: Record<string, T>
  bySectionType: Record<string, T[]>
}

function resolveEntryKey(entry: RegistryIndexableEntry): string | null {
  if (typeof entry.componentKey === 'string' && entry.componentKey.trim() !== '') {
    return entry.componentKey
  }

  if (typeof entry.key === 'string' && entry.key.trim() !== '') {
    return entry.key
  }

  return null
}

function resolveSectionType(entry: RegistryIndexableEntry): string | null {
  if (typeof entry.sectionType !== 'string') {
    return null
  }

  const normalized = entry.sectionType.trim()
  return normalized === '' ? null : normalized
}

export function buildRegistryIndex<T extends RegistryIndexableEntry>(registry: readonly T[]): RegistryIndex<T> {
  const byKey: Record<string, T> = Object.create(null)
  const bySectionType: Record<string, T[]> = Object.create(null)

  for (const component of registry) {
    const key = resolveEntryKey(component)
    if (!key) {
      continue
    }

    byKey[key] = component

    const sectionType = resolveSectionType(component)
    if (!sectionType) {
      continue
    }

    if (!bySectionType[sectionType]) {
      bySectionType[sectionType] = []
    }

    bySectionType[sectionType].push(component)
  }

  return {
    entries: registry,
    byKey,
    bySectionType,
  }
}
