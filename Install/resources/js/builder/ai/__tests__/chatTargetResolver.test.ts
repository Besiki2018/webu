import { describe, expect, it } from 'vitest';

import { resolveChatTargetMutation } from '../chatTargetResolver';
import { getDefaultProps } from '../../componentRegistry';
import { stringifySectionProps } from '../../state/sectionProps';
import type { BuilderUpdateStateSnapshot } from '../../state/updatePipeline';

function createStateSnapshot(): BuilderUpdateStateSnapshot {
  const heroProps = getDefaultProps('webu_general_hero_01');
  const ctaProps = getDefaultProps('webu_general_cta_01');

  return {
    sectionsDraft: [
      {
        localId: 'hero-1',
        type: 'webu_general_hero_01',
        props: heroProps,
        propsText: stringifySectionProps(heroProps),
        propsError: null,
        bindingMeta: null,
      },
      {
        localId: 'cta-1',
        type: 'webu_general_cta_01',
        props: ctaProps,
        propsText: stringifySectionProps(ctaProps),
        propsError: null,
        bindingMeta: null,
      },
    ],
    selectedSectionLocalId: 'hero-1',
    selectedBuilderTarget: null,
  };
}

describe('chatTargetResolver', () => {
  it('resolves add-section prompts to allowed registry components', () => {
    const result = resolveChatTargetMutation('Add testimonials section', createStateSnapshot(), 'business');

    expect(result.ok).toBe(true);
    expect(result.mutations[0]).toMatchObject({
      kind: 'insert-section',
      sectionType: expect.stringContaining('testimonials'),
    });
  });

  it('resolves direct text edits into builder prop mutations', () => {
    const result = resolveChatTargetMutation('Change CTA text to "Book now"', createStateSnapshot(), 'business');

    expect(result.ok).toBe(true);
    expect(result.mutations[0]).toMatchObject({
      kind: 'update-props',
      targetSectionLocalId: 'cta-1',
    });
    expect((result.mutations[0] as { patch: Record<string, unknown> }).patch).toEqual(expect.objectContaining({
      buttonText: 'Book now',
    }));
  });

  it('resolves variant-style refinement commands', () => {
    const result = resolveChatTargetMutation('Make the hero more minimal', createStateSnapshot(), 'landing');

    expect(result.ok).toBe(true);
    expect(result.mutations[0]).toMatchObject({
      kind: 'replace-section',
      targetSectionLocalId: 'hero-1',
      sectionType: 'webu_general_hero_01',
    });
  });
});
