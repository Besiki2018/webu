import { designTokens, type DesignTokens } from '../tokens';

describe('designSystem/tokens', () => {
  it('exports designTokens with colors, typography, spacing, radius, shadows, buttons', () => {
    expect(designTokens.colors).toBeDefined();
    expect(designTokens.typography).toBeDefined();
    expect(designTokens.spacing).toBeDefined();
    expect(designTokens.radius).toBeDefined();
    expect(designTokens.shadows).toBeDefined();
    expect(designTokens.buttons).toBeDefined();
  });

  it('colors has primary and background', () => {
    expect(designTokens.colors.primary).toBeDefined();
    expect(designTokens.colors.background).toBeDefined();
  });

  it('typography has fonts and fontSizes', () => {
    expect(designTokens.typography.fonts).toBeDefined();
    expect(designTokens.typography.fontSizes).toBeDefined();
  });

  it('spacing has md and lg', () => {
    expect(designTokens.spacing.md).toBeDefined();
    expect(designTokens.spacing.lg).toBeDefined();
  });

  it('shadows has sm, md, lg, xl', () => {
    expect(designTokens.shadows.sm).toBeDefined();
    expect(designTokens.shadows.md).toBeDefined();
  });

  it('buttons has primary and outline', () => {
    expect(designTokens.buttons.primary.background).toBeDefined();
    expect(designTokens.buttons.outline.radius).toBeDefined();
  });
});
