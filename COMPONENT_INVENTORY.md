# Inventario de Componentes Necesarios - Etapa 1

## Componentes para el Módulo de Gestión de Estructura Organizacional

### 1. Árbol Jerárquico
- **TreeView**: Contenedor principal del árbol colapsable/expandible
- **TreeNode**: Nodo individual con indicadores de nivel y tipo
- **TreeToggle**: Botón para expandir/colapsar nodos
- **TreeIcon**: Ícono visual según tipo de ubicación (sede, piso, área, subárea)
- **TreeInfo**: Información del nodo (nombre, tipo, metadata)
- **TreeStats**: Contadores de usuarios y equipos por ubicación
- **TreeActions**: Menú de acciones por nodo (editar, eliminar, agregar hijo)

### 2. Formulario de Ubicación
- **LocationForm**: Formulario contextual según tipo de ubicación
- **ConditionalFields**: Campos que se muestran/ocultan según el tipo
- **ParentSelector**: Selector de ubicación padre con filtrado y búsqueda
- **FormValidator**: Validaciones en tiempo real y mensajes de error
- **FormActions**: Botones de guardar y cancelar con estados de carga

### 3. Búsqueda y Filtrado
- **SearchBar**: Campo de búsqueda por texto con sugerencias
- **TypeFilter**: Filtro desplegable por tipo de ubicación
- **ParentFilter**: Filtro por ubicación padre (mostrar hijos)
- **ResultsList**: Lista de resultados con indicadores de jerarquía

### 4. Asignación de Usuarios
- **UserAssignmentPanel**: Panel para asignar usuarios a ubicaciones
- **AvailableUsersList**: Lista de usuarios sin ubicación asignada
- **LocationTargetSelector**: Selector de ubicación objetivo con visualización de jerarquía
- **AssignedUsersView**: Vista de usuarios ya asignados a cada ubicación
- **AutoDetectionPrompt**: Modal para detección automática de subáreas

### 5. Detalle de Ubicación
- **LocationDetailModal**: Modal/vista de detalle de ubicación
- **LocationInfoSection**: Información básica de la ubicación
- **BreadcrumbNavigation**: Breadcrumbs/ruta de navegación
- **AssignedUsersList**: Lista de usuarios asignados
- **EquipmentCounters**: Contadores de equipos por estado
- **ChangeHistorySection**: Historial de cambios (para futuras implementaciones)

### 6. Componentes Compartidos
- **ModalOverlay**: Fondo y contenedor para modales
- **Button**: Botón primario y secundario con estados
- **Input**: Campo de texto con estilos y validaciones
- **Select**: Dropdown con búsqueda y filtrado
- **Badge**: Indicadores de estado y conteo
- **Spinner**: Indicador de carga
- **EmptyState**: Vista para estados vacíos
- **Tooltip**: Información contextual al hover

### 7. Estados y Interacciones
- **LoadingState**: Estados de carga para APIs
- **ErrorState**: Estados de error con opciones de reintento
- **EmptyState**: Estados vacíos con mensajes instructivos
- **HoverEffects**: Efectos visuales al pasar el cursor
- **FocusStates**: Estados de foco para accesibilidad
- **DisabledStates**: Estados deshabilitados con estilos apropiados

## Tecnologías y Dependencias Actuales

### Frontend
- HTML5 semántico
- CSS3 con variables personalizadas (CSS Custom Properties)
- JavaScript vanilla (ES6+)
- No se utilizan frameworks frontend (React, Vue, Angular, etc.)

### Backend
- PHP 7.4+
- PostgreSQL
- Arquitectura MVC personalizada

### Herramientas de Desarrollo
- Kilo (asistente de desarrollo basado en IA)
- Composer para gestión de dependencias de PHP
- npm en directorio .kilo para plugin de Kilo

## Recomendaciones para la Etapa 2

1. **Mantener el enfoque actual**: Continuar con JavaScript vanilla y CSS puro para evitar sobrecarga
2. **Crear una capa de componentes**: Implementar un patrón de componentes reutilizables sin framework
3. **Establecer convenciones de nomencladura**: BEM o similar para CSS
4. **Implementar manejo de estado sencillo**: Patrón de publicación/suscripción o almacenamiento central ligero
5. **Optimizar para rendimiento**: Minimizar reflows y repaints, usar requestAnimationFrame cuando sea necesario
6. **Mantener accesibilidad**: Seguir WCAG 2.1 AA desde el inicio