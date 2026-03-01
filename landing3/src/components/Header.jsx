import StaggeredMenu from './StaggeredMenu';

export default function Header() {
  const navLinks = [
    { label: 'Inicio', ariaLabel: 'Ir a Inicio', link: '#inicio' },
    { label: 'Servicios', ariaLabel: 'Ir a Servicios', link: '#servicios' },
    { label: 'Nosotros', ariaLabel: 'Ir a Nosotros', link: '#nosotros' },
    { label: 'Menú', ariaLabel: 'Ir a Menú', link: '#menu' },
    { label: 'Ubicación', ariaLabel: 'Ir a Ubicación', link: '#contacto' },
  ];

  const socialItems = [
    { label: 'Instagram', link: 'https://www.instagram.com/laruta11foodtruck/' },
    { label: 'WhatsApp', link: 'https://wa.me/56922504275' },
    { label: 'App', link: 'https://app.laruta11.cl' }
  ];

  return (
    <StaggeredMenu
      isFixed={true}
      position="right"
      items={navLinks}
      socialItems={socialItems}
      colors={['#0a0a0b', '#1a1a1c', '#fac815']}
      accentColor="#fac815"
      logoUrl="https://laruta11-images.s3.amazonaws.com/menu/logo-optimized.png"
      displayItemNumbering={true}
    />
  );
}