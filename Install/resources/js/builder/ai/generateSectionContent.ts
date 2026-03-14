import { setValueAtPath } from '../state/sectionProps'
import { getCatalogEntry, type AiComponentCatalogEntry } from './componentCatalog'
import type {
  BannerSectionContent,
  CollectionSectionContent,
  CtaSectionContent,
  FeaturesSectionContent,
  FooterSectionContent,
  FormSectionContent,
  GenerateSectionContentInput,
  GeneratedSectionContent,
  GeneratedSectionStructuredContent,
  GenericSectionContent,
  HeroSectionContent,
  NavigationSectionContent,
  PricingSectionContent,
  ProductGridSectionContent,
  SectionContentBrief,
  TestimonialSectionContent,
  FaqSectionContent,
} from './contentContracts'
import { hasDisallowedProductionCopy } from './contentContracts'

type IndustryProfileKey =
  | 'veterinary'
  | 'finance'
  | 'restaurant'
  | 'portfolio'
  | 'ecommerce'
  | 'saas'
  | 'business'

interface IndustryProfile {
  key: IndustryProfileKey
  label: string
  offerLabel: string
  outcomeLabel: string
  proofPoints: string[]
  primaryCtaLabel: string
  primaryCtaUrl: string
  secondaryCtaLabel?: string
  secondaryCtaUrl?: string
  featureItems: Array<{ title: string; description: string; icon?: string }>
  testimonialItems: Array<{ user_name: string; text: string; rating?: number }>
  faqItems: Array<{ question: string; answer: string }>
  contactAddress: string
  newsletterHeading: string
  newsletterCopy: string
  newsletterPlaceholder: string
  newsletterButtonLabel: string
  formNamePlaceholder: string
  formEmailPlaceholder: string
  formMessagePlaceholder: string
  formSubmitLabel: string
  productCollectionLabel: string
}

function normalizeText(value: string): string {
  return value.toLowerCase().trim().replace(/\s+/g, ' ')
}

function compactWhitespace(value: string): string {
  return value.trim().replace(/\s+/g, ' ')
}

function titleCase(value: string): string {
  return compactWhitespace(value)
    .split(/\s+/)
    .filter(Boolean)
    .map((token) => token.charAt(0).toUpperCase() + token.slice(1))
    .join(' ')
}

function hashSeed(value: string): number {
  let hash = 0

  for (let index = 0; index < value.length; index += 1) {
    hash = ((hash << 5) - hash + value.charCodeAt(index)) | 0
  }

  return Math.abs(hash)
}

function pickDeterministic<T>(seed: string, options: readonly T[]): T {
  return options[hashSeed(seed) % options.length]!
}

function sanitizeBusinessNoun(value: string): string {
  return compactWhitespace(
    normalizeText(value)
      .replace(/\b(website|site|landing page|landing|page|homepage)\b/g, ' ')
      .replace(/\b(store|shop)\b/g, ' ')
  )
}

function deriveBusinessFocus(input: GenerateSectionContentInput): string {
  const fromBlueprint = sanitizeBusinessNoun(input.blueprint.businessType)
  if (fromBlueprint !== '') {
    return fromBlueprint
  }

  return input.blueprint.projectType === 'saas'
    ? 'software'
    : input.blueprint.projectType === 'ecommerce'
      ? 'products'
      : 'services'
}

function resolveBrandLabel(input: GenerateSectionContentInput): string {
  if (typeof input.brandName === 'string' && input.brandName.trim() !== '') {
    return input.brandName.trim()
  }

  return titleCase(input.blueprint.businessType)
}

function detectIndustryProfile(input: GenerateSectionContentInput): IndustryProfileKey {
  const signals = [
    input.prompt,
    input.blueprint.businessType,
    input.blueprint.audience,
    input.blueprint.pageGoal,
    input.blueprint.tone,
    ...input.blueprint.styleKeywords,
  ].map(normalizeText).join(' ')

  if (/(vet|veterin|pet care|pet clinic|animal clinic|pet owners)/.test(signals)) {
    return 'veterinary'
  }

  if (/(finance|cfo|controller|accounting|close|budget|procurement|treasury)/.test(signals)) {
    return 'finance'
  }

  if (/(restaurant|cafe|dining|menu|guest|reservation|table)/.test(signals)) {
    return 'restaurant'
  }

  if (/(portfolio|studio|creative|photography|designer|brand designer|agency)/.test(signals) || input.blueprint.projectType === 'portfolio') {
    return 'portfolio'
  }

  if (input.blueprint.projectType === 'ecommerce') {
    return 'ecommerce'
  }

  if (input.blueprint.projectType === 'saas') {
    return 'saas'
  }

  return 'business'
}

