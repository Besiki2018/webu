import type { WebuTeamProps } from '../types';

/** Cards – data from CMS */
export function Team1({ title, members }: WebuTeamProps) {
  return (
    <section className="webu-team webu-team--team-1">
      <div className="webu-team__inner">
        {title && <h2 className="webu-team__title">{title}</h2>}
        <div className="webu-team__grid">
          {members.map((m, i) => (
            <div key={i} className="webu-team__card">
              {m.avatar && <img src={m.avatar} alt={m.name} className="webu-team__avatar" />}
              <h3 className="webu-team__name">{m.name}</h3>
              {m.role && <p className="webu-team__role">{m.role}</p>}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
