import type { WebuProductFiltersProps } from '../types';

/** Sidebar – data from CMS */
export function Filters1({ filters }: WebuProductFiltersProps) {
  return (
    <aside className="webu-product-filters webu-product-filters--filters-1">
      <div className="webu-product-filters__inner">
        {filters.map((group) => (
          <div key={group.key} className="webu-product-filters__group">
            <h3 className="webu-product-filters__label">{group.label}</h3>
            <ul className="webu-product-filters__list">
              {group.options.map((opt) => (
                <li key={opt.value}>
                  <label className="webu-product-filters__option">
                    <input type="checkbox" name={group.key} value={opt.value} />
                    <span>{opt.label}</span>
                    {opt.count != null && <span className="webu-product-filters__count">({opt.count})</span>}
                  </label>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>
    </aside>
  );
}