function resolveIndustryProfile(input: GenerateSectionContentInput): IndustryProfile {
  const businessFocus = deriveBusinessFocus(input)

  switch (detectIndustryProfile(input)) {
    case 'veterinary':
      return {
        key: 'veterinary',
        label: 'Veterinary clinic',
        offerLabel: 'premium veterinary care',
        outcomeLabel: 'faster bookings, calmer visits, and better follow-through for every pet',
        proofPoints: [
          'preventive care plans',
          'urgent visit access',
          'clear treatment communication',
        ],
        primaryCtaLabel: 'Book a visit',
        primaryCtaUrl: '/book',
        secondaryCtaLabel: 'See care plans',
        secondaryCtaUrl: '/services',
        featureItems: [
          { title: 'Preventive care that stays on schedule', description: 'Explain wellness exams, vaccines, and long-term care plans in a way pet owners can trust.', icon: 'Heart' },
          { title: 'Urgent appointments without phone friction', description: 'Guide anxious owners toward urgent visit booking and make availability feel immediate.', icon: 'Clock3' },
          { title: 'Clinical guidance that feels reassuring', description: 'Show credentials, treatment clarity, and post-visit communication before a family ever arrives.', icon: 'ShieldCheck' },
        ],
        testimonialItems: [
          { user_name: 'Nino G.', text: 'We booked our dog’s wellness visit in under a minute and felt informed before we even walked in.', rating: 5 },
          { user_name: 'Luka K.', text: 'The clinic’s care plans and treatment explanations made it easy to trust the next step for our cat.', rating: 5 },
        ],
        faqItems: [
          { question: 'Can owners request routine and urgent visits online?', answer: 'Yes. The page can route visitors to routine care, urgent appointments, and follow-up questions without a phone bottleneck.' },
          { question: 'How should the site build trust before a first visit?', answer: 'Lead with clinician expertise, preventive services, and what owners can expect from intake through follow-up.' },
        ],
        contactAddress: 'Open weekdays for wellness care and same-week urgent pet visits.',
        newsletterHeading: 'Send seasonal pet health reminders',
        newsletterCopy: 'Collect emails for vaccine windows, parasite prevention tips, and clinic updates that owners actually want.',
        newsletterPlaceholder: 'Email for pet care updates',
        newsletterButtonLabel: 'Send reminders',
        formNamePlaceholder: "Pet owner's name",
        formEmailPlaceholder: 'Email for visit updates',
        formMessagePlaceholder: "Tell us your pet's needs or preferred appointment window",
        formSubmitLabel: 'Request an appointment',
        productCollectionLabel: 'care essentials',
      }
    case 'finance':
      return {
        key: 'finance',
        label: 'Finance platform',
        offerLabel: input.blueprint.projectType === 'saas' ? 'finance operations software' : 'finance advisory services',
        outcomeLabel: 'faster closes, cleaner approvals, and reporting leaders can trust',
        proofPoints: [
          'approval workflows',
          'live reporting',
          'close management',
        ],
        primaryCtaLabel: input.blueprint.projectType === 'saas' ? 'Book demo' : 'Book consultation',
        primaryCtaUrl: input.blueprint.projectType === 'saas' ? '/demo' : '/consultation',
        secondaryCtaLabel: input.blueprint.projectType === 'saas' ? 'See workflows' : 'See services',
        secondaryCtaUrl: input.blueprint.projectType === 'saas' ? '/platform' : '/services',
        featureItems: [
          { title: 'Approval flows that stay audit-ready', description: 'Show how requests, approvals, and controls stay visible for finance teams under pressure.', icon: 'ShieldCheck' },
          { title: 'Reporting that answers questions immediately', description: 'Turn board prep, spend visibility, and monthly close work into one clear operating view.', icon: 'BarChart3' },
          { title: 'Operations built for controllers and CFOs', description: 'Position the product or service around fewer manual handoffs and more confident forecasting.', icon: 'BriefcaseBusiness' },
        ],
        testimonialItems: [
          { user_name: 'Ana M.', text: 'Our finance team shortened close week because approvals and reporting finally lived in the same workflow.', rating: 5 },
          { user_name: 'David T.', text: 'The page speaks directly to controllers and CFOs instead of forcing us through generic SaaS language.', rating: 5 },
        ],
        faqItems: [
          { question: 'How quickly can finance teams understand the value?', answer: 'Lead with time-to-close, approval clarity, and the exact reporting workflow the team will improve first.' },
          { question: 'Should the site speak to controllers, CFOs, or operators?', answer: 'Yes, but prioritize the buying team first and support them with proof for operations and executive stakeholders.' },
        ],
        contactAddress: 'Built for finance leaders who need control, speed, and reporting confidence.',
        newsletterHeading: 'Share finance workflow updates',
        newsletterCopy: 'Capture work emails for launch notes, reporting templates, and operating guidance for finance teams.',
        newsletterPlaceholder: 'Work email for finance updates',
        newsletterButtonLabel: 'Send updates',
        formNamePlaceholder: 'Finance lead name',
        formEmailPlaceholder: 'Work email',
        formMessagePlaceholder: 'Which close, reporting, or approval workflow needs attention?',
        formSubmitLabel: input.blueprint.projectType === 'saas' ? 'Request a demo' : 'Start the conversation',
        productCollectionLabel: businessFocus || 'finance workflows',
      }
    case 'restaurant':
      return {
        key: 'restaurant',
        label: 'Restaurant',
        offerLabel: 'signature dining experiences',
        outcomeLabel: 'more reservations, stronger repeat visits, and a menu guests remember',
        proofPoints: [
          'signature dishes',
          'easy reservations',
          'atmosphere and hospitality',
        ],
        primaryCtaLabel: 'Reserve a table',
        primaryCtaUrl: '/reserve',
        secondaryCtaLabel: 'See the menu',
        secondaryCtaUrl: '/menu',
        featureItems: [
          { title: 'Signature dishes deserve a stronger spotlight', description: 'Lead with the menu moments guests already tell their friends about and make them easy to browse.', icon: 'Utensils' },
          { title: 'Reservations should take one clear step', description: 'Reduce booking friction with a simple path to reserve, confirm, and plan a visit.', icon: 'CalendarClock' },
          { title: 'Atmosphere sells before the first bite', description: 'Use copy that frames the room, service style, and why this place is worth coming back to.', icon: 'Sparkles' },
        ],
        testimonialItems: [
          { user_name: 'Mari P.', text: 'The site makes the menu feel irresistible and booking a table takes less time than choosing dessert.', rating: 5 },
          { user_name: 'Giorgi L.', text: 'We understood the atmosphere and signature dishes immediately, then reserved for the same evening.', rating: 5 },
        ],
        faqItems: [
          { question: 'What should the site emphasize first?', answer: 'Show signature dishes, reservation clarity, and the dining atmosphere before secondary details.' },
          { question: 'How do we support group bookings or special requests?', answer: 'Use the final CTA or contact section to invite guests to share group size, date, and dietary notes.' },
        ],
        contactAddress: 'Reservations open daily for dinner service, celebrations, and special requests.',
        newsletterHeading: 'Share menu drops and reservation openings',
        newsletterCopy: 'Collect guest emails for tasting menus, seasonal launches, and event nights worth planning around.',
        newsletterPlaceholder: 'Email for menu updates',
        newsletterButtonLabel: 'Send invitations',
        formNamePlaceholder: 'Guest name',
        formEmailPlaceholder: 'Email for reservation details',
        formMessagePlaceholder: 'Date, party size, and any dietary requests',
        formSubmitLabel: 'Request a table',
        productCollectionLabel: 'signature dishes',
      }
    case 'portfolio':
      return {
        key: 'portfolio',
        label: 'Portfolio',
        offerLabel: 'creative work with a distinct point of view',
        outcomeLabel: 'clearer positioning, better-fit inquiries, and stronger project conversations',
        proofPoints: [
          'selected projects',
          'process clarity',
          'client-fit positioning',
        ],
        primaryCtaLabel: 'View selected work',
        primaryCtaUrl: '/work',
        secondaryCtaLabel: 'Start a project',
        secondaryCtaUrl: '/contact',
        featureItems: [
          { title: 'Lead with the work that sets the tone', description: 'Show the strongest projects first so the right clients understand the craft and range immediately.', icon: 'GalleryVerticalEnd' },
          { title: 'Explain process without slowing the scroll', description: 'Turn discovery, concepting, and delivery into clear steps that make collaboration feel organized.', icon: 'Workflow' },
          { title: 'Make the inquiry feel like the next natural move', description: 'The copy should filter for aligned clients while still sounding approachable and precise.', icon: 'MessageSquareMore' },
        ],
        testimonialItems: [
          { user_name: 'Tamar R.', text: 'The portfolio now reads like a point of view, not a gallery dump, and clients know why they should reach out.', rating: 5 },
          { user_name: 'Nika C.', text: 'Project stories feel intentional and the inquiry path finally matches the quality of the work.', rating: 5 },
        ],
        faqItems: [
          { question: 'How much work should the homepage show?', answer: 'Only enough to establish range, taste, and trust. Let the strongest case studies carry the first impression.' },
          { question: 'What should the inquiry copy accomplish?', answer: 'It should attract aligned projects, set expectations, and make it easy to share scope and timing.' },
        ],
        contactAddress: 'Available for brand, digital, and campaign projects with thoughtful timelines.',
        newsletterHeading: 'Share new case studies and releases',
        newsletterCopy: 'Keep past clients and collaborators close with thoughtful updates on launches, work, and availability.',
        newsletterPlaceholder: 'Email for case study releases',
        newsletterButtonLabel: 'Send updates',
        formNamePlaceholder: 'Project lead name',
        formEmailPlaceholder: 'Studio or work email',
        formMessagePlaceholder: 'Briefly outline the project, timeline, and what you want the work to do',
        formSubmitLabel: 'Start the project',
        productCollectionLabel: 'selected projects',
      }
    case 'ecommerce':
      return {
        key: 'ecommerce',
        label: 'Storefront',
        offerLabel: `${businessFocus || 'product'} collections built to convert`,
        outcomeLabel: 'clearer merchandising, stronger trust, and faster product discovery',
        proofPoints: [
          'curated collections',
          'fast checkout',
          'high-intent product discovery',
        ],
        primaryCtaLabel: 'Shop the collection',
        primaryCtaUrl: '/shop',
        secondaryCtaLabel: 'Browse bestsellers',
        secondaryCtaUrl: '/collections/bestsellers',
        featureItems: [
          { title: 'Collections that feel curated, not crowded', description: 'Frame the catalog around the products shoppers are most likely to compare and buy first.', icon: 'Package2' },
          { title: 'Trust signals where hesitation usually happens', description: 'Use shipping clarity, quality cues, and social proof before visitors reach the cart.', icon: 'ShieldCheck' },
          { title: 'Copy that supports conversion, not decoration', description: 'Describe the product experience in a way that helps shoppers make a decision quickly.', icon: 'ShoppingBag' },
        ],
        testimonialItems: [
          { user_name: 'Salome D.', text: 'The storefront feels intentional and makes it obvious which collection to shop first.', rating: 5 },
          { user_name: 'Levan A.', text: 'We went from generic product copy to a store that actually sounds like the category it sells in.', rating: 5 },
        ],
        faqItems: [
          { question: 'What should the homepage merchandise first?', answer: 'Feature the most distinctive or highest-intent collection before supporting categories and proof.' },
          { question: 'How can the page reduce drop-off before checkout?', answer: 'Surface shipping confidence, product quality cues, and obvious next actions inside the buying flow.' },
        ],
        contactAddress: 'Shop online with clear delivery expectations and curated product support.',
        newsletterHeading: 'Share drops and restocks that matter',
        newsletterCopy: 'Capture subscriber intent for launches, bestsellers, and back-in-stock products without sounding generic.',
        newsletterPlaceholder: 'Email for product drops',
        newsletterButtonLabel: 'Send product news',
        formNamePlaceholder: 'Customer name',
        formEmailPlaceholder: 'Email for order support',
        formMessagePlaceholder: 'Question about products, sizing, or delivery',
        formSubmitLabel: 'Send the request',
        productCollectionLabel: businessFocus || 'featured products',
      }
    case 'saas':
      return {
        key: 'saas',
        label: 'SaaS product',
        offerLabel: 'software that turns evaluation into confidence',
        outcomeLabel: 'clearer product value, stronger proof, and more qualified demos',
        proofPoints: [
          'product workflow clarity',
          'trust through outcomes',
          'qualified demo intent',
        ],
        primaryCtaLabel: 'Book demo',
        primaryCtaUrl: '/demo',
        secondaryCtaLabel: 'See the product',
        secondaryCtaUrl: '/product',
        featureItems: [
          { title: 'Lead with the workflow that changes first', description: 'Show the highest-value product motion immediately so evaluators know what the software replaces.', icon: 'Workflow' },
          { title: 'Translate product depth into business outcomes', description: 'Tie automation, reporting, or collaboration features to the result teams care about most.', icon: 'Sparkles' },
          { title: 'Proof should feel native to the buying journey', description: 'Move from product clarity to trust signals without falling back on generic SaaS claims.', icon: 'BadgeCheck' },
        ],
        testimonialItems: [
          { user_name: 'Eka B.', text: 'The landing page finally explains the workflow our team buys for instead of repeating generic product slogans.', rating: 5 },
          { user_name: 'James K.', text: 'Prospects reach the demo form already understanding the problem we solve and why it matters.', rating: 5 },
        ],
        faqItems: [
          { question: 'What should the hero communicate first?', answer: 'Name the team, the workflow, and the specific improvement the product creates before any secondary proof.' },
          { question: 'How do we keep the copy from sounding like every other SaaS page?', answer: 'Use audience-specific workflow language, concrete outcomes, and proof that matches the buying motion.' },
        ],
        contactAddress: 'Built for teams evaluating software with short attention and high standards.',
        newsletterHeading: 'Share product launches that matter',
        newsletterCopy: 'Keep evaluation-stage teams engaged with release notes, workflow guides, and practical rollout stories.',
        newsletterPlaceholder: 'Work email for product updates',
        newsletterButtonLabel: 'Send release notes',
        formNamePlaceholder: 'Team lead name',
        formEmailPlaceholder: 'Work email',
        formMessagePlaceholder: 'What workflow are you replacing or improving?',
        formSubmitLabel: 'Request the demo',
        productCollectionLabel: 'core workflows',
      }
    case 'business':
    default:
      return {
        key: 'business',
        label: titleCase(input.blueprint.businessType),
        offerLabel: input.blueprint.businessType.toLowerCase(),
        outcomeLabel: 'clearer positioning, faster trust, and a stronger next step for prospects',
        proofPoints: [
          'clear offer positioning',
          'trust-building proof',
          'simple next-step conversion',
        ],
        primaryCtaLabel: 'Schedule a consultation',
        primaryCtaUrl: '/consultation',
        secondaryCtaLabel: 'See services',
        secondaryCtaUrl: '/services',
        featureItems: [
          { title: 'State the offer without making visitors decode it', description: `Explain what makes ${input.blueprint.businessType.toLowerCase()} credible and worth contacting on the first visit.`, icon: 'BriefcaseBusiness' },
          { title: 'Place trust where decisions are made', description: 'Use proof, process, and expertise to remove hesitation before the visitor reaches the final CTA.', icon: 'ShieldCheck' },
          { title: 'Turn interest into a qualified next step', description: 'Give prospects a single clear action that matches the service being sold.', icon: 'ArrowRightCircle' },
        ],
        testimonialItems: [
          { user_name: 'Elene J.', text: 'The site now sounds like the category we are actually in instead of a generic brochure template.', rating: 5 },
          { user_name: 'Mate H.', text: 'Visitors understand the offer faster and reach out with better context for the work.', rating: 5 },
        ],
        faqItems: [
          { question: 'What should the homepage prove first?', answer: 'Clarify the offer, who it is for, and why this team is credible before expanding into supporting detail.' },
          { question: 'How do we keep the page conversion-focused?', answer: 'Limit competing calls to action and make the main inquiry step match the service on offer.' },
        ],
        contactAddress: 'Built to help qualified prospects understand the offer and reach out with context.',
        newsletterHeading: 'Share practical updates with prospects',
        newsletterCopy: 'Use follow-up email for insights, launches, and proof that reinforces why the business is worth contacting.',
        newsletterPlaceholder: 'Email for updates and insights',
        newsletterButtonLabel: 'Send updates',
        formNamePlaceholder: 'Decision-maker name',
        formEmailPlaceholder: 'Best work email',
        formMessagePlaceholder: 'What are you trying to solve and when do you need it live?',
        formSubmitLabel: 'Request the consultation',
        productCollectionLabel: businessFocus || 'featured work',
      }
  }
}

