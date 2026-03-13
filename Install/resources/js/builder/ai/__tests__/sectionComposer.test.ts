import { describe, expect, it } from 'vitest';

import { composeSectionProps } from '../sectionComposer';

describe('sectionComposer', () => {
  it('composes safe hero props from prompt context', () => {
    const props = composeSectionProps('webu_general_hero_01', {
      prompt: 'Create a cosmetics online store for Aura',
      projectType: 'ecommerce',
      brandName: 'Aura',
    });

    expect(props.title).toEqual(expect.any(String));
    expect(props.buttonText ?? props.buttonLabel).toBeTruthy();
    expect(props.buttonLink ?? props.buttonUrl).toBeTruthy();
    expect('notARealField' in props).toBe(false);
  });

  it('composes booking-friendly form props for clinics', () => {
    const props = composeSectionProps('webu_general_form_wrapper_01', {
      prompt: 'Create a veterinary clinic website',
      projectType: 'clinic',
    });

    expect(props.title).toContain('Book');
    expect(props.submit_label ?? props.buttonText).toBeTruthy();
  });
});
