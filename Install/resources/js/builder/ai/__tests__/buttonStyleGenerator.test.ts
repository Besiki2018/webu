import { generateButtonStyles, buttonStylesToCssVars } from '../buttonStyleGenerator';

describe('buttonStyleGenerator', () => {
  it('returns primary, secondary, outline, ghost styles', () => {
    const styles = generateButtonStyles({});
    expect(styles.primary).toBeDefined();
    expect(styles.primary.background).toContain('var(--color-');
    expect(styles.primary.color).toContain('var(--color-');
    expect(styles.primary.radius).toContain('var(--radius-');
    expect(styles.primary.padding).toBeDefined();
    expect(styles.secondary).toBeDefined();
    expect(styles.outline).toBeDefined();
    expect(styles.ghost).toBeDefined();
  });

  it('primary button uses primary color and md radius', () => {
    const styles = generateButtonStyles({});
    expect(styles.primary.background).toContain('primary');
    expect(styles.primary.color).toContain('primary-foreground');
    expect(styles.primary.radius).toContain('md');
  });

  it('outline has transparent background and border ref', () => {
    const styles = generateButtonStyles({});
    expect(styles.outline.background).toBe('transparent');
    expect(styles.outline.border).toBeDefined();
  });

  it('buttonStylesToCssVars produces --button-* vars', () => {
    const styles = generateButtonStyles({});
    const vars = buttonStylesToCssVars(styles);
    expect(Object.keys(vars).some((k) => k.startsWith('--button-primary-'))).toBe(true);
  });
});
