import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import * as ts from 'typescript';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const installRoot = path.resolve(__dirname, '..');
const cmsPath = path.join(installRoot, 'resources/js/Pages/Project/Cms.tsx');
const docsRoot = path.join(installRoot, 'docs/architecture');
const generatedDir = path.join(docsRoot, 'generated');
const jsonOutPath = path.join(generatedDir, 'cms-builder-control-group-audit.json');
const mdOutPath = path.join(docsRoot, 'CMS_BUILDER_CONTROL_PANEL_AUDIT.md');

const sourceText = fs.readFileSync(cmsPath, 'utf8');
const sourceFile = ts.createSourceFile(cmsPath, sourceText, ts.ScriptTarget.Latest, true, ts.ScriptKind.TSX);

const stringConstValues = new Map();

function propertyNameText(name) {
    if (!name) return null;
    if (ts.isIdentifier(name) || ts.isStringLiteral(name) || ts.isNumericLiteral(name)) {
        return String(name.text);
    }
    return null;
}

function getObjectProperty(node, propertyName) {
    if (!ts.isObjectLiteralExpression(node)) return null;
    for (const prop of node.properties) {
        if (!ts.isPropertyAssignment(prop)) continue;
        const name = propertyNameText(prop.name);
        if (name === propertyName) return prop;
    }
    return null;
}

function getStringLiteral(node) {
    if (!node) return null;
    if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) {
        return node.text;
    }
    return null;
}

function getExpressionText(node) {
    if (!node) return null;
    try {
        return node.getText(sourceFile);
    } catch {
        return null;
    }
}

function resolveStringish(node) {
    const direct = getStringLiteral(node);
    if (direct !== null) return direct;
    if (node && ts.isIdentifier(node)) {
        return stringConstValues.get(node.text) ?? node.text;
    }
    return getExpressionText(node);
}

function visitForConstStrings(node) {
    if (ts.isVariableDeclaration(node) && ts.isIdentifier(node.name) && node.initializer) {
        const value = getStringLiteral(node.initializer);
        if (value !== null) {
            stringConstValues.set(node.name.text, value);
        }
    }
    ts.forEachChild(node, visitForConstStrings);
}

visitForConstStrings(sourceFile);

function collectPrimitiveFields(propertiesNode, pathSegments = []) {
    const fields = [];
    if (!ts.isObjectLiteralExpression(propertiesNode)) return fields;

    for (const prop of propertiesNode.properties) {
        if (!ts.isPropertyAssignment(prop)) continue;
        const key = propertyNameText(prop.name);
        if (!key) continue;
        const nextPath = [...pathSegments, key];
        const initializer = prop.initializer;

        if (!ts.isObjectLiteralExpression(initializer)) {
            continue;
        }

        const nestedPropertiesProp = getObjectProperty(initializer, 'properties');
        const nestedPropertiesNode = nestedPropertiesProp?.initializer;
        if (nestedPropertiesNode && ts.isObjectLiteralExpression(nestedPropertiesNode)) {
            fields.push(...collectPrimitiveFields(nestedPropertiesNode, nextPath));
            continue;
        }

        const typeNode = getObjectProperty(initializer, 'type')?.initializer;
        const type = resolveStringish(typeNode);
        if (!type) {
            continue;
        }

        fields.push({
            path: nextPath,
            type,
            title: resolveStringish(getObjectProperty(initializer, 'title')?.initializer),
            format: resolveStringish(getObjectProperty(initializer, 'format')?.initializer),
        });
    }

    return fields;
}

