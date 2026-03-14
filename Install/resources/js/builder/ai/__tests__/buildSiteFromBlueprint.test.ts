import { describe, expect, it, vi } from 'vitest'
import { createBlueprint } from '../createBlueprint'
import { buildSiteFromBlueprint } from '../buildSiteFromBlueprint'

describe('buildSiteFromBlueprint design quality integration', () => {
  it('adds a design quality report and applies auto-improvements before returning the final tree', async () => {
    const infoSpy = vi.spyOn(console, 'info').mockImplementation(() => undefined)
    const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => undefined)
    const blueprint = createBlueprint({
      prompt: 'Create a modern SaaS landing page',
    })

    try {
      const result = await buildSiteFromBlueprint({
        prompt: 'Create a modern SaaS landing page',
        blueprint,
      })

      expect(result.diagnostics.designQualityReport).not.toBeNull()
      expect(result.diagnostics.designQualityReport?.initialOverallScore).toBeGreaterThan(0)
      expect(result.generationLog.some((entry) => entry.step === 'design_quality')).toBe(true)
      expect(result.diagnostics.designQualityReport?.autoImproved).toBe(true)
      expect(result.diagnostics.designQualityReport?.overallScore).toBeGreaterThanOrEqual(
        result.diagnostics.designQualityReport?.initialOverallScore ?? 0,
      )
      expect(result.diagnostics.designQualityReport?.improvementsApplied.length).toBeGreaterThan(0)
      expect(result.diagnostics.designQualityReport?.threshold).toBe(80)
      expect(result.generationLog.some((entry) => entry.step === 'design_improvement')).toBe(true)
      expect(result.diagnostics.stageTimingsMs.layoutPlanning).toBeGreaterThanOrEqual(0)
      expect(result.diagnostics.stageTimingsMs.componentSelection).toBeGreaterThanOrEqual(0)
      expect(result.diagnostics.stageTimingsMs.contentGeneration).toBeGreaterThanOrEqual(0)
      expect(result.diagnostics.stageTimingsMs.treeAssembly).toBeGreaterThanOrEqual(0)
      expect(result.diagnostics.stageTimingsMs.designOptimization).toBeGreaterThanOrEqual(0)
      expect(result.diagnostics.stageTimingsMs.validation).toBeGreaterThanOrEqual(0)
      expect(result.diagnostics.stageTimingsMs.previewRendering).toBeNull()

      expect(result.sitePlan.pages[0]?.sections.some((section) => (
        typeof section.props?.advanced === 'object'
        && section.props?.advanced !== null
      ))).toBe(true)
    } finally {
      infoSpy.mockRestore()
      errorSpy.mockRestore()
    }
  })
})
