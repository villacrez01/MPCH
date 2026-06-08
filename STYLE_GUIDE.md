# Guía de Estilos OTI - Tokens de Diseño

## 1. Paleta de Colores

### Primaria / Marca
- `--primary: #0f2942` - Azul Marino Profundo
- `--primary-light: #2563a0` - Azul Marino Claro
- `--primary-dark: #081828` - Azul Marino Oscuro
- `--primary-soft: rgba(15, 41, 66, 0.06)` - Azul Marino Transparente Suave
- `--primary-glow: rgba(15, 41, 66, 0.10)` - Azul Marino Transparente Brillante

### Dorado - Acento Institucional
- `--gold: #c9922a` - Dorado Institucional
- `--gold-light: #dca543` - Dorado Claro
- `--gold-dark: #a6791f` - Dorado Oscuro
- `--gold-soft: rgba(201, 146, 42, 0.10)` - Dorado Transparente Suave
- `--gold-soft-md: rgba(201, 146, 42, 0.22)` - Dorado Transparente Medio
- `--gold-soft-lg: rgba(201, 146, 42, 0.30)` - Dorado Transparente Oscuro

### Estados Semánticos
- `--success: #0f6a4e` - Verde Éxito
- `--success-soft: #e6f5f0` - Verde Éxito Suave
- `--success-ring: rgba(15, 106, 78, 0.15)` - Anillo Verde Éxito
- `--warning: #b45309` - Naranja Advertencia
- `--warning-soft: #fef3c7` - Naranja Advertencia Suave
- `--danger: #b91c1c` - Rojo Peligro
- `--danger-soft: #fef2f2` - Rojo Peligro Suave
- `--danger-ring: rgba(185, 28, 28, 0.15)` - Anillo Rojo Peligro
- `--info: #0369a1` - Azul Información
- `--info-soft: #e0f2fe` - Azul Información Suave

### Neutros
- `--bg-main: #edf0f6` - Fondo Principal
- `--bg-card: #ffffff` - Fondo de Tarjeta
- `--bg-sidebar: #fafbfc` - Fondo de Barra Lateral
- `--bg-hover: #eceef6` - Fondo de Hover
- `--text-primary: #111a2e` - Texto Principal
- `--text-secondary: #4a5e78` - Texto Secundario
- `--text-muted: #7e92a9` - Texto Atenuao
- `--text-inverse: #f8fafc` - Texto Inverso
- `--border-light: #dfe6f0` - Borde Claro
- `--border-medium: #c4d0dc` - Borde Medio
- `--border-dark: #b0bfd0` - Borde Oscuro

## 2. Tipografía

### Familia de Fuentes
- `--font-family: 'Outfit', -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;`

### Tamaños de Fuente
- `--font-size-xs: 10px`
- `--font-size-sm: 11px`
- `--font-size-base: 13.5px`
- `--font-size-md: 15px`
- `--font-size-lg: 17px`
- `--font-size-xl: 20px`
- `--font-size-2xl: 24px`
- `--font-size-3xl: 28px`
- `--font-size-4xl: 34px`

### Alturas de Línea
- `--leading-tight: 1.2`
- `--leading-normal: 1.5`
- `--leading-relaxed: 1.75`

## 3. Espaciado

### Escala Espacial
- `--space-0: 0px`
- `--space-1: 4px`
- `--space-2: 8px`
- `--space-3: 12px`
- `--space-4: 16px`
- `--space-5: 20px`
- `--space-6: 24px`
- `--space-8: 32px`
- `--space-10: 40px`
- `--space-12: 48px`
- `--space-16: 64px`

### Espaciado Abreviado
- `--space-xs: 4px`
- `--space-sm: 8px`
- `--space-md: 16px`
- `--space-lg: 24px`
- `--space-xl: 32px`
- `--space-2xl: 48px`

## 4. Bordes y Redondez

### Radio de Bordes
- `--radius-xs: 4px`
- `--radius-sm: 6px`
- `--radius-md: 10px`
- `--radius-lg: 14px`
- `--radius-xl: 20px`
- `--radius-2xl: 30px`
- `--radius-full: 9999px`

### Grosor de Bordes
- Se utilizan valores específicos en componentes (ej: 1px, 1.5px)

## 5. Sombras

