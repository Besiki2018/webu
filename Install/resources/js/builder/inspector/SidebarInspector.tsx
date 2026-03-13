/**
 * Dynamic sidebar inspector — schema-driven.
 * Reads componentRegistry[componentKey].schema, loops through schema.props (or schema.fields),
 * generates UI by field type. No hardcoded inputs per component.
 */

import { useCallback, useMemo } from 'react';
import { getEntry } from '../componentRegistry';
import { useBuilderStore } from '../store/builderStore';
import { updateComponentProps } from '../updates';
import { useTranslation } from '@/contexts/LanguageContext';

export interface SchemaFieldDef {
  key: string;
  type: string;
  label?: string;
  default?: unknown;
  options?: Array<{ value: string; label?: string }>;
  group?: string;
}

function getFieldsFromSchema(schema: Record<string, unknown>): SchemaFieldDef[] {
  if (!schema || typeof schema !== 'object') return [];

  // schema.props: { [key]: { type, label, default, options?, group? } }
  const props = schema.props as Record<string, Record<string, unknown>> | undefined;
  if (props && typeof props === 'object') {
    return Object.entries(props).map(([key, def]) => ({
      key,
      type: (def?.type as string) ?? 'text',
      label: (def?.label as string) ?? key,
      default: def?.default,
      options: Array.isArray(def?.options)
        ? (def.options as Array<{ value: string; label?: string }>).map((o) =>
            typeof o === 'object' && o && 'value' in o
              ? { value: String((o as { value: string }).value), label: (o as { label?: string }).label }
              : { value: String(o) }
          )
        : undefined,
      group: (def?.group as string) ?? 'content',
    }));
  }

  // schema.fields: Array<{ path/key, type, label, default, options?, group? }>
  const fields = schema.fields as SchemaFieldDef[] | undefined;
  if (Array.isArray(fields)) {
    return fields.map((f) => ({
      key: (f as { path?: string }).path ?? f.key,
      type: f.type ?? 'text',
      label: f.label ?? f.key,
      default: f.default,
      options: f.options,
      group: f.group ?? 'content',
    }));
  }

  return [];
}

