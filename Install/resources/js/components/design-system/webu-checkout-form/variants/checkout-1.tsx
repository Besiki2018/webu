import type { WebuCheckoutFormProps } from '../types';

/** Simple – data from CMS */
export function Checkout1({ action, method = 'post', labels }: WebuCheckoutFormProps) {
  return (
    <section className="webu-checkout-form webu-checkout-form--checkout-1">
      <form className="webu-checkout-form__form" action={action} method={method}>
        <div className="webu-checkout-form__group">
          <label className="webu-checkout-form__label">{labels?.email ?? 'Email'}</label>
          <input type="email" name="email" className="webu-checkout-form__input" required />
        </div>
        <div className="webu-checkout-form__group">
          <label className="webu-checkout-form__label">{labels?.address ?? 'Address'}</label>
          <input type="text" name="address" className="webu-checkout-form__input" />
        </div>
        <button type="submit" className="webu-checkout-form__submit">{labels?.submit ?? 'Place order'}</button>
      </form>
    </section>
  );
}