### Sombras Elevación
- `--shadow-1: 0 1px 2px 0 rgba(17, 26, 46, 0.04);`
- `--shadow-2: 0 1px 3px 0 rgba(17, 26, 46, 0.06), 0 1px 2px -1px rgba(17, 26, 46, 0.04);`
- `--shadow-3: 0 4px 6px -1px rgba(17, 26, 46, 0.07), 0 2px 4px -2px rgba(17, 26, 46, 0.05);`
- `--shadow-4: 0 10px 15px -3px rgba(17, 26, 46, 0.08), 0 4px 6px -4px rgba(17, 26, 46, 0.05);`
- `--shadow-5: 0 20px 25px -5px rgba(17, 26, 46, 0.10), 0 8px 10px -6px rgba(17, 26, 46, 0.05);`
- `--shadow-6: 0 25px 50px -12px rgba(17, 26, 46, 0.20);`
- `--shadow-gold: 0 4px 14px rgba(201, 146, 42, 0.22);`
- `--shadow-gold-lg: 0 8px 28px rgba(201, 146, 42, 0.28);`

## 6. Movimiento y Transiciones

### Duraciones
- `--duration-instant: 0ms`
- `--duration-fast: 90ms`
- `--duration-normal: 150ms`
- `--duration-slow: 230ms`
- `--duration-slower: 350ms`
- `--duration-slowest: 500ms`

### Funciones de Easing
- `--transition-fast: var(--duration-normal);`
- `--transition-normal: var(--duration-slow);`
- `--transition-slow: var(--duration-slower);`
- `--ease-out: cubic-bezier(0.4, 0, 0.2, 1);`
- `--ease-in: cubic-bezier(0.4, 0, 1, 1);`
- `--ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);`
- `--ease-spring: cubic-bezier(0.175, 0.885, 0.32, 1.275);`

## 7. Z-index

### Capas de Superposición
- `--z-dropdown: 100`
- `--z-sticky: 200`
- `--z-fixed: 300`
- `--z-modal-backdrop: 400`
- `--z-modal: 500`
- `--z-popover: 600`
- `--z-tooltip: 700`
- `--z-toast: 800`

## 8. Layout

### Dimensiones de Layout
- `--sidebar-width: 260px`
- `--header-height: 60px`
- `--max-content-width: 1400px`

## 9. Aplicación en Componentes

### Botones Primarios
- Fondo: `linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%)`
- Color de texto: blanco
- Sombra: `0 4px 14px rgba(99, 102, 241, 0.3)`
- Hover: transform `translateY(-2px)`, sombra aumentada

### Botones Secundarios
- Fondo: `var(--bg-card)`
- Color de texto: `var(--text-secondary)`
- Borde: `1px solid var(--border-light)`
- Hover: borde `var(--primary)`, color `var(--primary)`

### Tarjetas
- Fondo: `var(--bg-card)`
- Borde: `1px solid var(--border-light)`
- Radio: `var(--radius-lg)`
- Sombra: `var(--shadow-sm)`
- Hover: borde `var(--primary-light)`, transform `translateY(-2px)`

### Inputs y Selects
- Fondo: `white`
- Borde: `1px solid #e2e8f0`
- Radio: `8px`
- Padding: `10px 12px`
- Fuente: `14px`
- Focus: borde `var(--primary)`, sombra `0 0 0 3px rgba(67, 56, 202, 0.1)`

### Etiquetas y Texto
- Color primario: `var(--text-primary)`
- Color secundario: `var(--text-secondary)`
- Color atenuao: `var(--text-muted)`
- Tamaño base: `13.5px` o `14px` dependiendo del componente
- Peso de fuente: `400` normal, `500` medio, `600` semibold, `700` bold

## 10. Guía de Actualización

Esta guía establece los tokens base que deben utilizarse en todos los componentes de la Etapa 1 (árbol jerárquico, formularios de ubicación, búsquedas, asignaciones). Cualquier nuevo componente debe:

1. Utilizar exclusivamente estas variables CSS para colores, espaciado, tipografía, etc.
2. Evitar valores hardcoded que no provengan de estos tokens
3. Extender la guía con nuevos tokens solo cuando sea absolutamente necesario y siguiendo el mismo patrón de nomenclatura
4. Mantener la consistencia con el diseño existente en app.css y login.css