import type { ProjectSiteType, ProjectType } from '../projectTypes';
import { normalizeProjectSiteType } from '../projectTypes';

export const AI_PROJECT_TYPES = [
    'landing',
    'business',
    'ecommerce',
    'booking',
    'portfolio',
    'clinic',
    'restaurant',
    'saas',
    'blog',
    'education',
] as const;

export type AiProjectType = (typeof AI_PROJECT_TYPES)[number];

export interface ProjectTypeDetectionResult {
    projectType: AiProjectType;
    builderProjectType: ProjectType;
    siteType: ProjectSiteType;
    confidence: number;
    matchedKeywords: string[];
}

interface DetectionRule {
    projectType: AiProjectType;
    keywords: string[];
    builderProjectType: ProjectType;
    siteType: ProjectSiteType;
    confidence: number;
}

const DETECTION_RULES: DetectionRule[] = [
    {
        projectType: 'ecommerce',
        keywords: ['online store', 'e-commerce', 'ecommerce', 'shop', 'store', 'marketplace', 'sell online', 'products'],
        builderProjectType: 'ecommerce',
        siteType: 'ecommerce',
        confidence: 0.95,
    },
    {
        projectType: 'clinic',
        keywords: ['clinic', 'medical', 'doctor', 'dentist', 'veterinary', 'veterinarian', 'hospital', 'healthcare'],
        builderProjectType: 'business',
        siteType: 'booking',
        confidence: 0.93,
    },
    {
        projectType: 'restaurant',
        keywords: ['restaurant', 'cafe', 'café', 'food', 'menu', 'dining', 'bistro'],
        builderProjectType: 'restaurant',
        siteType: 'booking',
        confidence: 0.92,
    },
    {
        projectType: 'booking',
        keywords: ['booking', 'reservation', 'appointments', 'schedule', 'book now', 'availability'],
        builderProjectType: 'hotel',
        siteType: 'booking',
        confidence: 0.91,
    },
    {
        projectType: 'saas',
        keywords: ['saas', 'software', 'app', 'platform', 'dashboard', 'workspace', 'automation', 'ai tool'],
        builderProjectType: 'saas',
        siteType: 'website',
        confidence: 0.9,
    },
    {
        projectType: 'portfolio',
        keywords: ['portfolio', 'showcase', 'my work', 'case studies', 'creative', 'photography'],
        builderProjectType: 'portfolio',
        siteType: 'website',
        confidence: 0.88,
    },
    {
        projectType: 'blog',
        keywords: ['blog', 'articles', 'stories', 'editorial', 'magazine', 'news'],
        builderProjectType: 'blog',
        siteType: 'website',
        confidence: 0.86,
    },
    {
        projectType: 'education',
        keywords: ['academy', 'course', 'learning', 'school', 'education', 'training'],
        builderProjectType: 'education',
        siteType: 'website',
        confidence: 0.85,
    },
    {
        projectType: 'business',
        keywords: ['agency', 'business', 'company', 'consulting', 'services', 'firm', 'studio'],
        builderProjectType: 'business',
        siteType: 'website',
        confidence: 0.82,
    },
    {
        projectType: 'landing',
        keywords: ['landing', 'campaign', 'launch', 'marketing', 'promo', 'lead generation', 'conversion'],
        builderProjectType: 'landing',
        siteType: 'landing',
        confidence: 0.8,
    },
];

const DEFAULT_RESULT: ProjectTypeDetectionResult = {
    projectType: 'landing',
    builderProjectType: 'landing',
    siteType: 'landing',
    confidence: 0.35,
    matchedKeywords: [],
};

function normalizePrompt(prompt: string): string {
    return prompt.toLowerCase().trim().replace(/\s+/g, ' ');
}

export function mapAiProjectTypeToBuilderProjectType(projectType: AiProjectType): ProjectType {
    switch (projectType) {
        case 'ecommerce':
            return 'ecommerce';
        case 'restaurant':
            return 'restaurant';
        case 'saas':
            return 'saas';
        case 'portfolio':
            return 'portfolio';
        case 'blog':
            return 'blog';
        case 'education':
            return 'education';
        case 'business':
            return 'business';
        case 'landing':
            return 'landing';
        case 'booking':
            return 'hotel';
        case 'clinic':
            return 'business';
        default:
            return 'landing';
    }
}

export function mapAiProjectTypeToSiteType(projectType: AiProjectType): ProjectSiteType {
    switch (projectType) {
        case 'ecommerce':
            return 'ecommerce';
        case 'booking':
        case 'clinic':
        case 'restaurant':
            return 'booking';
        case 'landing':
            return 'landing';
        default:
            return 'website';
    }
}

export function inferAiProjectTypeFromBuilderProjectType(projectType: ProjectType | string | null | undefined): AiProjectType {
    const normalized = typeof projectType === 'string' ? projectType.trim().toLowerCase() : '';
    switch (normalized) {
        case 'ecommerce':
            return 'ecommerce';
        case 'restaurant':
            return 'restaurant';
        case 'hotel':
            return 'booking';
        case 'portfolio':
            return 'portfolio';
        case 'saas':
            return 'saas';
        case 'blog':
            return 'blog';
        case 'education':
            return 'education';
        case 'business':
            return 'business';
        case 'landing':
            return 'landing';
        default:
            return normalizeProjectSiteType(projectType) === 'booking' ? 'booking' : 'landing';
    }
}

export function detectProjectType(prompt: string): ProjectTypeDetectionResult {
    const normalizedPrompt = normalizePrompt(prompt);
    if (normalizedPrompt === '') {
        return DEFAULT_RESULT;
    }

    let bestMatch: ProjectTypeDetectionResult | null = null;

    for (const rule of DETECTION_RULES) {
        const matchedKeywords = rule.keywords.filter((keyword) => normalizedPrompt.includes(keyword));
        if (matchedKeywords.length === 0) {
            continue;
        }

        const confidence = Math.min(0.99, rule.confidence + (matchedKeywords.length - 1) * 0.01);
        const candidate: ProjectTypeDetectionResult = {
            projectType: rule.projectType,
            builderProjectType: rule.builderProjectType,
            siteType: rule.siteType,
            confidence,
            matchedKeywords,
        };

        if (!bestMatch || candidate.confidence > bestMatch.confidence) {
            bestMatch = candidate;
        }
    }

    return bestMatch ?? DEFAULT_RESULT;
}