function resolveProjectTypeLabel(projectType: GenerateSectionContentInput['blueprint']['projectType']): string {
  switch (projectType) {
    case 'saas':
      return 'platform'
    case 'ecommerce':
      return 'store'
    case 'portfolio':
      return 'portfolio'
    case 'restaurant':
      return 'restaurant'
    case 'business':
      return 'business'
    case 'landing':
    default:
      return 'site'
  }
}

function buildSectionContentBrief(input: GenerateSectionContentInput, profile: IndustryProfile): SectionContentBrief {
  const sectionType = input.section.sectionType
  let sectionLabel = `Support ${input.blueprint.pageGoal.toLowerCase()}`

  if (sectionType === 'hero') {
    sectionLabel = `Introduce ${profile.offerLabel}`
  } else if (['problem', 'solution'].includes(sectionType)) {
    sectionLabel = `Clarify the core value of ${profile.offerLabel}`
  } else if (['services', 'features', 'process', 'skills'].includes(sectionType)) {
    sectionLabel = `Prove ${profile.offerLabel}`
  } else if (['doctors', 'chef', 'case_studies', 'portfolio_gallery', 'gallery', 'product_demo'].includes(sectionType)) {
    sectionLabel = `Build trust around ${profile.offerLabel}`
  } else if (['appointment_booking', 'reservation', 'booking', 'contact', 'location'].includes(sectionType)) {
    sectionLabel = `Make the next step easy for ${input.blueprint.audience}`
  } else if (sectionType === 'testimonials') {
    sectionLabel = `Use client proof to support ${profile.offerLabel}`
  } else if (sectionType === 'faq') {
    sectionLabel = `Resolve objections for ${input.blueprint.audience}`
  } else if (sectionType === 'cta' || input.section.layoutType === 'form') {
    sectionLabel = `Convert ${input.blueprint.audience}`
  }

  return {
    sectionType: input.section.sectionType,
    layoutType: input.section.layoutType,
    businessType: input.blueprint.businessType,
    audience: input.blueprint.audience,
    tone: input.blueprint.tone,
    styleKeywords: [...input.blueprint.styleKeywords],
    pageGoal: input.blueprint.pageGoal,
    messagingAngle: `${sectionLabel} with a ${input.blueprint.tone} tone for ${input.blueprint.audience}`,
    proofPoints: [...profile.proofPoints],
    ctaIntent: profile.primaryCtaLabel,
  }
}

