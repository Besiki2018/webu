/**
 * Visual DOM Mapper for Webu Builder.
 * Builds a structured map of rendered components and editable elements from the preview DOM.
 * Used for element selection, hover highlight, and AI chat element targeting.
 */

import { buildElementId, getComponentParameterMeta, getComponentShortName } from '../componentParameterMetadata';

export interface DOMMapperSectionSnapshot {
  localId: string;
  sectionKey: string;
  label?: string;
  previewText?: string;
  props?: Record<string, unknown>;
}

export interface MappedElement {
  /** Unique id for AI/sidebar: ComponentName.parameterName */
  elementId: string;
  /** Section key (e.g. webu_general_hero_01) */
  sectionKey: string;
  /** Section instance local id when available */
  sectionLocalId: string | null;
  /** Parameter name (e.g. title, headline, buttonText) */
  parameterName: string;
  /** CSS selector to find this element in the iframe */
  selector: string;
  /** Which attribute resolved the target */
  attribute: 'data-webu-field' | 'data-webu-field-url' | 'data-webu-field-scope';
  /** Display label */
  label?: string;
}

export interface MappedSection {
  sectionKey: string;
  sectionLocalId: string | null;
  displayName: string;
  shortName: string;
  elements: MappedElement[];
}

export interface DOMMap {
  sections: MappedSection[];
  /** Flat list for quick lookup by elementId */
  elementsById: Map<string, MappedElement>;
  /** When the map was built (for cache invalidation) */
  builtAt: number;
}

interface FlattenedEditableField {
  path: string;
  rootParameter: string;
  value: string;
  kind: 'text' | 'image' | 'link' | 'icon' | 'number' | 'boolean';
}

interface HostCandidateBuckets {
  text: HTMLElement[];
  images: HTMLElement[];
  links: HTMLElement[];
  icons: HTMLElement[];
}

const TEXT_CANDIDATE_SELECTOR = [
  'h1',
  'h2',
  'h3',
  'h4',
  'h5',
  'h6',
  'p',
  'span',
  'small',
  'strong',
  'em',
  'b',
  'i',
  'a',
  'button',
  'label',
  'li',
  'dt',
  'dd',
  'figcaption',
  'blockquote',
  'div',
].join(', ');

const IMAGE_CANDIDATE_SELECTOR = 'img, source, video[poster], [style*="background-image"]';
const LINK_CANDIDATE_SELECTOR = 'a[href], button[formaction], [role="link"]';
const ICON_CANDIDATE_SELECTOR = 'svg, use, i, [class*="icon"], [data-icon], [data-feather]';

