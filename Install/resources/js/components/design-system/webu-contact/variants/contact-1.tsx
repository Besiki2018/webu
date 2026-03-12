import type { WebuContactProps } from '../types';

/** Form – data from CMS */
export function Contact1({ title, subtitle, email, phone, address }: WebuContactProps) {
  return (
    <section className="webu-contact webu-contact--contact-1">
      <div className="webu-contact__inner">
        {title && <h2 className="webu-contact__title">{title}</h2>}
        {subtitle && <p className="webu-contact__subtitle">{subtitle}</p>}
        <div className="webu-contact__info">
          {email && <p><strong>Email:</strong> <a href={`mailto:${email}`}>{email}</a></p>}
          {phone && <p><strong>Phone:</strong> {phone}</p>}
          {address && <p><strong>Address:</strong> {address}</p>}
        </div>
        <form className="webu-contact__form" onSubmit={(e) => e.preventDefault()}>
          <input type="text" placeholder="Name" className="webu-contact__input" aria-label="Name" />
          <input type="email" placeholder="Email" className="webu-contact__input" aria-label="Email" />
          <textarea placeholder="Message" className="webu-contact__textarea" aria-label="Message" rows={4} />
          <button type="submit" className="webu-contact__submit">Send</button>
        </form>
      </div>
    </section>
  );
}
