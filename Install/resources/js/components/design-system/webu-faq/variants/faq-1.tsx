import type { WebuFaqProps } from '../types';

/** Accordion – data from CMS */
export function Faq1({ title, items }: WebuFaqProps) {
  return (
    <section className="webu-faq webu-faq--faq-1">
      <div className="webu-faq__inner">
        {title && <h2 className="webu-faq__title" data-webu-field="title">{title}</h2>}
        <div className="webu-faq__list" data-webu-field="items">
          {items.map((item, i) => (
            <details key={i} className="webu-faq__item" data-webu-field-scope={`items.${i}`}>
              <summary className="webu-faq__question" data-webu-field="question">{item.question}</summary>
              <p className="webu-faq__answer" data-webu-field="answer">{item.answer}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  );
}
