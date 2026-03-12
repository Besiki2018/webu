/**
 * Structured output of the codebase scanner for AI context.
 */

export interface ScannedComponentField {
  parameterName: string;
  type: string;
  title: string;
  default?: unknown;
  format?: string;
}

export interface ScannedComponentEntry {
  component: string;
  path: string;
  label: string;
  fields: ScannedComponentField[];
  schema_json?: Record<string, unknown>;
}

export interface ProjectComponentParameters {
  sections: Record<string, ScannedComponentEntry>;
  components: Record<string, ScannedComponentEntry>;
  layouts: Record<string, ScannedComponentEntry>;
}

export interface ProjectStructure {
  pages: string[];
  sections: string[];
  components: string[];
  layouts: string[];
  styles: string[];
  public: string[];
  page_structure: Record<string, string[]>;
  component_parameters: ProjectComponentParameters;
  imports_sample?: Record<string, string>;
  file_contents?: Record<string, string>;
}

export interface CodebaseScanResult {
  success: true;
  structure: ProjectStructure;
  fromCache: boolean;
}

export interface CodebaseScanError {
  success: false;
  error: string;
  structure: ProjectStructure;
}

export type CodebaseScanOutput = CodebaseScanResult | CodebaseScanError;

export const EMPTY_STRUCTURE: ProjectStructure = {
  pages: [],
  sections: [],
  components: [],
  layouts: [],
  styles: [],
  public: [],
  page_structure: {},
  component_parameters: {
    sections: {},
    components: {},
    layouts: {},
  },
  imports_sample: {},
  file_contents: {},
};