function resolveFieldPath(entry: AiComponentCatalogEntry, aliases: string[]): string | null {
  for (const alias of aliases) {
    const exact = entry.propsSchema.find((field) => field.path === alias)
    if (exact) {
      return exact.path
    }
  }

  for (const alias of aliases) {
    const partial = entry.propsSchema.find((field) => field.path.split('.').pop() === alias)
    if (partial) {
      return partial.path
    }
  }

  return null
}

function isCompatibleValue(fieldType: string, value: unknown): boolean {
  switch (fieldType) {
    case 'text':
    case 'richtext':
    case 'image':
    case 'video':
    case 'icon':
    case 'link':
    case 'menu':
    case 'button-group':
    case 'color':
    case 'alignment':
    case 'radius':
    case 'shadow':
    case 'overlay':
    case 'visibility':
    case 'select':
    case 'layout-variant':
    case 'style-variant':
      return typeof value === 'string' || value === null || Array.isArray(value) || typeof value === 'object'
    case 'number':
      return typeof value === 'number'
    case 'boolean':
      return typeof value === 'boolean'
    case 'spacing':
    case 'width':
    case 'height':
    case 'typography':
    case 'repeater':
      return typeof value === 'string' || typeof value === 'number' || value === null || Array.isArray(value) || typeof value === 'object'
    default:
      return true
  }
}

