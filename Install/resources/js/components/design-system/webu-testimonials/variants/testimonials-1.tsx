import type { WebuTestimonialsProps } from '../types';

/** Slider – data from CMS */
export function Testimonials1({ title, items }: WebuTestimonialsProps) {
  return (
    <section className="webu-testimonials webu-testimonials--testimonials-1">
      <div className="webu-testimonials__inner">
        {title && <h2 className="webu-testimonials__title" data-webu-field="title">{title}</h2>}
        <div className="webu-testimonials__slider" data-webu-field="items">
          {items.map((item, i) => (
            <blockquote key={i} className="webu-testimonials__item" data-webu-field-scope={`items.${i}`}>
              <p className="webu-testimonials__text" data-webu-field="text">{item.text}</p>
              <footer>
                {item.avatar && <img src={item.avatar} alt="" className="webu-testimonials__avatar" data-webu-field="avatar" />}
                <cite className="webu-testimonials__author" data-webu-field="user_name">{item.user_name}</cite>
                {item.rating != null && <span className="webu-testimonials__rating" aria-label={`Rating ${item.rating}`} data-webu-field="rating">{item.rating}</span>}
              </footer>
            </blockquote>
          ))}
        </div>
      </div>
    </section>
  );
}
