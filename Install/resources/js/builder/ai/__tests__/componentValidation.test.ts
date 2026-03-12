/**
 * Part 11 — Component Validation tests.
 */
import {
  validateGeneratedComponentFolder,
  validateGeneratedComponentInRegistry,
  runGenerationWithValidation,
  DEFAULT_MAX_VALIDATION_RETRIES,
} from '../componentValidation';
import { generateComponentFolder } from '../componentFolderGenerator';
import { generateComponentSpec } from '../componentSpecGenerator';
import type { GeneratedComponentFolder } from '../componentFolderGenerator';

describe('componentValidation', () => {
  describe('validateGeneratedComponentFolder', () => {
    it('passes when folder has schema, defaults, valid TSX', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table', designStyle: 'modern' });
      const folder = generateComponentFolder(spec);
      const result = validateGeneratedComponentFolder(folder);
      expect(result.valid).toBe(true);
      expect(result.errors).toHaveLength(0);
      expect(result.checks.schemaExists.passed).toBe(true);
      expect(result.checks.defaultsExist.passed).toBe(true);
      expect(result.checks.componentCompiles.passed).toBe(true);
      expect(result.checks.builderCanEditProps.passed).toBe(true);
    });

    it('fails when schema file is missing', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const folder = generateComponentFolder(spec);
      const filesWithoutSchema = folder.files.filter((f) => !f.path.endsWith('.schema.ts'));
      const badFolder: GeneratedComponentFolder = { ...folder, files: filesWithoutSchema };
      const result = validateGeneratedComponentFolder(badFolder);
      expect(result.valid).toBe(false);
      expect(result.checks.schemaExists.passed).toBe(false);
      expect(result.errors.some((e) => e.includes('schemaExists'))).toBe(true);
    });

    it('fails when defaults file is missing', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const folder = generateComponentFolder(spec);
      const filesWithoutDefaults = folder.files.filter((f) => !f.path.endsWith('.defaults.ts'));
      const badFolder: GeneratedComponentFolder = { ...folder, files: filesWithoutDefaults };
      const result = validateGeneratedComponentFolder(badFolder);
      expect(result.valid).toBe(false);
      expect(result.checks.defaultsExist.passed).toBe(false);
    });

    it('fails when component TSX has no default export', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const folder = generateComponentFolder(spec);
      const tsxFile = folder.files.find((f) => f.path.endsWith('.tsx'));
      const badFolder: GeneratedComponentFolder = {
        ...folder,
        files: folder.files.map((f) =>
          f.path.endsWith('.tsx')
            ? { path: f.path, content: 'const x = 1;' }
            : f
        ),
      };
      const result = validateGeneratedComponentFolder(badFolder);
      expect(result.valid).toBe(false);
      expect(result.checks.componentCompiles.passed).toBe(false);
    });

    it('with getEntry and registryId fails registry checks when entry missing', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const folder = generateComponentFolder(spec);
      const getEntry = () => null;
      const result = validateGeneratedComponentFolder(folder, {
        registryId: 'webu_general_pricing_table_01',
        getEntry,
      });
      expect(result.checks.registryUpdated.passed).toBe(false);
      expect(result.checks.componentRenders.passed).toBe(false);
      expect(result.valid).toBe(false);
    });

    it('with getEntry and registryId passes when entry has schema, defaults, component', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const folder = generateComponentFolder(spec);
      const getEntry = () => ({
        schema: { component: 'pricing', editableFields: [{ key: 'title', type: 'text' }] },
        defaults: { title: 'Plans' },
        component: function Pricing() {
          return null;
        },
      });
      const result = validateGeneratedComponentFolder(folder, {
        registryId: 'webu_general_pricing_table_01',
        getEntry,
      });
      expect(result.valid).toBe(true);
      expect(result.checks.registryUpdated.passed).toBe(true);
      expect(result.checks.componentRenders.passed).toBe(true);
      expect(result.checks.builderCanEditProps.passed).toBe(true);
    });
  });

  describe('validateGeneratedComponentInRegistry', () => {
    it('fails when getEntry returns null', () => {
      const result = validateGeneratedComponentInRegistry('webu_general_pricing_01', () => null);
      expect(result.valid).toBe(false);
      expect(result.checks.registryUpdated.passed).toBe(false);
      expect(result.checks.schemaExists.passed).toBe(false);
    });

    it('passes when entry has schema, defaults, component function', () => {
      const result = validateGeneratedComponentInRegistry('webu_general_pricing_01', () => ({
        schema: { component: 'pricing', editableFields: [{ key: 'title', type: 'text' }] },
        defaults: { title: 'Plans' },
        component: function C() {
          return null;
        },
      }));
      expect(result.valid).toBe(true);
      expect(result.checks.componentRenders.passed).toBe(true);
      expect(result.checks.builderCanEditProps.passed).toBe(true);
    });

    it('fails when entry.component is not a function', () => {
      const result = validateGeneratedComponentInRegistry('webu_general_pricing_01', () => ({
        schema: { editableFields: [] },
        defaults: {},
        component: 'not-a-function',
      }));
      expect(result.valid).toBe(false);
      expect(result.checks.componentRenders.passed).toBe(false);
    });
  });

  describe('runGenerationWithValidation', () => {
    it('returns folder and validation on first success', () => {
      const spec = generateComponentSpec({ prompt: 'Create pricing table' });
      const out = runGenerationWithValidation(
        () => generateComponentFolder(spec),
        (folder) => validateGeneratedComponentFolder(folder)
      );
      expect('folder' in out).toBe(true);
      expect(out.validation.valid).toBe(true);
      expect(out.attempt).toBe(1);
    });

    it('regenerates on failure up to maxRetries then returns failure', () => {
      let calls = 0;
      const out = runGenerationWithValidation(
        () => {
          calls += 1;
          const spec = generateComponentSpec({ prompt: 'Create pricing table' });
          const folder = generateComponentFolder(spec);
          return calls === 1 ? { ...folder, files: [] } : folder;
        },
        (folder) => validateGeneratedComponentFolder(folder),
        2
      );
      expect(out.validation.valid).toBe(true);
      expect(out.attempt).toBe(2);
      expect(calls).toBe(2);
    });

    it('returns failure when all attempts fail', () => {
      const out = runGenerationWithValidation(
        () => {
          const spec = generateComponentSpec({ prompt: 'Create pricing table' });
          const folder = generateComponentFolder(spec);
          return { ...folder, files: [] };
        },
        (folder) => validateGeneratedComponentFolder(folder),
        1
      );
      expect('folder' in out).toBe(false);
      expect(out.validation.valid).toBe(false);
      expect(out.attempt).toBe(2);
    });
  });

  it('DEFAULT_MAX_VALIDATION_RETRIES is 2', () => {
    expect(DEFAULT_MAX_VALIDATION_RETRIES).toBe(2);
  });
});
