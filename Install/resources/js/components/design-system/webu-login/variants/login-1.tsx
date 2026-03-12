import { Link } from '@inertiajs/react';
import type { WebuLoginProps } from '../types';

/** Classic – data from CMS */
export function Login1({ action, redirectUrl, registerUrl, forgotUrl, title, labels }: WebuLoginProps) {
  return (
    <section className="webu-login webu-login--login-1">
      <div className="webu-login__inner">
        <h2 className="webu-login__title">{title ?? labels?.title ?? 'Sign in'}</h2>
        <form className="webu-login__form" action={action} method="post">
          {redirectUrl && <input type="hidden" name="redirect" value={redirectUrl} />}
          <div className="webu-login__group">
            <label className="webu-login__label">{labels?.email ?? 'Email'}</label>
            <input type="email" name="email" className="webu-login__input" required autoComplete="email" />
          </div>
          <div className="webu-login__group">
            <label className="webu-login__label">{labels?.password ?? 'Password'}</label>
            <input type="password" name="password" className="webu-login__input" required autoComplete="current-password" />
          </div>
          {forgotUrl && (
            <p className="webu-login__forgot">
              <Link href={forgotUrl}>{labels?.forgot ?? 'Forgot password?'}</Link>
            </p>
          )}
          <button type="submit" className="webu-login__submit">{labels?.submit ?? 'Sign in'}</button>
        </form>
        {registerUrl && (
          <p className="webu-login__register">
            {labels?.noAccount ?? "Don't have an account?"} <Link href={registerUrl}>{labels?.register ?? 'Register'}</Link>
          </p>
        )}
      </div>
    </section>
  );
}
