export type CategoryCardVariant = 'category-1' | 'category-2' | 'category-3' | 'category-4';

export interface WebuCategoryCardCategory {
  name: string;
  slug: string;
  image_url?: string | null;
  count?: number;
}

export interface WebuCategoryCardProps {
  variant?: CategoryCardVariant;
  category: WebuCategoryCardCategory;
  basePath?: string;
  className?: string;
}
