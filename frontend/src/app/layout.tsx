import type { Metadata } from 'next';
import './globals.css';

export const metadata: Metadata = {
  title: 'Membora CRM',
  description: 'CRM SaaS para gimnasios y centros fitness.',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="es">
      <body>{children}</body>
    </html>
  );
}
