function cloneFallback<T>(value: T, seen: WeakMap<object, unknown>): T {
  if (
    value === null
    || typeof value === 'undefined'
    || typeof value === 'string'
    || typeof value === 'number'
    || typeof value === 'boolean'
    || typeof value === 'bigint'
    || typeof value === 'symbol'
    || typeof value === 'function'
  ) {
    return value
  }

  if (value instanceof Date) {
    return new Date(value.getTime()) as T
  }

  if (Array.isArray(value)) {
    return value.map((entry) => cloneFallback(entry, seen)) as T
  }

  if (value instanceof Map) {
    const clonedMap = new Map()
    seen.set(value, clonedMap)
    value.forEach((entryValue, entryKey) => {
      clonedMap.set(cloneFallback(entryKey, seen), cloneFallback(entryValue, seen))
    })
    return clonedMap as T
  }

  if (value instanceof Set) {
    const clonedSet = new Set()
    seen.set(value, clonedSet)
    value.forEach((entryValue) => {
      clonedSet.add(cloneFallback(entryValue, seen))
    })
    return clonedSet as T
  }

  if (typeof value === 'object') {
    const existing = seen.get(value as object)
    if (existing) {
      return existing as T
    }

    const prototype = Object.getPrototypeOf(value)
    const clonedObject = prototype === null ? Object.create(null) : {}
    seen.set(value as object, clonedObject)

    Object.keys(value as Record<string, unknown>).forEach((key) => {
      clonedObject[key] = cloneFallback((value as Record<string, unknown>)[key], seen)
    })

    return clonedObject as T
  }

  return value
}

export function cloneData<T>(value: T): T {
  if (typeof globalThis.structuredClone === 'function') {
    return globalThis.structuredClone(value)
  }

  return cloneFallback(value, new WeakMap<object, unknown>())
}

export function cloneRecordData<T extends Record<string, unknown>>(value: T | null | undefined): T {
  if (!value) {
    return {} as T
  }

  return cloneData(value)
}