function applyCandidatePatch(
  entry: AiComponentCatalogEntry,
  patch: Record<string, unknown>,
  aliases: string[],
  value: unknown,
): Record<string, unknown> {
  const fieldPath = resolveFieldPath(entry, aliases)
  if (!fieldPath) {
    return patch
  }

  const field = entry.propsSchema.find((candidate) => candidate.path === fieldPath)
  if (!field || !isCompatibleValue(field.type, value)) {
    return patch
  }

  return setValueAtPath(patch, fieldPath, value)
}

function buildMenuLinks(input: GenerateSectionContentInput, profile: IndustryProfile): Array<{ label: string; url: string }> {
  const sectionLinks = input.blueprint.sections
    .map((section) => {
      switch (section.sectionType) {
        case 'hero':
          return { label: 'Overview', url: '#overview' }
        case 'problem':
          return { label: 'Problem', url: '#problem' }
        case 'solution':
          return { label: 'Solution', url: '#solution' }
        case 'services':
          return { label: 'Services', url: '#services' }
        case 'doctors':
          return { label: 'Team', url: '#team' }
        case 'features':
          return { label: profile.key === 'restaurant' ? 'Highlights' : profile.key === 'ecommerce' ? 'Why shop here' : 'Why it works', url: '#features' }
        case 'pricing':
          return { label: 'Pricing', url: '#pricing' }
        case 'productGrid':
          return { label: 'Shop', url: '#products' }
        case 'featured_products':
          return { label: 'Featured', url: '#featured-products' }
        case 'categories':
          return { label: 'Categories', url: '#categories' }
        case 'grid':
          return { label: 'Work', url: '#work' }
        case 'portfolio_gallery':
          return { label: 'Portfolio', url: '#portfolio' }
        case 'case_studies':
          return { label: 'Case Studies', url: '#case-studies' }
        case 'process':
          return { label: 'Process', url: '#process' }
        case 'gallery':
          return { label: 'Gallery', url: '#gallery' }
        case 'testimonials':
          return { label: profile.key === 'restaurant' ? 'Reviews' : 'Proof', url: '#testimonials' }
        case 'reviews':
          return { label: 'Reviews', url: '#reviews' }
        case 'faq':
          return { label: 'FAQ', url: '#faq' }
        case 'menu':
          return { label: 'Menu', url: '#menu' }
        case 'chef':
          return { label: 'Chef', url: '#chef' }
        case 'product_demo':
          return { label: 'Demo', url: '#demo' }
        case 'about':
          return { label: 'About', url: '#about' }
        case 'skills':
          return { label: 'Skills', url: '#skills' }
        case 'location':
          return { label: 'Location', url: '#location' }
        case 'cta':
        case 'contact':
        case 'booking':
        case 'appointment_booking':
        case 'reservation':
          return { label: profile.primaryCtaLabel, url: profile.primaryCtaUrl }
        default:
          return null
      }
    })
    .filter((item): item is { label: string; url: string } => item !== null)

  const deduped = sectionLinks.filter((item, index, array) => array.findIndex((candidate) => candidate.label === item.label) === index)
  return deduped.slice(0, 5)
}

function resolveHeroContent(input: GenerateSectionContentInput, profile: IndustryProfile, brief: SectionContentBrief): HeroSectionContent {
  const businessLabel = input.blueprint.businessType.toLowerCase()
  const toneWord = pickDeterministic(`${input.prompt}:tone`, [
    input.blueprint.tone,
    ...input.blueprint.styleKeywords,
  ].filter(Boolean))
  const title = pickDeterministic(`${input.prompt}:${profile.key}:hero-title`, [
    `Make ${profile.offerLabel} feel clear from the first scroll`,
    `${titleCase(input.blueprint.businessType)} for ${input.blueprint.audience} who expect more than a template`,
    `${titleCase(profile.offerLabel)} for ${input.blueprint.audience}`,
  ])
  const subtitle = pickDeterministic(`${input.prompt}:${profile.key}:hero-subtitle`, [
    `Show ${input.blueprint.audience} exactly why ${businessLabel} leads to ${profile.outcomeLabel}.`,
    `Use ${toneWord || 'clear'} positioning, proof, and one decisive CTA to move visitors toward ${profile.primaryCtaLabel.toLowerCase()}.`,
    `Frame the offer around ${brief.proofPoints.join(', ')} so the next step feels obvious.`,
  ])

  return {
    kind: 'hero',
    eyebrow: titleCase(profile.label),
    badgeText: titleCase(resolveProjectTypeLabel(input.blueprint.projectType)),
    title,
    subtitle,
    description: `${titleCase(profile.offerLabel)} built around ${profile.outcomeLabel}.`,
    primaryCtaLabel: profile.primaryCtaLabel,
    primaryCtaUrl: profile.primaryCtaUrl,
    secondaryCtaLabel: profile.secondaryCtaLabel,
    secondaryCtaUrl: profile.secondaryCtaUrl,
    imageAlt: `${resolveBrandLabel(input)} ${profile.offerLabel}`,
    statValue: String(pickDeterministic(`${input.prompt}:${profile.key}:stat`, [24, 48, 72])),
    statUnit: profile.key === 'finance' ? 'hrs' : profile.key === 'restaurant' ? 'tables' : profile.key === 'ecommerce' ? '%' : '+',
    statLabel: pickDeterministic(`${input.prompt}:${profile.key}:stat-label`, [
      `focused on ${profile.proofPoints[0]}`,
      `built around ${profile.proofPoints[1]}`,
      `${profile.proofPoints[2]} first`,
    ]),
  }
}

