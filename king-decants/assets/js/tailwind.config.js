/**
 * King Decants — assets/js/tailwind.config.js
 * ---------------------------------------------------------------------------
 * Configuração do Tailwind Play CDN (sem build). Carregado logo após
 * <script src="https://cdn.tailwindcss.com">, estende o tema com:
 *   - ink-*  : pretos/cinzas quentes (fundo e textos de apoio)
 *   - gold-* : dourados (destaques, ícones, botões)
 *   - fontes : Cormorant Garamond (serifa) e Inter (sem serifa)
 *
 * Observação: o Play CDN é ótimo para MVP. Para produção com alto tráfego,
 * considere gerar um CSS estático com o Tailwind CLI (opcional, fora do escopo).
 */
tailwind.config = {
  theme: {
    extend: {
      colors: {
        ink: {
          950: '#070707',
          900: '#0c0c0c',
          850: '#111111',
          800: '#161616',
          700: '#202020',
          600: '#2c2b29',
          500: '#6b6862',
          400: '#8f8b83',
          300: '#b8b4ab',
          200: '#d6d3cd',
          100: '#ece9e3',
        },
        gold: {
          100: '#f8efd2',
          200: '#f1dfa6',
          300: '#e6c76f',
          400: '#d4af37',
          500: '#b8952a',
          600: '#8f7220',
          700: '#5e4b16',
        },
      },
      fontFamily: {
        serif: ['"Cormorant Garamond"', 'Georgia', 'serif'],
        sans: ['Inter', 'system-ui', '-apple-system', 'Segoe UI', 'sans-serif'],
      },
      keyframes: {
        'fade-up': {
          '0%': { opacity: '0', transform: 'translateY(16px)' },
          '100%': { opacity: '1', transform: 'translateY(0)' },
        },
        glow: {
          '0%, 100%': { opacity: '.75', transform: 'scale(1)' },
          '50%': { opacity: '1', transform: 'scale(1.04)' },
        },
      },
      animation: {
        'fade-up': 'fade-up .8s cubic-bezier(.2,.7,.2,1) both',
        glow: 'glow 6s ease-in-out infinite',
      },
    },
  },
};
