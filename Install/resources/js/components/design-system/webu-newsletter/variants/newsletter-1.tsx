import type { WebuNewsletterProps } from '../types';

export function Newsletter1({
  title = 'Stay updated',
  text = 'Subscribe for offers and news.',
  placeholder = 'Your email',
  buttonLabel = 'Subscribe',
}: WebuNewsletterProps) {
  return (
    <section className="webu-newsletter webu-newsletter--newsletter-1">
      <div className="webu-newsletter__inner">
        <h3 className="webu-newsletter__title">{title}</h3>
        <p className="webu-newsletter__text">{text}</p>
        <form className="webu-newsletter__form" onSubmit={(e) => e.preventDefault()}>
          <input type="email" className="webu-newsletter__input" placeholder={placeholder} aria-label={placeholder} />
          <button type="submit" className="webu-newsletter__button">
            {buttonLabel}
          </button>
        </form>
      </div>
    </section>
  );
}
