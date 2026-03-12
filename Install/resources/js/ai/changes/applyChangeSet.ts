/**
 * Apply a ChangeSet to a sections array (type + props).
 * Returns a new sections array; does not mutate.
 * Use this to apply AI-generated ChangeSets to the page draft.
 */
import type { ChangeSet, ChangeSetOperation } from './changeSet.schema';
import { setValueAtPath } from '@/builder/state/sectionProps';

export interface SectionItem {
  id?: string;
  type: string;
  props?: Record<string, unknown>;
}

/**
 * Applies a ChangeSet to the given sections array.
 * Section ids in the ChangeSet should match SectionItem.id (or order when id not set).
 * Returns new sections array; original is not mutated.
 */
export function applyChangeSetToSections(
  sections: SectionItem[],
  changeSet: ChangeSet
): SectionItem[] {
  let result: SectionItem[] = sections.map((s) => ({
    ...s,
    id: s.id ?? undefined,
    type: s.type,
    props: { ...(s.props ?? {}) },
  }));

  for (const op of changeSet.operations) {
    result = applyOperation(result, op);
  }

  return result;
}

function applyOperation(
  sections: SectionItem[],
  op: ChangeSetOperation
): SectionItem[] {
  switch (op.op) {
    case 'updateSection': {
      const idx = findSectionIndex(sections, op.sectionId);
      if (idx < 0) return sections;
      const next = [...sections];
      next[idx] = {
        ...next[idx],
        props: { ...(next[idx].props ?? {}), ...op.patch },
      };
      return next;
    }
    case 'insertSection': {
      const newSection: SectionItem = {
        id: op.sectionId,
        type: op.sectionType,
        props: { ...(op.props ?? {}) },
      };
      if (op.afterSectionId != null) {
        const idx = findSectionIndex(sections, op.afterSectionId);
        const insertAt = idx < 0 ? sections.length : idx + 1;
        const next = [...sections];
        next.splice(insertAt, 0, newSection);
        return next;
      }
      if (op.index != null) {
        const next = [...sections];
        next.splice(op.index, 0, newSection);
        return next;
      }
      return [...sections, newSection];
    }
    case 'deleteSection': {
      const idx = findSectionIndex(sections, op.sectionId);
      if (idx < 0) return sections;
      const next = [...sections];
      next.splice(idx, 1);
      return next;
    }
    case 'reorderSection': {
      const idx = findSectionIndex(sections, op.sectionId);
      if (idx < 0 || idx === op.toIndex) return sections;
      const next = [...sections];
      const [item] = next.splice(idx, 1);
      next.splice(op.toIndex, 0, item!);
      return next;
    }
    case 'updateText': {
      if (op.sectionId != null) {
        const idx = findSectionIndex(sections, op.sectionId);
        if (idx < 0) return sections;
        const next = [...sections];
        const path = (op.path ?? 'headline').split('.');
        const props = next[idx].props ?? {};
        next[idx] = { ...next[idx], props: setValueAtPath(props, path, op.value) };
        return next;
      }
      return sections;
    }
    case 'replaceImage': {
      const idx = findSectionIndex(sections, op.sectionId);
      if (idx < 0) return sections;
      const next = [...sections];
      const props = { ...(next[idx].props ?? {}) };
      const imageKey = op.imageKey ?? 'image';
      const existing = { ...((props[imageKey] as Record<string, unknown>) ?? {}) };
      if (op.url !== undefined) existing.url = op.url;
      if (op.alt !== undefined) existing.alt = op.alt;
      props[imageKey] = existing;
      next[idx] = { ...next[idx], props };
      return next;
    }
    case 'updateButton': {
      const idx = findSectionIndex(sections, op.sectionId);
      if (idx < 0) return sections;
      const next = [...sections];
      const props = { ...(next[idx].props ?? {}) };
      if (op.label !== undefined) props.buttonLabel = op.label;
      if (op.href !== undefined) props.buttonHref = op.href;
      if (op.variant !== undefined) props.buttonVariant = op.variant;
      next[idx] = { ...next[idx], props };
      return next;
    }
    case 'updateTheme':
    case 'addProduct':
    case 'removeProduct':
    case 'translatePage':
    case 'generateContent':
      // These require backend or broader context; leave sections unchanged here
      return sections;
    default:
      return sections;
  }
}

function findSectionIndex(sections: SectionItem[], sectionId: string): number {
  const byId = sections.findIndex((s) => (s.id ?? '').toString() === sectionId);
  if (byId >= 0) return byId;
  const n = parseInt(sectionId, 10);
  if (!Number.isNaN(n) && n >= 0 && n < sections.length) return n;
  return -1;
}
