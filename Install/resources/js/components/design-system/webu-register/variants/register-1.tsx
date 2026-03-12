import { Link } from '@inertiajs/react';
import type { WebuRegisterProps } from '../types';

/** Data from CMS */
export function Register1({ action, loginUrl, title, labels }: WebuRegisterProps) {
  return (
    <section className="webu-register webu-register--register-1">
      <div className="webu-register__inner">
        <h2 className="webu-register__title">{title ?? labels?.title ?? 'Create account'}</h2>
        <form className="webu-register__form" action={action} method="post">
          <div className="webu-register__group">
            <label className="webu-register__label">{labels?.name ?? 'Name'}</label>
            <input type="text" name="name" className="webu-register__input" required autoComplete="name" />
          </div>
          <div className="webu-register__group">
            <label className="webu-register__label">{labels?.email ?? 'Email'}</label>
            <input type="email" name="email" className="webu-register__input" required autoComplete="email" />
          </div>
          <div className="webu-register__group">
            <label className="webu-register__label">{labels?.password ?? 'Password'}</label>
            <input type="password" name="password" className="webu-register__input" required autoComplete="new-password" />
          </div>
          <button type="submit" className="webu-register__submit">{labels?.submit ?? 'Register'}</button>
        </form>
        {loginUrl && (
          <p className="webu-register__login">
            {labels?.hasAccount ?? 'Already have an account?'} <Link href={loginUrl}>{labels?.login ?? 'Sign in'}</Link>
          </p>
        )}
      </div>
    </section>
  );
}