function classifyField(field) {
    const path = field.path.join('.');
    const pathLower = path.toLowerCase();
    const root = (field.path[0] ?? '').toLowerCase();
    const title = typeof field.title === 'string' ? field.title : '';
    const titleLower = title.toLowerCase();

    if (root === 'style') {
        return { group: 'style', reason: 'explicit_path', confidence: 'high' };
    }
    if (root === 'advanced') {
        return { group: 'advanced', reason: 'explicit_path', confidence: 'high' };
    }
    if (titleLower.startsWith('style:')) {
        return { group: 'style', reason: 'title_prefix', confidence: 'high' };
    }
    if (titleLower.startsWith('advanced:')) {
        return { group: 'advanced', reason: 'title_prefix', confidence: 'high' };
    }

    const advancedPatterns = [
        /(^|[._-])(html_id|css_class|class_name|custom_css|custom_html)($|[._-])/,
        /(^|[._-])(attribute|attributes|attr|attrs)($|[._-])/,
        /(^|[._-])(aria|data_attr|data_attribute)($|[._-])/,
        /(^|[._-])(visibility|hidden|hide_on|show_on)($|[._-])/,
        /(^|[._-])(z_index|zindex|position|offset|top|left|right|bottom)($|[._-])/,
        /(^|[._-])(opacity_percent)($|[._-])/,
    ];
    const stylePatterns = [
        /(^|[._-])(color|background|bg|overlay|border|radius|shadow)($|[._-])/,
        /(^|[._-])(padding|margin|gap|spacing)($|[._-])/,
        /(^|[._-])(font|typography|line_height|letter_spacing|text_align|text_transform)($|[._-])/,
        /(^|[._-])(width|height|min_height|min_width|max_height|max_width|columns|rows|aspect_ratio)($|[._-])/,
        /(^|[._-])(display|align|justify|fit|size|icon_size)($|[._-])/,
        /(^|[._-])(accent_color|panel_background_color|panel_border_color)($|[._-])/,
    ];

    if (advancedPatterns.some((pattern) => pattern.test(pathLower))) {
        return { group: 'advanced', reason: 'heuristic_key', confidence: 'medium' };
    }
    if (stylePatterns.some((pattern) => pattern.test(pathLower))) {
        return { group: 'style', reason: 'heuristic_key', confidence: 'medium' };
    }

    return { group: 'content', reason: 'default_content', confidence: 'medium' };
}

function extractSectionDefinitions() {
    const sections = [];
    const seenKeys = new Set();

    function visit(node) {
        if (ts.isObjectLiteralExpression(node)) {
            const keyNode = getObjectProperty(node, 'key')?.initializer;
            const labelNode = getObjectProperty(node, 'label')?.initializer;
            const schemaNode = getObjectProperty(node, 'schema_json')?.initializer;

            if (keyNode && labelNode && schemaNode && ts.isObjectLiteralExpression(schemaNode)) {
                const key = resolveStringish(keyNode);
                const label = resolveStringish(labelNode);
                const category = resolveStringish(getObjectProperty(node, 'category')?.initializer);
                const htmlTemplate = resolveStringish(getObjectProperty(schemaNode, 'html_template')?.initializer);
                const propertiesNode = getObjectProperty(schemaNode, 'properties')?.initializer;

                if (
                    typeof key === 'string'
                    && key.trim() !== ''
                    && typeof label === 'string'
                    && label.trim() !== ''
                    && typeof htmlTemplate === 'string'
                    && htmlTemplate.includes('data-webu-section=')
                    && propertiesNode
                    && ts.isObjectLiteralExpression(propertiesNode)
                    && !seenKeys.has(key)
                ) {
                    seenKeys.add(key);

                    const rawFields = collectPrimitiveFields(propertiesNode);
                    const fields = rawFields.map((field) => {
                        const classification = classifyField(field);
                        return {
                            ...field,
                            ...classification,
                        };
                    });

                    const groupCounts = { content: 0, style: 0, advanced: 0 };
                    const reasonCounts = {};
                    for (const field of fields) {
                        groupCounts[field.group] += 1;
                        reasonCounts[field.reason] = (reasonCounts[field.reason] ?? 0) + 1;
                    }

                    sections.push({
                        key,
                        label,
                        category: typeof category === 'string' ? category : null,
                        field_count: fields.length,
                        group_counts: groupCounts,
                        reason_counts: reasonCounts,
                        fields: fields.map((field) => ({
                            path: field.path.join('.'),
                            type: field.type,
                            title: field.title ?? null,
                            format: field.format ?? null,
                            group: field.group,
                            reason: field.reason,
                            confidence: field.confidence,
                        })),
                    });
                }
            }
        }

        ts.forEachChild(node, visit);
    }

    visit(sourceFile);

    sections.sort((a, b) => a.key.localeCompare(b.key));
    return sections;
}

const sections = extractSectionDefinitions();

const totals = {
    sections: sections.length,
    fields: 0,
    groups: { content: 0, style: 0, advanced: 0 },
    reasons: {},
    confidence: { high: 0, medium: 0, low: 0 },
};

const heuristicExamples = [];

for (const section of sections) {
    totals.fields += section.field_count;
    for (const group of ['content', 'style', 'advanced']) {
        totals.groups[group] += section.group_counts[group] ?? 0;
    }
    for (const [reason, count] of Object.entries(section.reason_counts)) {
        totals.reasons[reason] = (totals.reasons[reason] ?? 0) + count;
    }

    for (const field of section.fields) {
        totals.confidence[field.confidence] = (totals.confidence[field.confidence] ?? 0) + 1;
        if (field.reason === 'heuristic_key' && heuristicExamples.length < 80) {
            heuristicExamples.push({
                section_key: section.key,
                path: field.path,
                title: field.title,
                group: field.group,
            });
        }
    }
}