function escapeAttributeValue(value: string): string {
  return value.replace(/"/g, '\\"');
}

function normalizeComparableText(value: string): string {
  return value.replace(/\s+/g, ' ').trim().toLowerCase();
}

function stripUrlWrapping(value: string): string {
  return value
    .trim()
    .replace(/^url\((.*)\)$/i, '$1')
    .replace(/^['"]|['"]$/g, '');
}

function normalizeComparableUrl(value: string): string {
  const stripped = stripUrlWrapping(value);
  if (!stripped) {
    return '';
  }

  try {
    const parsed = new URL(stripped, 'https://webu.local');
    return `${parsed.pathname}${parsed.search}${parsed.hash}`.toLowerCase();
  } catch {
    return stripped.toLowerCase();
  }
}

function getUrlTail(value: string): string {
  const normalized = normalizeComparableUrl(value);
  if (!normalized) {
    return '';
  }

  const segments = normalized.split('/').filter(Boolean);
  return segments[segments.length - 1] ?? normalized;
}

function formatParameterLabel(path: string): string {
  const readable = path
    .split('.')
    .filter(Boolean)
    .map((segment) => segment.replace(/_/g, ' '))
    .join(' ');

  if (!readable) {
    return path;
  }

  return readable.charAt(0).toUpperCase() + readable.slice(1);
}

function inferFieldKind(pathSegments: string[], value: string | number | boolean): FlattenedEditableField['kind'] {
  const joined = pathSegments.join('.').toLowerCase();
  if (typeof value === 'boolean') {
    return 'boolean';
  }
  if (typeof value === 'number') {
    return 'number';
  }
  if (/(image|logo|avatar|photo|picture|thumbnail|background|bg|banner|hero_image|icon_image)/i.test(joined)) {
    return 'image';
  }
  if (/(icon|glyph|symbol)/i.test(joined)) {
    return 'icon';
  }
  if (/(url|href|link|cta_link|button_link)/i.test(joined)) {
    return 'link';
  }
  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (/^(https?:)?\/\//i.test(trimmed) || trimmed.startsWith('/')) {
      if (/\.(png|jpe?g|gif|webp|svg|avif)(\?|#|$)/i.test(trimmed)) {
        return 'image';
      }
      return 'link';
    }
  }
  return 'text';
}

function flattenEditableFields(
  value: unknown,
  path: string[] = [],
  fields: FlattenedEditableField[] = []
): FlattenedEditableField[] {
  if (Array.isArray(value)) {
    value.forEach((entry, index) => flattenEditableFields(entry, [...path, String(index)], fields));
    return fields;
  }

  if (value && typeof value === 'object') {
    Object.entries(value as Record<string, unknown>).forEach(([key, entry]) => {
      flattenEditableFields(entry, [...path, key], fields);
    });
    return fields;
  }

  if (path.length === 0 || value === null || value === undefined) {
    return fields;
  }

  if (typeof value === 'string') {
    const trimmed = value.trim();
    if (!trimmed) {
      return fields;
    }

    fields.push({
      path: path.join('.'),
      rootParameter: path[0],
      value: trimmed,
      kind: inferFieldKind(path, trimmed),
    });
    return fields;
  }

  if (typeof value === 'number' && Number.isFinite(value)) {
    fields.push({
      path: path.join('.'),
      rootParameter: path[0],
      value: String(value),
      kind: inferFieldKind(path, value),
    });
    return fields;
  }

  if (typeof value === 'boolean') {
    fields.push({
      path: path.join('.'),
      rootParameter: path[0],
      value: value ? 'true' : 'false',
      kind: 'boolean',
    });
  }

  return fields;
}

function isNestedInsideOtherSection(host: HTMLElement, node: Element): boolean {
  const nearestSection = node.closest<HTMLElement>('[data-webu-section]');
  return Boolean(nearestSection && nearestSection !== host);
}

function isMeaningfulTextCandidate(node: HTMLElement): boolean {
  if (node.matches('[aria-hidden="true"], script, style')) {
    return false;
  }

  const text = normalizeComparableText(node.textContent ?? '');
  if (!text) {
    return false;
  }

  const tag = node.tagName.toLowerCase();
  const permissiveTags = new Set(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'button', 'label', 'li', 'dt', 'dd', 'figcaption', 'blockquote']);
  if (permissiveTags.has(tag)) {
    return true;
  }

  const meaningfulChildren = Array.from(node.children).filter((child) => {
    const childEl = child as HTMLElement;
    return normalizeComparableText(childEl.textContent ?? '') !== '' || childEl.matches('img, svg, use, i, [class*="icon"]');
  });

  return meaningfulChildren.length === 0;
}

function collectHostCandidates(host: HTMLElement): HostCandidateBuckets {
  const inHost = (node: Element) => !isNestedInsideOtherSection(host, node);

  return {
    text: Array.from(host.querySelectorAll<HTMLElement>(TEXT_CANDIDATE_SELECTOR)).filter((node) => inHost(node) && isMeaningfulTextCandidate(node)),
    images: Array.from(host.querySelectorAll<HTMLElement>(IMAGE_CANDIDATE_SELECTOR)).filter((node) => inHost(node)),
    links: Array.from(host.querySelectorAll<HTMLElement>(LINK_CANDIDATE_SELECTOR)).filter((node) => inHost(node)),
    icons: Array.from(host.querySelectorAll<HTMLElement>(ICON_CANDIDATE_SELECTOR)).filter((node) => inHost(node)),
  };
}

function getTextMatchScore(node: HTMLElement, field: FlattenedEditableField): number {
  const nodeText = normalizeComparableText(node.textContent ?? '');
  const fieldText = normalizeComparableText(field.value);

  if (!nodeText || !fieldText) {
    return -1;
  }

  let score = -1;
  if (nodeText === fieldText) {
    score = 120;
  } else if (nodeText.includes(fieldText) || fieldText.includes(nodeText)) {
    const penalty = Math.abs(nodeText.length - fieldText.length);
    score = Math.max(60 - penalty, 10);
  }

  if (score < 0) {
    return score;
  }

  const path = field.path.toLowerCase();
  if (/(title|headline|heading|hero_title|section_title)/.test(path) && node.matches('h1, h2, h3, h4, h5, h6')) {
    score += 18;
  }
  if (/(subtitle|description|body|caption|text|content)/.test(path) && node.matches('p, small, span, figcaption, blockquote, div')) {
    score += 10;
  }
  if (/(label|cta|button|action|link|name)/.test(path) && node.matches('a, button, label, span, li')) {
    score += 12;
  }

  return score;
}

function getNodeComparableUrl(node: HTMLElement): string {
  if (node instanceof HTMLAnchorElement) {
    return normalizeComparableUrl(node.getAttribute('href') ?? node.href ?? '');
  }
  if (node instanceof HTMLButtonElement) {
    return normalizeComparableUrl(node.getAttribute('formaction') ?? '');
  }
  return normalizeComparableUrl(node.getAttribute('href') ?? '');
}

function getLinkMatchScore(node: HTMLElement, field: FlattenedEditableField): number {
  const nodeUrl = getNodeComparableUrl(node);
  const fieldUrl = normalizeComparableUrl(field.value);
  if (!nodeUrl || !fieldUrl) {
    return -1;
  }

  if (nodeUrl === fieldUrl) {
    return 120;
  }

  const nodeTail = getUrlTail(nodeUrl);
  const fieldTail = getUrlTail(fieldUrl);
  if (nodeTail && fieldTail && nodeTail === fieldTail) {
    return 90;
  }

  if (nodeUrl.endsWith(fieldUrl) || fieldUrl.endsWith(nodeUrl)) {
    return 70;
  }

  return -1;
}

function getImageSource(node: HTMLElement): string {
  if (node instanceof HTMLImageElement) {
    return node.getAttribute('src') ?? node.currentSrc ?? node.src ?? '';
  }
  if (node instanceof HTMLSourceElement) {
    return node.getAttribute('src') ?? node.getAttribute('srcset') ?? '';
  }
  if (node instanceof HTMLVideoElement) {
    return node.getAttribute('poster') ?? '';
  }
  return node.style.backgroundImage || node.getAttribute('style') || '';
}

function getImageMatchScore(node: HTMLElement, field: FlattenedEditableField): number {
  const nodeSrc = normalizeComparableUrl(getImageSource(node));
  const fieldSrc = normalizeComparableUrl(field.value);
  if (!nodeSrc || !fieldSrc) {
    return -1;
  }

  if (nodeSrc === fieldSrc) {
    return 120;
  }

  const nodeTail = getUrlTail(nodeSrc);
  const fieldTail = getUrlTail(fieldSrc);
  if (nodeTail && fieldTail && nodeTail === fieldTail) {
    return 90;
  }

  if (nodeSrc.endsWith(fieldSrc) || fieldSrc.endsWith(nodeSrc)) {
    return 70;
  }

  return -1;
}

function getIconDescriptor(node: HTMLElement): string {
  const classNames = typeof node.className === 'string' ? node.className : '';
  return [
    node.getAttribute('data-icon') ?? '',
    node.getAttribute('data-feather') ?? '',
    node.getAttribute('href') ?? '',
    node.getAttribute('xlink:href') ?? '',
    classNames,
  ].join(' ');
}

function getIconMatchScore(node: HTMLElement, field: FlattenedEditableField): number {
  const descriptor = normalizeComparableText(getIconDescriptor(node));
  const value = normalizeComparableText(field.value);
  if (!descriptor || !value) {
    return -1;
  }

  if (descriptor === value) {
    return 120;
  }

  if (descriptor.includes(value) || value.includes(descriptor)) {
    return 80;
  }

  return -1;
}

function getFieldIndexHint(path: string): number | null {
  const segments = path.split('.');
  for (let index = segments.length - 1; index >= 0; index -= 1) {
    const value = Number.parseInt(segments[index] ?? '', 10);
    if (Number.isFinite(value)) {
      return value;
    }
  }
  return null;
}

function shouldAnnotateLinkText(path: string): boolean {
  const lastSegment = splitParameterPath(path).slice(-1)[0]?.toLowerCase() ?? '';
  return /(label|text|title|caption|button|cta|name)$/.test(lastSegment);
}

function getNodeSpecificity(node: HTMLElement): { depth: number; area: number } {
  let depth = 0;
  let cursor: HTMLElement | null = node;
  while (cursor) {
    depth += 1;
    cursor = cursor.parentElement;
  }

  const rect = typeof node.getBoundingClientRect === 'function'
    ? node.getBoundingClientRect()
    : null;
  const area = rect ? rect.width * rect.height : 0;

  return { depth, area };
}

function pickBestCandidate(
  candidates: HTMLElement[],
  field: FlattenedEditableField,
  scoreCandidate: (node: HTMLElement, field: FlattenedEditableField) => number,
  usedNodes: Set<HTMLElement>
): HTMLElement | null {
  const scored = candidates
    .filter((node) => !usedNodes.has(node))
    .map((node, index) => ({
      node,
      index,
      score: scoreCandidate(node, field),
      specificity: getNodeSpecificity(node),
    }))
    .filter((entry) => entry.score >= 0)
    .sort((left, right) => (
      right.score - left.score
      || right.specificity.depth - left.specificity.depth
      || left.specificity.area - right.specificity.area
      || left.index - right.index
    ));

  if (scored.length === 0) {
    return null;
  }

  const bestScore = scored[0]?.score ?? -1;
  const tied = scored.filter((entry) => entry.score === bestScore);
  const indexHint = getFieldIndexHint(field.path);

  if (indexHint !== null && tied.length > 1) {
    return tied[Math.min(indexHint, tied.length - 1)]?.node ?? tied[0]?.node ?? null;
  }

  return tied[0]?.node ?? null;
}

function setInferredFieldAttribute(node: HTMLElement, attribute: 'data-webu-field' | 'data-webu-field-url', path: string): boolean {
  const sourceAttribute = `${attribute}-source`;
  const existingValue = node.getAttribute(attribute);
  const existingSource = node.getAttribute(sourceAttribute);

  if (existingValue && existingValue !== path && existingSource !== 'inferred') {
    return false;
  }

  node.setAttribute(attribute, path);
  node.setAttribute(sourceAttribute, 'inferred');
  return true;
}

function setInferredScopeAttribute(node: HTMLElement, path: string): boolean {
  const existingValue = node.getAttribute('data-webu-field-scope');
  const existingSource = node.getAttribute('data-webu-field-scope-source');

  if (existingValue) {
    if (existingValue === path) {
      return true;
    }

    if (existingSource !== 'inferred') {
      return false;
    }

    if (splitParameterPath(existingValue).length >= splitParameterPath(path).length) {
      return false;
    }
  }

  node.setAttribute('data-webu-field-scope', path);
  node.setAttribute('data-webu-field-scope-source', 'inferred');
  return true;
}

function clearInferredFieldAnnotations(doc: Document): void {
  doc.querySelectorAll<HTMLElement>('[data-webu-field-source="inferred"]').forEach((node) => {
    node.removeAttribute('data-webu-field');
    node.removeAttribute('data-webu-field-source');
  });

  doc.querySelectorAll<HTMLElement>('[data-webu-field-url-source="inferred"]').forEach((node) => {
    node.removeAttribute('data-webu-field-url');
    node.removeAttribute('data-webu-field-url-source');
  });

  doc.querySelectorAll<HTMLElement>('[data-webu-field-scope-source="inferred"]').forEach((node) => {
    node.removeAttribute('data-webu-field-scope');
    node.removeAttribute('data-webu-field-scope-source');
  });
}

function splitParameterPath(path: string): string[] {
  return path.split('.').map((segment) => segment.trim()).filter(Boolean);
}

function buildScopePrefixes(path: string): string[] {
  const segments = splitParameterPath(path);
  const scopes: string[] = [];

  for (let index = 1; index < segments.length; index += 1) {
    scopes.push(segments.slice(0, index).join('.'));
  }

  return scopes;
}

function findLowestCommonAncestor(host: HTMLElement, nodes: HTMLElement[]): HTMLElement | null {
  if (nodes.length === 0) {
    return null;
  }

  let candidate: HTMLElement | null = nodes[0] ?? null;
  while (candidate && candidate !== host && !nodes.every((node) => candidate?.contains(node) ?? false)) {
    candidate = candidate.parentElement;
  }

  if (!candidate || candidate === host) {
    return null;
  }

  return candidate;
}

function annotateComponentScopes(host: HTMLElement): number {
  const fieldNodes = Array.from(host.querySelectorAll<HTMLElement>('[data-webu-field], [data-webu-field-url]'))
    .filter((node) => !isNestedInsideOtherSection(host, node));
  const nodesByScope = new Map<string, HTMLElement[]>();

  fieldNodes.forEach((node) => {
    const paths = [
      node.getAttribute('data-webu-field'),
      node.getAttribute('data-webu-field-url'),
    ].filter((value): value is string => Boolean(value && value.trim() !== ''));

    paths.forEach((path) => {
      buildScopePrefixes(path).forEach((scopePath) => {
        const existing = nodesByScope.get(scopePath) ?? [];
        existing.push(node);
        nodesByScope.set(scopePath, existing);
      });
    });
  });

  let annotatedCount = 0;
  Array.from(nodesByScope.entries())
    .sort((left, right) => right[0].split('.').length - left[0].split('.').length)
    .forEach(([scopePath, groupedNodes]) => {
      const uniqueNodes = Array.from(new Set(groupedNodes));
      let commonAncestor = findLowestCommonAncestor(host, uniqueNodes);
      if (!commonAncestor) {
        return;
      }

      const scopeTail = splitParameterPath(scopePath).slice(-1)[0] ?? '';
      if (
        /^\d+$/.test(scopeTail)
        && commonAncestor.matches('[data-webu-field], [data-webu-field-url]')
        && commonAncestor.parentElement
        && commonAncestor.parentElement !== host
      ) {
        commonAncestor = commonAncestor.parentElement;
      }

      if (setInferredScopeAttribute(commonAncestor, scopePath)) {
        annotatedCount += 1;
      }
    });

  return annotatedCount;
}

function resolveSectionHost(doc: Document, snapshot: DOMMapperSectionSnapshot): HTMLElement | null {
  const localId = snapshot.localId.trim();
  const sectionKey = snapshot.sectionKey.trim();

  if (localId) {
    const node = doc.querySelector<HTMLElement>(`[data-webu-section-local-id="${escapeAttributeValue(localId)}"]`);
    if (node) {
      return node;
    }
  }

  if (!sectionKey) {
    return null;
  }

  const matches = Array.from(doc.querySelectorAll<HTMLElement>(`[data-webu-section="${escapeAttributeValue(sectionKey)}"]`));
  return matches.length === 1 ? matches[0] : null;
}

function annotateSectionHost(host: HTMLElement, snapshot: DOMMapperSectionSnapshot): number {
  const props = snapshot.props ?? {};
  const fields = flattenEditableFields(props)
    .filter((field) => field.path !== '')
    .sort((left, right) => {
      const kindOrder = ['image', 'icon', 'link', 'text', 'number', 'boolean'];
      const leftPriority = kindOrder.indexOf(left.kind);
      const rightPriority = kindOrder.indexOf(right.kind);
      if (leftPriority !== rightPriority) {
        return leftPriority - rightPriority;
      }

      return right.value.length - left.value.length;
    });

  if (fields.length === 0) {
    return 0;
  }

  const candidates = collectHostCandidates(host);
  const usedTextNodes = new Set<HTMLElement>();
  const usedImageNodes = new Set<HTMLElement>();
  const usedIconNodes = new Set<HTMLElement>();
  let annotatedCount = 0;

  fields.forEach((field) => {
    if (host.querySelector(`[data-webu-field="${escapeAttributeValue(field.path)}"], [data-webu-field-url="${escapeAttributeValue(field.path)}"]`)) {
      return;
    }

    if (field.kind === 'image') {
      const target = pickBestCandidate(candidates.images, field, getImageMatchScore, usedImageNodes);
      if (target && setInferredFieldAttribute(target, 'data-webu-field', field.path)) {
        usedImageNodes.add(target);
        annotatedCount += 1;
      }
      return;
    }

    if (field.kind === 'icon') {
      const target = pickBestCandidate(candidates.icons, field, getIconMatchScore, usedIconNodes);
      if (target && setInferredFieldAttribute(target, 'data-webu-field', field.path)) {
        usedIconNodes.add(target);
        annotatedCount += 1;
      }
      return;
    }

    if (field.kind === 'link') {
      const target = pickBestCandidate(candidates.links, field, getLinkMatchScore, new Set<HTMLElement>());
      if (target && setInferredFieldAttribute(target, 'data-webu-field-url', field.path)) {
        annotatedCount += 1;
      }

      if (shouldAnnotateLinkText(field.path)) {
        const textTarget = pickBestCandidate(candidates.text, field, getTextMatchScore, usedTextNodes);
        if (textTarget && setInferredFieldAttribute(textTarget, 'data-webu-field', field.path)) {
          usedTextNodes.add(textTarget);
          annotatedCount += 1;
        }
      }
      return;
    }

    const target = pickBestCandidate(candidates.text, field, getTextMatchScore, usedTextNodes);
    if (target && setInferredFieldAttribute(target, 'data-webu-field', field.path)) {
      usedTextNodes.add(target);
      annotatedCount += 1;
    }
  });

  return annotatedCount;
}

/**
 * Infer missing editable field markers from live builder props so the preview supports
 * field-level hover, click selection, sidebar focus, and AI chat targeting.
 */
export function annotateEditableElements(
  doc: Document,
  sections: DOMMapperSectionSnapshot[]
): number {
  clearInferredFieldAnnotations(doc);

  let annotatedCount = 0;
  sections.forEach((section) => {
    const host = resolveSectionHost(doc, section);
    if (!host) {
      return;
    }

    annotatedCount += annotateSectionHost(host, section);
    annotatedCount += annotateComponentScopes(host);
  });

  return annotatedCount;
}

/**
 * Scan the iframe document for [data-webu-section] and mapped editable field markers and build the map.
 * Uses component registry to know which parameters exist per section type.
 */
export function buildDOMMap(doc: Document): DOMMap {
  const sections: MappedSection[] = [];
  const elementsById = new Map<string, MappedElement>();

  const sectionHosts = doc.querySelectorAll<HTMLElement>('[data-webu-section]');
  sectionHosts.forEach((host) => {
    const sectionKey = host.getAttribute('data-webu-section');
    const sectionLocalId = host.getAttribute('data-webu-section-local-id');
    if (!sectionKey) return;

    const meta = getComponentParameterMeta(sectionKey);
    const shortName = meta?.shortName ?? getComponentShortName(sectionKey);

    const elements: MappedElement[] = [];
    const fieldNodes = [
      ...(host.matches('[data-webu-field], [data-webu-field-url], [data-webu-field-scope]') ? [host] : []),
      ...Array.from(host.querySelectorAll<HTMLElement>('[data-webu-field], [data-webu-field-url], [data-webu-field-scope]')),
    ].filter((node) => !isNestedInsideOtherSection(host, node));

    const seenParameterNames = new Set<string>();
    fieldNodes.forEach((fieldNode) => {
      const parameterEntries = [
        { attribute: 'data-webu-field', parameterName: fieldNode.getAttribute('data-webu-field') },
        { attribute: 'data-webu-field-url', parameterName: fieldNode.getAttribute('data-webu-field-url') },
        { attribute: 'data-webu-field-scope', parameterName: fieldNode.getAttribute('data-webu-field-scope') },
      ].filter((entry): entry is { attribute: 'data-webu-field' | 'data-webu-field-url' | 'data-webu-field-scope'; parameterName: string } => Boolean(entry.parameterName));

      parameterEntries.forEach(({ attribute, parameterName }) => {
        if (seenParameterNames.has(parameterName)) {
          return;
        }

        seenParameterNames.add(parameterName);
        const selector = sectionLocalId
          ? `[data-webu-section-local-id="${escapeAttributeValue(sectionLocalId)}"] [${attribute}="${escapeAttributeValue(parameterName)}"]`
          : `[data-webu-section="${escapeAttributeValue(sectionKey)}"] [${attribute}="${escapeAttributeValue(parameterName)}"]`;
        const rootField = meta?.fields.find((field) => field.parameterName === parameterName || parameterName.startsWith(`${field.parameterName}.`));
        const elementId = buildElementId(sectionKey, parameterName);
        const mapped: MappedElement = {
          elementId,
          sectionKey,
          sectionLocalId,
          parameterName,
          selector,
          attribute,
          label: rootField?.title ?? formatParameterLabel(parameterName),
        };
        elements.push(mapped);
        elementsById.set(elementId, mapped);
      });
    });

    if (meta && elements.length === 0) {
      meta.fields.forEach((field) => {
        const fieldEl = host.querySelector(`[data-webu-field="${escapeAttributeValue(field.parameterName)}"]`);
        if (!fieldEl) return;
        const elementId = `${shortName}.${field.parameterName}`;
        const selector = sectionLocalId
          ? `[data-webu-section-local-id="${escapeAttributeValue(sectionLocalId)}"] [data-webu-field="${escapeAttributeValue(field.parameterName)}"]`
          : `[data-webu-section="${escapeAttributeValue(sectionKey)}"] [data-webu-field="${escapeAttributeValue(field.parameterName)}"]`;
        const mapped: MappedElement = {
          elementId,
          sectionKey,
          sectionLocalId,
          parameterName: field.parameterName,
          selector,
          attribute: 'data-webu-field',
          label: field.title ?? field.parameterName,
        };
        elements.push(mapped);
        elementsById.set(elementId, mapped);
      });
    }

    sections.push({
      sectionKey: meta?.sectionKey ?? sectionKey,
      sectionLocalId,
      displayName: meta?.displayName ?? sectionKey,
      shortName,
      elements,
    });
  });

  return {
    sections,
    elementsById,
    builtAt: Date.now(),
  };
}

/**
 * Find which mapped element is at the given point in the document.
 * Returns the finest-grained element (e.g. title) if the point is inside a field.
 */
export function getElementAtPoint(doc: Document, x: number, y: number, map: DOMMap): MappedElement | null {
  const stack = typeof doc.elementsFromPoint === 'function'
    ? doc.elementsFromPoint(x, y)
    : (() => {
      const fallback = doc.elementFromPoint(x, y);
      return fallback ? [fallback] : [];
    })();

  for (const candidate of stack) {
    if (!(candidate instanceof HTMLElement)) {
      continue;
    }

    const sectionHost = candidate.closest<HTMLElement>('[data-webu-section]');
    if (!sectionHost) {
      continue;
    }

    const sectionKey = sectionHost.getAttribute('data-webu-section');
    if (!sectionKey) {
      continue;
    }

    const scopeEl = candidate.closest<HTMLElement>('[data-webu-field-scope]');
    const fieldEl = candidate.closest<HTMLElement>('[data-webu-field], [data-webu-field-url]');
    const parameterName = scopeEl?.getAttribute('data-webu-field-scope')
      ?? fieldEl?.getAttribute('data-webu-field')
      ?? fieldEl?.getAttribute('data-webu-field-url');

    if (!parameterName) {
      continue;
    }

    const mapped = map.elementsById.get(buildElementId(sectionKey, parameterName));
    if (mapped) {
      return mapped;
    }
  }

  return null;
}

/**
 * Get the section-level node for a given point (for overlay when no field is hit).
 */
export function getSectionAtPoint(doc: Document, x: number, y: number): HTMLElement | null {
  const el = doc.elementFromPoint(x, y);
  if (!el) return null;
  return el.closest<HTMLElement>('[data-webu-section]');
}

/** Default cache TTL: refresh when layout likely changed (e.g. section add/remove/reorder). */
const DEFAULT_CACHE_MAX_AGE_MS = 2000;

const domMapCache = new Map<string, { map: DOMMap; builtAt: number }>();

/**
 * Build a cache key from the document (section order + counts). Map is invalidated when
 * component added, removed, or reordered.
 */
function getDocFingerprint(doc: Document): string {
  const sections = doc.querySelectorAll('[data-webu-section]');
  const fields = doc.querySelectorAll('[data-webu-field], [data-webu-field-url], [data-webu-field-scope]');
  const keys = Array.from(sections).map((el) => el.getAttribute('data-webu-section') ?? '').join(',');
  return `${sections.length}-${fields.length}-${keys}`;
}

/**
 * Build DOM map with optional cache. Use cacheKey to force refresh (e.g. after section add/remove/reorder).
 * When cacheKey is not provided, a fingerprint from the document is used (counts + section keys length).
 */
export function buildDOMMapCached(
  doc: Document,
  options?: { cacheKey?: string; maxAgeMs?: number }
): DOMMap {
  const maxAge = options?.maxAgeMs ?? DEFAULT_CACHE_MAX_AGE_MS;
  const key = options?.cacheKey ?? getDocFingerprint(doc);
  const cached = domMapCache.get(key);
  if (cached && Date.now() - cached.builtAt < maxAge) {
    return cached.map;
  }
  const map = buildDOMMap(doc);
  domMapCache.set(key, { map, builtAt: Date.now() });
  return map;
}

/** Invalidate DOM map cache (call when sections added/removed/reordered). */
export function invalidateDOMMapCache(): void {
  domMapCache.clear();
}

/**
 * Phase 11: observe DOM mutations and invalidate cache when layout changes.
 * Call when the preview iframe document is ready; returns a disconnect function.
 */
export function observeDOMMapInvalidation(
  doc: Document,
  onInvalidate: () => void = invalidateDOMMapCache
): () => void {
  const root = doc.body ?? doc.documentElement;
  if (!root) return () => {};

  const observer = new MutationObserver(() => {
    onInvalidate();
  });
  observer.observe(root, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['data-webu-section', 'data-webu-field', 'data-webu-field-url', 'data-webu-field-scope', 'data-webu-section-local-id'],
  });
  return () => observer.disconnect();
}

/**
 * Debug: return a summary of the map for visualization (component boundaries, element identifiers).
 * Use in optional debug mode to show labels overlays or console.
 */
export function getDOMMapDebugInfo(map: DOMMap): { sections: Array<{ shortName: string; localId: string | null; elementIds: string[] }> } {
  return {
    sections: map.sections.map((s) => ({
      shortName: s.shortName,
      localId: s.sectionLocalId,
      elementIds: s.elements.map((e) => e.elementId),
    })),
  };
}

export interface DebugOverlayBox {
  left: number;
  top: number;
  width: number;
  height: number;
  label: string;
  kind: 'section' | 'element';
  localId?: string | null;
  elementId?: string;
}

/**
 * Optional debug mode: get bounding boxes and labels for each section and mapped element
 * so the UI can draw an overlay (e.g. component boundaries, element identifiers).
 * Coordinates are relative to the document (iframe).
 */
export function getDOMMapDebugOverlays(doc: Document): DebugOverlayBox[] {
  const map = buildDOMMapCached(doc);
  const boxes: DebugOverlayBox[] = [];
  const sectionHosts = doc.querySelectorAll<HTMLElement>('[data-webu-section]');
  sectionHosts.forEach((host, i) => {
    const rect = host.getBoundingClientRect();
    const section = map.sections[i];
    const label = section ? `${section.shortName}${section.sectionLocalId ? ` (${section.sectionLocalId})` : ''}` : `Section ${i}`;
    boxes.push({
      left: rect.left,
      top: rect.top,
      width: rect.width,
      height: rect.height,
      label,
      kind: 'section',
      localId: section?.sectionLocalId ?? null,
    });
    if (section) {
      section.elements.forEach((el) => {
        const fieldEl = host.querySelector<HTMLElement>(`[data-webu-field="${escapeAttributeValue(el.parameterName)}"], [data-webu-field-url="${escapeAttributeValue(el.parameterName)}"]`);
        if (fieldEl) {
          const r = fieldEl.getBoundingClientRect();
          boxes.push({
            left: r.left,
            top: r.top,
            width: r.width,
            height: r.height,
            label: el.elementId,
            kind: 'element',
            elementId: el.elementId,
          });
        }
      });
    }
  });
  return boxes;
}
