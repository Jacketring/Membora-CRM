'use client';

import { ArrowRight, Dumbbell, Eye, Lock, Mail } from 'lucide-react';
import { FormEvent, useState } from 'react';
import { useRouter } from 'next/navigation';
import { login, storeSession } from '@/lib/api';

export default function LoginPage() {
  const router = useRouter();
  const [email, setEmail] = useState('admin@nexofit.demo');
  const [password, setPassword] = useState('MemboraDemo2026!');
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError('');
    setLoading(true);

    try {
      const session = await login(email, password);
      storeSession(session);
      router.push('/dashboard');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'No se pudo iniciar sesión');
    } finally {
      setLoading(false);
    }
  }

  return (
    <main className="login-screen">
      <div className="login-overlay" />
      <section className="login-panel" aria-label="Inicio de sesión">
        <div className="brand-lockup brand-lockup--login">
          <div className="brand-icon">
            <Dumbbell size={34} />
          </div>
          <h1>Membora CRM</h1>
          <p>Portal de gestión fitness</p>
        </div>

        <form className="login-card" onSubmit={handleSubmit}>
          <header>
            <h2>Accede a tu CRM</h2>
            <p>Introduce tus credenciales para gestionar NexoFit Studio.</p>
          </header>

          <label className="field">
            <span>Email</span>
            <div className="input-shell">
              <Mail size={18} />
              <input
                autoComplete="email"
                onChange={(event) => setEmail(event.target.value)}
                required
                type="email"
                value={email}
              />
            </div>
          </label>

          <label className="field">
            <span>Contraseña</span>
            <div className="input-shell">
              <Lock size={18} />
              <input
                autoComplete="current-password"
                onChange={(event) => setPassword(event.target.value)}
                required
                type="password"
                value={password}
              />
              <Eye size={18} />
            </div>
          </label>

          <div className="login-options">
            <label>
              <input type="checkbox" />
              <span>Recordarme</span>
            </label>
            <button type="button">¿Has olvidado la contraseña?</button>
          </div>

          {error ? <p className="form-error">{error}</p> : null}

          <button className="primary-action" disabled={loading} type="submit">
            {loading ? 'Accediendo...' : 'Iniciar sesión'}
            <ArrowRight size={18} />
          </button>

          <div className="demo-hint">
            <strong>Demo:</strong> admin@nexofit.demo / MemboraDemo2026!
          </div>
        </form>
      </section>
    </main>
  );
}