function resolveFeaturesContent(input: GenerateSectionContentInput, profile: IndustryProfile): FeaturesSectionContent {
  return {
    kind: 'features',
    title: pickDeterministic(`${input.prompt}:${profile.key}:features-title`, [
      `Why ${input.blueprint.audience} choose this ${resolveProjectTypeLabel(input.blueprint.projectType)}`,
      `What makes ${profile.offerLabel} feel credible immediately`,
      `The sections that turn interest into ${profile.primaryCtaLabel.toLowerCase()}`,
    ]),
    items: profile.featureItems,
  }
}

function resolvePricingContent(input: GenerateSectionContentInput, profile: IndustryProfile): PricingSectionContent {
  const tierAudience = input.blueprint.audience.toLowerCase()
  return {
    kind: 'pricing',
    title: pickDeterministic(`${input.prompt}:${profile.key}:pricing-title`, [
      `Plans built for ${tierAudience}`,
      `Choose the pace that fits your ${resolveProjectTypeLabel(input.blueprint.projectType)}`,
      `Commercial options that scale with the team`,
    ]),
    subtitle: `Keep pricing clear enough for evaluation while reinforcing ${profile.proofPoints.join(', ')}.`,
    items: [
      { title: 'Starter', description: `A focused way to adopt ${profile.offerLabel} with one clear workflow and fast onboarding.` },
      { title: 'Growth', description: `Expand into deeper reporting, collaboration, and repeatable operations for growing teams.` },
      { title: 'Scale', description: `Add governance, custom support, and rollout depth for larger teams with more stakeholders.` },
    ],
  }
}

function resolveCollectionContent(input: GenerateSectionContentInput, profile: IndustryProfile): CollectionSectionContent {
  const titles = profile.proofPoints.map((point) => titleCase(point))
  const links = [
    profile.secondaryCtaUrl ?? profile.primaryCtaUrl,
    profile.primaryCtaUrl,
    '/contact',
  ]

  return {
    kind: 'collection',
    title: pickDeterministic(`${input.prompt}:${profile.key}:collection-title`, [
      `Explore ${profile.label.toLowerCase()} highlights`,
      `Sections that support ${input.blueprint.pageGoal.toLowerCase()}`,
      `A clearer look at ${profile.offerLabel}`,
    ]),
    subtitle: `Turn ${profile.proofPoints.join(', ')} into concrete reasons to keep scrolling.`,
    items: titles.map((title, index) => ({
      title,
      description: `Show how ${title.toLowerCase()} supports ${profile.outcomeLabel}.`,
      link: links[index] ?? profile.primaryCtaUrl,
    })),
  }
}

function resolveTestimonialsContent(input: GenerateSectionContentInput, profile: IndustryProfile): TestimonialSectionContent {
  return {
    kind: 'testimonials',
    title: pickDeterministic(`${input.prompt}:${profile.key}:testimonial-title`, [
      `Proof for ${input.blueprint.audience}`,
      `What clients notice first`,
      `Results that match the promise`,
    ]),
    items: profile.testimonialItems,
  }
}

function resolveFaqContent(input: GenerateSectionContentInput, profile: IndustryProfile): FaqSectionContent {
  return {
    kind: 'faq',
    title: pickDeterministic(`${input.prompt}:${profile.key}:faq-title`, [
      `Questions ${input.blueprint.audience} ask before ${profile.primaryCtaLabel.toLowerCase()}`,
      `Answers that remove hesitation`,
      `What people need to know first`,
    ]),
    items: profile.faqItems,
  }
}

function resolveCtaContent(input: GenerateSectionContentInput, profile: IndustryProfile): CtaSectionContent {
  return {
    kind: 'cta',
    title: pickDeterministic(`${input.prompt}:${profile.key}:cta-title`, [
      `Turn ${input.blueprint.audience} into the next conversation`,
      `Make the next step feel specific`,
      `End the page with a stronger final ask`,
    ]),
    subtitle: `Tie the final action to ${profile.proofPoints.join(', ')} so the page ends with momentum instead of drift.`,
    buttonLabel: profile.primaryCtaLabel,
    buttonUrl: profile.primaryCtaUrl,
  }
}

function resolveFormContent(input: GenerateSectionContentInput, profile: IndustryProfile): FormSectionContent {
  return {
    kind: 'form',
    title: pickDeterministic(`${input.prompt}:${profile.key}:form-title`, [
      `${profile.primaryCtaLabel} with the right context`,
      `Start the next conversation with better detail`,
      `Collect the details that make follow-up faster`,
    ]),
    subtitle: `Ask for only the information needed to move ${input.blueprint.audience} toward ${profile.primaryCtaLabel.toLowerCase()}.`,
    submitLabel: profile.formSubmitLabel,
    namePlaceholder: profile.formNamePlaceholder,
    emailPlaceholder: profile.formEmailPlaceholder,
    messagePlaceholder: profile.formMessagePlaceholder,
  }
}