function FieldControl({
  field,
  value,
  onChange,
}: {
  field: SchemaFieldDef;
  value: unknown;
  onChange: (value: unknown) => void;
}) {
  const { t } = useTranslation();
  const type = (field.type ?? 'text').toLowerCase();
  const displayValue = value ?? field.default ?? '';
  const id = `inspector-${field.key}`;
  const tt = (key: string, fallback: string) => {
    const translated = t(key);
    return translated === key ? fallback : translated;
  };

  const handleChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
      const target = e.target;
      if (type === 'toggle' || type === 'boolean') {
        onChange((target as HTMLInputElement).checked);
        return;
      }
      if (type === 'number') {
        const n = parseFloat(target.value);
        onChange(Number.isNaN(n) ? 0 : n);
        return;
      }
      onChange(target.value);
    },
    [onChange, type]
  );

  if (type === 'textarea' || type === 'richtext') {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <textarea
          id={id}
          value={typeof displayValue === 'string' ? displayValue : ''}
          onChange={(e) => onChange(e.target.value)}
          rows={type === 'richtext' ? 4 : 3}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
          placeholder={type === 'richtext' ? tt('Rich text (HTML)', 'მდიდარი ტექსტი (HTML)') : undefined}
        />
      </div>
    );
  }

  if (type === 'number') {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <input
          id={id}
          type="number"
          value={typeof displayValue === 'number' ? displayValue : ''}
          onChange={handleChange}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        />
      </div>
    );
  }

  if (type === 'color') {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <div className="flex gap-2 items-center">
          <input
            id={id}
            type="color"
            value={typeof displayValue === 'string' ? displayValue : '#000000'}
            onChange={handleChange}
            className="h-9 w-14 rounded border border-slate-300 cursor-pointer"
          />
          <input
            type="text"
            value={typeof displayValue === 'string' ? displayValue : ''}
            onChange={(e) => onChange(e.target.value)}
            className="flex-1 rounded border border-slate-300 px-2 py-1.5 text-sm font-mono"
          />
        </div>
      </div>
    );
  }

  if (type === 'image' || type === 'icon') {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <input
          id={id}
          type="text"
          placeholder={tt('URL or upload', 'URL ან ატვირთვა')}
          value={typeof displayValue === 'string' ? displayValue : ''}
          onChange={(e) => onChange(e.target.value)}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        />
      </div>
    );
  }

  if (type === 'link') {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <input
          id={id}
          type="url"
          value={typeof displayValue === 'string' ? displayValue : ''}
          onChange={handleChange}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        />
      </div>
    );
  }

  if ((type === 'select' || type === 'alignment') && Array.isArray(field.options) && field.options.length > 0) {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <select
          id={id}
          value={typeof displayValue === 'string' ? displayValue : String(field.default ?? '')}
          onChange={handleChange}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        >
          {field.options.map((opt) => (
            <option key={opt.value} value={opt.value}>
              {opt.label ?? opt.value}
            </option>
          ))}
        </select>
      </div>
    );
  }

  if (type === 'toggle' || type === 'boolean') {
    return (
      <div className="flex items-center gap-2">
        <input
          id={id}
          type="checkbox"
          checked={Boolean(displayValue)}
          onChange={handleChange}
          className="rounded border-slate-300"
        />
        <label htmlFor={id} className="text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
      </div>
    );
  }

  // Spacing control: presets or custom CSS value (Phase 3)
  if (type === 'spacing') {
    const presets = [
      { value: '', label: tt('Default', 'ნაგულისხმები') },
      { value: '0', label: tt('None', 'არცერთი') },
      { value: '0.25rem', label: tt('Tight', 'მჭიდრო') },
      { value: '0.5rem', label: tt('Small', 'პატარა') },
      { value: '1rem', label: tt('Medium', 'საშუალო') },
      { value: '1.5rem', label: tt('Large', 'დიდი') },
      { value: '2rem', label: tt('X-Large', 'ძალიან დიდი') },
    ];
    const strVal = typeof displayValue === 'string' ? displayValue : '';
    const hasPreset = presets.some((p) => p.value === strVal);
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <select
          id={id}
          value={hasPreset ? strVal : '__custom'}
          onChange={(e) => {
            const v = e.target.value;
            if (v === '__custom') {
              onChange(hasPreset ? '0.75rem' : strVal);
            } else {
              onChange(v);
            }
          }}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
        >
          {presets.map((p) => (
            <option key={p.value || 'default'} value={p.value}>
              {p.label}
            </option>
          ))}
          <option value="__custom">{tt('Custom…', 'ხელით…')}</option>
        </select>
        {!hasPreset && (
          <input
            type="text"
            value={strVal}
            onChange={(e) => onChange(e.target.value)}
            placeholder={tt('e.g. 1rem 2rem', 'მაგ. 1rem 2rem')}
            className="mt-1 w-full rounded border border-slate-300 px-2 py-1.5 text-sm font-mono"
          />
        )}
      </div>
    );
  }

  if (type === 'menu' || type === 'grid' || type === 'repeater') {
    return (
      <div className="space-y-1">
        <label htmlFor={id} className="block text-sm font-medium text-slate-700">
          {field.label ?? field.key}
        </label>
        <input
          id={id}
          type="text"
          value={typeof displayValue === 'string' ? displayValue : JSON.stringify(displayValue ?? '')}
          onChange={(e) => {
            try {
              const v = JSON.parse(e.target.value || '""');
              onChange(v);
            } catch {
              onChange(e.target.value);
            }
          }}
          placeholder={tt('JSON value', 'JSON მნიშვნელობა')}
          className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm font-mono"
        />
      </div>
    );
  }

  // text (default)
  return (
    <div className="space-y-1">
      <label htmlFor={id} className="block text-sm font-medium text-slate-700">
        {field.label ?? field.key}
      </label>
      <input
        id={id}
        type="text"
        value={typeof displayValue === 'string' ? displayValue : String(displayValue ?? '')}
        onChange={handleChange}
        className="w-full rounded border border-slate-300 px-2 py-1.5 text-sm"
      />
    </div>
  );
}

export interface SidebarInspectorProps {
  className?: string;
}

/**
 * Renders the sidebar inspector from registry schema only.
 * Reads componentRegistry[componentKey].schema, loops schema.props (or schema.fields), generates controls by type.
 */
