import type { WebuStatsProps } from '../types';

/** Numbers – data from CMS */
export function Stats1({ title, items }: WebuStatsProps) {
  return (
    <section className="webu-stats webu-stats--stats-1">
      <div className="webu-stats__inner">
        {title && <h2 className="webu-stats__title">{title}</h2>}
        <div className="webu-stats__grid">
          {items.map((item, i) => (
            <div key={i} className="webu-stats__item">
              <span className="webu-stats__value">{item.value}</span>
              <span className="webu-stats__label">{item.label}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