function resolveNavigationContent(input: GenerateSectionContentInput, profile: IndustryProfile): NavigationSectionContent {
  const brandLabel = resolveBrandLabel(input)

  return {
    kind: 'navigation',
    logoText: brandLabel,
    logoFallback: titleCase(profile.label),
    menuItems: buildMenuLinks(input, profile),
    ctaLabel: profile.primaryCtaLabel,
    ctaUrl: profile.primaryCtaUrl,
    menuDrawerFooterLabel: profile.secondaryCtaLabel,
    menuDrawerFooterUrl: profile.secondaryCtaUrl,
  }
}

function resolveFooterContent(input: GenerateSectionContentInput, profile: IndustryProfile): FooterSectionContent {
  const brandLabel = resolveBrandLabel(input)

  return {
    kind: 'footer',
    logoText: brandLabel,
    logoFallback: titleCase(profile.label),
    description: `${titleCase(profile.offerLabel)} for ${input.blueprint.audience}.`,
    subtitle: input.blueprint.pageGoal,
    links: buildMenuLinks(input, profile),
    socialLinks: [],
    copyright: `© ${new Date().getFullYear()} ${brandLabel}`,
    contactAddress: profile.contactAddress,
    newsletterHeading: profile.newsletterHeading,
    newsletterCopy: profile.newsletterCopy,
    newsletterPlaceholder: profile.newsletterPlaceholder,
    newsletterButtonLabel: profile.newsletterButtonLabel,
  }
}

function resolveProductGridContent(input: GenerateSectionContentInput, profile: IndustryProfile): ProductGridSectionContent {
  return {
    kind: 'product-grid',
    title: pickDeterministic(`${input.prompt}:${profile.key}:product-grid-title`, [
      `Shop ${profile.productCollectionLabel}`,
      `Browse the ${profile.productCollectionLabel} collection`,
      `Start with the products visitors compare first`,
    ]),
    subtitle: `Lead shoppers into ${profile.proofPoints.join(', ')} without forcing them through a generic catalog intro.`,
    addToCartLabel: pickDeterministic(`${input.prompt}:${profile.key}:add-to-cart`, [
      'Add to cart',
      'Add to bag',
      'Add to order',
    ]),
    ctaLabel: pickDeterministic(`${input.prompt}:${profile.key}:product-cta`, [
      'View details',
      'See product',
      'Compare options',
    ]),
    productCount: 8,
    productsPerPage: 12,
  }
}

function resolveBannerContent(input: GenerateSectionContentInput, profile: IndustryProfile): BannerSectionContent {
  return {
    kind: 'banner',
    title: pickDeterministic(`${input.prompt}:${profile.key}:banner-title`, [
      `Built for ${input.blueprint.audience}`,
      `A clearer path to ${profile.primaryCtaLabel.toLowerCase()}`,
      `Position ${profile.offerLabel} with more precision`,
    ]),
    subtitle: `${profile.outcomeLabel.charAt(0).toUpperCase()}${profile.outcomeLabel.slice(1)}.`,
    ctaLabel: profile.primaryCtaLabel,
    ctaUrl: profile.primaryCtaUrl,
  }
}

function resolveGenericContent(input: GenerateSectionContentInput, profile: IndustryProfile): GenericSectionContent {
  return {
    kind: 'generic',
    title: `${titleCase(profile.offerLabel)} built for ${input.blueprint.audience}`,
    subtitle: `Keep the page focused on ${profile.outcomeLabel}.`,
    body: input.blueprint.pageGoal,
  }
}

