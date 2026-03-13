import { describe, expect, it } from 'vitest';

import {
  applyAiBuilderMutations,
  applyAiSitePlan,
  type AiBuilderRenderOptions,
} from '../builderRenderAdapter';
import { getDefaultProps } from '../../componentRegistry';
import { stringifySectionProps } from '../../state/sectionProps';
import { buildEditableTargetFromSection } from '../../editingState';
import type { BuilderSection } from '../../visual/treeUtils';

function createSection(sectionType: string, localId: string, props: Record<string, unknown> = {}): BuilderSection {
  const mergedProps = {
    ...getDefaultProps(sectionType),
    ...props,
  };

  return {
    localId,
    type: sectionType,
    props: mergedProps,
    propsText: stringifySectionProps(mergedProps),
    propsError: null,
    bindingMeta: null,
  };
}

function createOptions(): AiBuilderRenderOptions {
  let counter = 0;
  return {
    makeLocalId: () => {
      counter += 1;
      return `ai-section-${counter}`;
    },
    createSection: ({ sectionType, localId, props }) => createSection(sectionType, localId ?? `generated-${Date.now()}`, props),
  };
}

describe('builderRenderAdapter', () => {
  it('applies a full AI site plan through the canonical update pipeline', () => {
    const options = createOptions();
    const initialSection = createSection('webu_general_features_01', 'legacy-1', { title: 'Legacy' });
    const result = applyAiSitePlan({
      sectionsDraft: [initialSection],
      selectedSectionLocalId: initialSection.localId,
      selectedBuilderTarget: buildEditableTargetFromSection(initialSection),
    }, {
      projectType: 'ecommerce',
      builderProjectType: 'ecommerce',
      available_components: ['webu_header_01', 'webu_general_hero_01', 'webu_footer_01'],
      project: { type: 'ecommerce' },
      pages: [{
        name: 'home',
        sections: [
          { componentKey: 'webu_header_01', label: 'Header', layoutType: 'header' },
          { componentKey: 'webu_general_hero_01', label: 'Hero', layoutType: 'hero', props: { title: 'AI Hero' } },
          { componentKey: 'webu_footer_01', label: 'Footer', layoutType: 'footer' },
        ],
      }],
    }, options);

    expect(result.ok).toBe(true);
    expect(result.state.sectionsDraft).toHaveLength(3);
    expect(result.state.sectionsDraft[0]?.type).toBe('webu_header_01');
    expect(result.state.sectionsDraft[1]?.type).toBe('webu_general_hero_01');
    expect(result.state.sectionsDraft[1]?.props?.title).toBe('AI Hero');
  });

  it('sanitizes AI prop patches and keeps selection stable for targeted edits', () => {
    const options = createOptions();
    const hero = createSection('webu_general_hero_01', 'hero-1', { title: 'Before' });
    const result = applyAiBuilderMutations({
      sectionsDraft: [hero],
      selectedSectionLocalId: null,
      selectedBuilderTarget: null,
    }, [{
      kind: 'update-props',
      targetSectionLocalId: hero.localId,
      patch: {
        title: 'After',
        notARealField: 'ignore me',
      },
    }], options);

    expect(result.ok).toBe(true);
    expect(result.state.sectionsDraft[0]?.props?.title).toBe('After');
    expect(result.state.sectionsDraft[0]?.props && 'notARealField' in result.state.sectionsDraft[0].props).toBe(false);
    expect(result.state.selectedSectionLocalId).toBe(hero.localId);
    expect(result.state.selectedBuilderTarget?.sectionLocalId).toBe(hero.localId);
  });
});
