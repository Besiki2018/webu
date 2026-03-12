/**
 * Builder utils — default props merge.
 * Used so components receive: props = saved component props + default props.
 * finalProps = defaults + savedProps (saved values override; use default when not provided).
 */

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Merges default props with saved/override props.
 * For each key: use saved value if provided (not undefined), otherwise use default.
 * Nested plain objects are merged recursively so e.g. responsive.desktop is merged, not replaced.
 *
 * @param defaults - Base props (e.g. from schema or registry)
 * @param props - Saved/override props (e.g. from section.props or propsText)
 * @returns finalProps = defaults + props (props win when defined)
 *
 * @example
 * mergeDefaults(
 *   { title: 'Default title', backgroundColor: '#fff' },
 *   { title: 'Custom' }
 * )
 * // => { title: 'Custom', backgroundColor: '#fff' }  (title from props, backgroundColor from default)
 */
export function mergeDefaults(
  defaults: Record<string, unknown>,
  props: Record<string, unknown>
): Record<string, unknown> {
  const result: Record<string, unknown> = { ...defaults };

  for (const [key, value] of Object.entries(props)) {
    if (value === undefined) {
      continue;
    }
    const current = result[key];
    if (isPlainObject(current) && isPlainObject(value)) {
      result[key] = mergeDefaults(current, value);
    } else {
      result[key] = value;
    }
  }

  return result;
}