function mapStructuredContentToProps(
  entry: AiComponentCatalogEntry,
  structuredContent: GeneratedSectionStructuredContent,
): Record<string, unknown> {
  let patch: Record<string, unknown> = {}

  switch (structuredContent.kind) {
    case 'hero':
      patch = applyCandidatePatch(entry, patch, ['eyebrow'], structuredContent.eyebrow)
      patch = applyCandidatePatch(entry, patch, ['badgeText', 'badge_text'], structuredContent.badgeText ?? structuredContent.eyebrow)
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'subheading'], structuredContent.subtitle)
      patch = applyCandidatePatch(entry, patch, ['description', 'body'], structuredContent.description)
      patch = applyCandidatePatch(entry, patch, ['buttonText', 'buttonLabel', 'ctaText', 'cta_label'], structuredContent.primaryCtaLabel)
      patch = applyCandidatePatch(entry, patch, ['buttonLink', 'buttonUrl', 'ctaLink', 'cta_url'], structuredContent.primaryCtaUrl)
      patch = applyCandidatePatch(entry, patch, ['secondaryButtonText', 'ctaSecondaryLabel'], structuredContent.secondaryCtaLabel ?? '')
      patch = applyCandidatePatch(entry, patch, ['secondaryButtonLink', 'ctaSecondaryUrl'], structuredContent.secondaryCtaUrl ?? '')
      patch = applyCandidatePatch(entry, patch, ['imageAlt', 'imageAltFallback'], structuredContent.imageAlt)
      patch = applyCandidatePatch(entry, patch, ['statValue', 'stat_value'], structuredContent.statValue ?? '')
      patch = applyCandidatePatch(entry, patch, ['statUnit', 'stat_unit'], structuredContent.statUnit ?? '')
      patch = applyCandidatePatch(entry, patch, ['statLabel', 'stat_label'], structuredContent.statLabel ?? '')
      return patch
    case 'features':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['items'], structuredContent.items)
      return patch
    case 'pricing':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], structuredContent.subtitle ?? '')
      patch = applyCandidatePatch(entry, patch, ['items'], structuredContent.items)
      return patch
    case 'collection':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], structuredContent.subtitle ?? '')
      patch = applyCandidatePatch(entry, patch, ['items'], structuredContent.items)
      return patch
    case 'testimonials':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['items', 'testimonials'], structuredContent.items)
      return patch
    case 'faq':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['items'], structuredContent.items)
      return patch
    case 'cta':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], structuredContent.subtitle)
      patch = applyCandidatePatch(entry, patch, ['buttonLabel', 'buttonText', 'ctaText', 'cta_label'], structuredContent.buttonLabel)
      patch = applyCandidatePatch(entry, patch, ['buttonUrl', 'buttonLink', 'ctaLink', 'cta_url'], structuredContent.buttonUrl)
      return patch
    case 'form':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], structuredContent.subtitle)
      patch = applyCandidatePatch(entry, patch, ['submit_label', 'buttonLabel', 'buttonText'], structuredContent.submitLabel)
      patch = applyCandidatePatch(entry, patch, ['namePlaceholder'], structuredContent.namePlaceholder)
      patch = applyCandidatePatch(entry, patch, ['emailPlaceholder'], structuredContent.emailPlaceholder)
      patch = applyCandidatePatch(entry, patch, ['messagePlaceholder'], structuredContent.messagePlaceholder)
      return patch
    case 'navigation':
      patch = applyCandidatePatch(entry, patch, ['logoText'], structuredContent.logoText)
      patch = applyCandidatePatch(entry, patch, ['logoFallback'], structuredContent.logoFallback)
      patch = applyCandidatePatch(entry, patch, ['menu_items', 'links'], structuredContent.menuItems)
      patch = applyCandidatePatch(entry, patch, ['ctaText', 'buttonText', 'buttonLabel'], structuredContent.ctaLabel)
      patch = applyCandidatePatch(entry, patch, ['ctaLink', 'buttonLink', 'buttonUrl'], structuredContent.ctaUrl)
      patch = applyCandidatePatch(entry, patch, ['menuDrawerFooterLabel'], structuredContent.menuDrawerFooterLabel ?? '')
      patch = applyCandidatePatch(entry, patch, ['menuDrawerFooterUrl'], structuredContent.menuDrawerFooterUrl ?? '')
      return patch
    case 'footer':
      patch = applyCandidatePatch(entry, patch, ['logoText'], structuredContent.logoText)
      patch = applyCandidatePatch(entry, patch, ['logoFallback'], structuredContent.logoFallback)
      patch = applyCandidatePatch(entry, patch, ['description', 'subtitle'], structuredContent.description)
      patch = applyCandidatePatch(entry, patch, ['subtitle'], structuredContent.subtitle ?? '')
      patch = applyCandidatePatch(entry, patch, ['links'], structuredContent.links)
      patch = applyCandidatePatch(entry, patch, ['socialLinks'], structuredContent.socialLinks ?? [])
      patch = applyCandidatePatch(entry, patch, ['copyright'], structuredContent.copyright)
      patch = applyCandidatePatch(entry, patch, ['contactAddress'], structuredContent.contactAddress)
      patch = applyCandidatePatch(entry, patch, ['newsletterHeading'], structuredContent.newsletterHeading)
      patch = applyCandidatePatch(entry, patch, ['newsletterCopy'], structuredContent.newsletterCopy)
      patch = applyCandidatePatch(entry, patch, ['newsletterPlaceholder'], structuredContent.newsletterPlaceholder)
      patch = applyCandidatePatch(entry, patch, ['newsletterButtonLabel'], structuredContent.newsletterButtonLabel)
      return patch
    case 'product-grid':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], structuredContent.subtitle)
      patch = applyCandidatePatch(entry, patch, ['add_to_cart_label', 'buttonText'], structuredContent.addToCartLabel)
      patch = applyCandidatePatch(entry, patch, ['cta_label', 'buttonLabel'], structuredContent.ctaLabel)
      patch = applyCandidatePatch(entry, patch, ['productCount'], structuredContent.productCount)
      patch = applyCandidatePatch(entry, patch, ['products_per_page'], structuredContent.productsPerPage)
      return patch
    case 'banner':
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description'], structuredContent.subtitle)
      patch = applyCandidatePatch(entry, patch, ['cta_label', 'buttonText', 'buttonLabel'], structuredContent.ctaLabel)
      patch = applyCandidatePatch(entry, patch, ['cta_url', 'buttonLink', 'buttonUrl'], structuredContent.ctaUrl)
      return patch
    case 'generic':
    default:
      patch = applyCandidatePatch(entry, patch, ['title', 'headline'], structuredContent.title)
      patch = applyCandidatePatch(entry, patch, ['subtitle', 'description', 'body'], structuredContent.subtitle)
      patch = applyCandidatePatch(entry, patch, ['body'], structuredContent.body ?? '')
      return patch
  }
}

function resolveStructuredContent(
  input: GenerateSectionContentInput,
  profile: IndustryProfile,
  brief: SectionContentBrief,
): GeneratedSectionStructuredContent {
  if (input.section.sectionType === 'pricing') {
    return resolvePricingContent(input, profile)
  }

  switch (input.section.layoutType) {
    case 'hero':
      return resolveHeroContent(input, profile, brief)
    case 'features':
      return resolveFeaturesContent(input, profile)
    case 'product-grid':
      return resolveProductGridContent(input, profile)
    case 'testimonials':
      return resolveTestimonialsContent(input, profile)
    case 'faq':
      return resolveFaqContent(input, profile)
    case 'cta':
      return resolveCtaContent(input, profile)
    case 'form':
      return resolveFormContent(input, profile)
    case 'header':
    case 'navigation':
      return resolveNavigationContent(input, profile)
    case 'footer':
      return resolveFooterContent(input, profile)
    case 'banner':
      return resolveBannerContent(input, profile)
    case 'cards':
    case 'grid':
      return resolveCollectionContent(input, profile)
    case 'content':
    case 'media':
    case 'section':
    default:
      return resolveGenericContent(input, profile)
  }
}

export function generateSectionContent(input: GenerateSectionContentInput): GeneratedSectionContent {
  const entry = input.catalogEntry ?? getCatalogEntry(input.section.componentKey)
  if (!entry) {
    throw new Error(`component_catalog_entry_missing:${input.section.componentKey}`)
  }

  const profile = resolveIndustryProfile(input)
  const brief = buildSectionContentBrief(input, profile)
  const content = resolveStructuredContent(input, profile, brief)
  const props = mapStructuredContentToProps(entry, content)

  if (hasDisallowedProductionCopy(props)) {
    throw new Error(`section_content_contains_generic_filler:${input.section.componentKey}`)
  }

  return {
    brief,
    content,
    props,
  }
}
