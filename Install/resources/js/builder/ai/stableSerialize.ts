function normalizeForStableSerialization(value: unknown, seen: WeakSet<object>): unknown {
  if (
    value === null
    || typeof value === 'string'
    || typeof value === 'number'
    || typeof value === 'boolean'
  ) {
    return value
  }

  if (Array.isArray(value)) {
    return value.map((entry) => normalizeForStableSerialization(entry, seen))
  }

  if (value instanceof Date) {
    return value.toISOString()
  }

  if (typeof value === 'object') {
    if (seen.has(value)) {
      return '[Circular]'
    }

    seen.add(value)

    return Object.keys(value as Record<string, unknown>)
      .sort((left, right) => left.localeCompare(right))
      .reduce<Record<string, unknown>>((accumulator, key) => {
        const entry = (value as Record<string, unknown>)[key]
        if (typeof entry !== 'undefined') {
          accumulator[key] = normalizeForStableSerialization(entry, seen)
        }
        return accumulator
      }, {})
  }

  return String(value)
}

export function stableSerialize(value: unknown): string {
  return JSON.stringify(normalizeForStableSerialization(value, new WeakSet<object>()))
}

export function clonePlainData<T>(value: T): T {
  if (typeof globalThis.structuredClone === 'function') {
    return globalThis.structuredClone(value)
  }

  return JSON.parse(stableSerialize(value)) as T
}
