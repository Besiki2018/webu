/**
 * Builder commands — entry points for UI and AI.
 */

export {
  runOptimizeForProjectType,
  OPTIMIZE_FOR_PROJECT_TYPE_COMMAND,
  type OptimizeForProjectTypeResult,
} from './optimizeForProjectType';

export {
  runGenerateSite,
  GENERATE_SITE_COMMAND,
  type GenerateSiteParams,
  type GenerateSiteResult,
} from './generateSite';

export {
  runExportWebsite,
  EXPORT_WEBSITE_COMMAND,
  type ExportWebsiteParams,
  type ExportWebsiteResult,
} from './exportWebsite';