const generatedAt = new Date().toISOString();

const auditPayload = {
    generated_at: generatedAt,
    source_file: path.relative(installRoot, cmsPath).replaceAll('\\', '/'),
    methodology: {
        parser: 'typescript-ast',
        scope: 'Section library entries with schema_json.properties and html_template containing data-webu-section',
        leaf_rule: 'Primitive schema fields (recursive properties traversal; object containers omitted)',
        grouping_rules: [
            'explicit_path: root path style/advanced',
            'title_prefix: field title starts with Style:/Advanced:',
            'heuristic_key: key/path naming patterns',
            'default_content: fallback for remaining fields',
        ],
    },
    totals,
    heuristic_examples: heuristicExamples,
    sections,
};

function buildMarkdown(payload) {
    const lines = [];
    lines.push('# CMS Builder Control Panel Audit');
    lines.push('');
    lines.push(`Generated: \`${payload.generated_at}\``);
    lines.push(`Source: \`${payload.source_file}\``);
    lines.push('');
    lines.push('## Summary');
    lines.push('');
    lines.push(`- Sections audited: **${payload.totals.sections}**`);
    lines.push(`- Primitive controls audited: **${payload.totals.fields}**`);
    lines.push(`- Content controls: **${payload.totals.groups.content}**`);
    lines.push(`- Style controls: **${payload.totals.groups.style}**`);
    lines.push(`- Advanced controls: **${payload.totals.groups.advanced}**`);
    lines.push('');
    lines.push('## Classification Method');
    lines.push('');
    lines.push('- `explicit_path`: root field path is `style.*` or `advanced.*`');
    lines.push('- `title_prefix`: field title starts with `Style:` / `Advanced:`');
    lines.push('- `heuristic_key`: group inferred from path/name patterns (used for current flat schemas)');
    lines.push('- `default_content`: fallback classification for remaining controls');
    lines.push('');
    lines.push('## Reason Breakdown');
    lines.push('');
    Object.entries(payload.totals.reasons)
        .sort((a, b) => a[0].localeCompare(b[0]))
        .forEach(([reason, count]) => {
            lines.push(`- \`${reason}\`: ${count}`);
        });
    lines.push('');
    lines.push('## Confidence Breakdown');
    lines.push('');
    Object.entries(payload.totals.confidence)
        .sort((a, b) => a[0].localeCompare(b[0]))
        .forEach(([level, count]) => {
            lines.push(`- \`${level}\`: ${count}`);
        });
    lines.push('');
    lines.push('## Top Sections By Control Count');
    lines.push('');

    payload.sections
        .slice()
        .sort((a, b) => b.field_count - a.field_count)
        .slice(0, 20)
        .forEach((section) => {
            lines.push(
                `- \`${section.key}\` (${section.category ?? 'unknown'}): ${section.field_count} controls ` +
                `(C:${section.group_counts.content} / S:${section.group_counts.style} / A:${section.group_counts.advanced})`
            );
        });

    lines.push('');
    lines.push('## Heuristic Mapping Sample (Review Candidates)');
    lines.push('');
    if (payload.heuristic_examples.length === 0) {
        lines.push('- None');
    } else {
        payload.heuristic_examples.slice(0, 40).forEach((field) => {
            const title = field.title ? ` — ${field.title}` : '';
            lines.push(`- \`${field.section_key}.${field.path}\` → **${field.group}**${title}`);
        });
    }
    lines.push('');
    lines.push('## Outputs');
    lines.push('');
    lines.push(`- Full machine-readable audit: \`docs/architecture/generated/${path.basename(jsonOutPath)}\``);
    lines.push('');
    lines.push('## Notes');
    lines.push('');
    lines.push('- This is an audit/mapping artifact (Phase 3 D1-01), not the final canonical control metadata model.');
    lines.push('- Flat fields are still grouped via heuristics until D1-03 introduces explicit control metadata.');
    lines.push('');

    return `${lines.join('\n')}\n`;
}

fs.mkdirSync(generatedDir, { recursive: true });
fs.writeFileSync(jsonOutPath, `${JSON.stringify(auditPayload, null, 2)}\n`, 'utf8');
fs.writeFileSync(mdOutPath, buildMarkdown(auditPayload), 'utf8');

console.log(`Wrote ${path.relative(installRoot, jsonOutPath)}`);
console.log(`Wrote ${path.relative(installRoot, mdOutPath)}`);
console.log(`Audited sections=${totals.sections} fields=${totals.fields} content=${totals.groups.content} style=${totals.groups.style} advanced=${totals.groups.advanced}`);