export function SidebarInspector({ className }: SidebarInspectorProps) {
  const { t } = useTranslation();
  const selectedComponentId = useBuilderStore((s) => s.selectedComponentId);
  const componentTree = useBuilderStore((s) => s.componentTree);
  const tt = useCallback((key: string, fallback: string) => {
    const translated = t(key);
    return translated === key ? fallback : translated;
  }, [t]);
  const groupLabel = useCallback((group: string) => {
    const normalized = group.trim().toLowerCase();
    const fallbacks: Record<string, string> = {
      content: 'კონტენტი',
      layout: 'განლაგება',
      style: 'სტილი',
      advanced: 'დამატებითი',
      settings: 'პარამეტრები',
    };

    return tt(group, fallbacks[normalized] ?? group);
  }, [tt]);

  const { node, entry, fields } = useMemo(() => {
    if (!selectedComponentId || !componentTree?.length) {
      return { node: null, entry: null, fields: [] as SchemaFieldDef[] };
    }
    const node = componentTree.find((n) => n.id === selectedComponentId) ?? null;
    if (!node) return { node: null, entry: null, fields: [] as SchemaFieldDef[] };
    const entry = getEntry(node.componentKey);
    if (!entry?.schema || typeof entry.schema !== 'object') {
      return { node, entry: null, fields: [] as SchemaFieldDef[] };
    }
    const fields = getFieldsFromSchema(entry.schema as Record<string, unknown>);
    return { node, entry, fields };
  }, [selectedComponentId, componentTree]);

  const handleFieldChange = useCallback(
    (key: string, value: unknown) => {
      if (!node) return;
      const result = updateComponentProps(node.id, { path: key, value });
      if (!result.ok) return;
    },
    [node]
  );

  if (!selectedComponentId) {
    return (
      <aside className={className} data-builder-inspector aria-label={tt('Builder inspector', 'ბილდერის ინსპექტორი')}>
        <div className="p-4 text-slate-500 text-sm">{tt('Select an element on the canvas', 'კანვასზე აირჩიეთ ელემენტი')}</div>
      </aside>
    );
  }

  if (!node) {
    return (
      <aside className={className} data-builder-inspector aria-label={tt('Builder inspector', 'ბილდერის ინსპექტორი')}>
        <div className="p-4 text-slate-500 text-sm">{tt('Selected element not found', 'არჩეული ელემენტი ვერ მოიძებნა')}</div>
      </aside>
    );
  }

  if (!entry) {
    return (
      <aside className={className} data-builder-inspector aria-label={tt('Builder inspector', 'ბილდერის ინსპექტორი')}>
        <div className="p-4 text-slate-500 text-sm">{tt('No schema for', 'სქემა არ მოიძებნა')} {node.componentKey}</div>
      </aside>
    );
  }

  const byGroup = useMemo(() => {
    const map = new Map<string, SchemaFieldDef[]>();
    for (const f of fields) {
      const g = f.group ?? 'content';
      if (!map.has(g)) map.set(g, []);
      map.get(g)!.push(f);
    }
    return map;
  }, [fields]);

  return (
    <aside className={className} data-builder-inspector aria-label={tt('Builder inspector', 'ბილდერის ინსპექტორი')}>
      <div className="p-3 border-b border-slate-200">
        <h2 className="text-sm font-semibold text-slate-800 truncate" title={node.componentKey}>
          {node.componentKey}
        </h2>
        <p className="text-xs text-slate-500 mt-0.5">ID: {node.id}</p>
      </div>
      <div className="p-3 space-y-4 overflow-y-auto">
        {Array.from(byGroup.entries()).map(([group, groupFields]) => (
          <section key={group}>
            <h3 className="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">{groupLabel(group)}</h3>
            <div className="space-y-3">
              {groupFields.map((field) => (
                <FieldControl
                  key={field.key}
                  field={field}
                  value={field.key === 'variant' ? (node.variant ?? node.props[field.key]) : node.props[field.key]}
                  onChange={(value) => handleFieldChange(field.key, value)}
                />
              ))}
            </div>
          </section>
        ))}
      </div>
    </aside>
  );
}
