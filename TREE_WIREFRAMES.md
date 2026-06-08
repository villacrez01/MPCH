# Wireframes del Árbol Jerárquico - Etapa 2

## 1. Vista de Árbol Colapsable/Expandible

```
┌─────────────────────────────────────────────────────────────────────┐
│                           ESTRUCTURA ORGANIZACIONAL                 │
├─────────────────────┬───────────────────────────────────────────────┤
│ 🔍 Buscar ubicación │ ▼ Todos los tipos       [ Nueva Ubicación ]   │
├─────────────────────┴───────────────────────────────────────────────┤
│                                                                     │
│  ▶ MUNICIPALIDAD                                                   │
│     ├─ 🏢 SAN MARTIN (SEDE)                                        │
│     │   ├─ 📋 1er Piso (PISO)                                      │
│     │   │   ├─ 👥 Sistema (ÁREA)                                   │
│     │   │   │   ├─ [Usuario1]                                      │
│     │   │   │   └─ [Usuario2]                                      │
│     │   │   └─ 🖨️ Impresoras (SUBAREA)                             │
│     │   │       ├─ [Usuario3]                                      │
│     │   │       └─ Impresora HP #001                               │
│     │   └─ 📋 2do Piso (PISO)                                      │
│     │       └─ 💰 Finanzas (ÁREA)                                  │
│     │           ├─ Contador General                                │
│     │           └─ Auxiliar Contable                               │
│     │                                                                    
│     └─ 🏢 SAN ISIDRO (SEDE)                                        │
│         └─ 📋 Planta Baja (PISO)                                   │
│             └─ 🏥 Salud (ÁREA)                                     │
│                 ├─ Médico 1                                        │
│                 └─ Enfermero 1                                     │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

## 2. Indicadores Visuales de Nivel y Tipo

### Por Color (línea izquierda en nodos):
- **Sede**: #4338ca (Índigo)
- **Dirección**: #6366f1 (Azul Claro)  
- **Área**: #0284c7 (Azul Cielo)
- **Subárea**: #f59e0b (Ámbar)
- **Piso**: #10b981 (Esmeralda)
- **Oficina**: #8b5cf6 (Violeta)

### Por Ícono:
- **Sede/Dirección**: 🏢 (Edificio)
- **Piso**: 📋 (Lista/Piso)
- **Área**: 👥 (Personas)
- **Subárea**: 🖨️ o 📦 (Recurso específico)
- **Oficina**: 🚪 (Puerta)

### Por Badge:
```
<span class="type-badge sede">SEDE</span>
<span class="type-badge piso">PISO</span>
<span class="type-badge area">ÁREA</span>
<span class="type-badge subarea">SUBAREA</span>
<span class="type-badge oficina">OFICINA</span>
```

## 3. Contadores de Usuarios/Equipos

```
<div class="tree-stats">
  <span class="tree-stat users">👤 3 usuarios</span>
  <span class="tree-stat equipment">💼 5 equipos</span>
</div>
```

### Variantes:
- Sin usuarios: `👤 0 usuarios` (color más tenue)
- Un usuario: `👤 1 usuario` (singular)
- Múltiples: `👤 5 usuarios` (plural)
- Sin equipos: `💼 0 equipos`
- Con equipos: `💼 12 equipos`

## 4. Acciones por Nodo

### Menú Contextual (al hacer clic derecho o botón de acciones):
```
┌──────────────────────────────┐
│ ✏️ Editar ubicación          │
│ ➕ Agregar ubicación hija     │
│ ➖ Eliminar ubicación         │
│ 👥 Asignar usuario           │
│ 📋 Ver detalle               │
│ 📊 Ver reporte               │
└──────────────────────────────┘
```

### Botones de Acción Visibles:
- En hover del nodo: aparecer botones pequeños alineados a la derecha
- Botón de toggle (▶/▼) en la izquierda para expandir/colapsar
- En mobile: menú desplegable al hacer swipe o long press

## 5. Diseño de Alta Fidelidad - Responsividad

### Escritorio (>1024px):
- Vista completa de árbol con panel lateral expandido
- Búsqueda y filtros en barra horizontal
- Tarjetas de nodo con información completa
- Estadísticas visibles siempre

### Tablet (640-1024px):
- Barra lateral colapsable con ícono de menú
- Búsqueda y filtros en barra que se adapta
- Nodos con padding reducido
- Estadísticas en una línea

### Móvil (<640px):
- Barra lateral totalmente colapsable (superposición)
- Búsqueda ocupando ancho completo
- Filtros en desplegable o accordion
- Nodos con diseño compacto
- Estadísticas opcionales (toggle para mostrar/ocultar)

## 6. Estados de Carga y Error

### Estado de Carga:
```
┌─────────────────────────────────────────────┐
│                                             │
│         [●●●] Cargando estructura...        │
│                                             │
│        (esqueleto de árbol animado)         │
│                                             │
└─────────────────────────────────────────────┘
```

### Estado de Error:
```
┌─────────────────────────────────────────────┐
│                                             │
│         ⚠️ Error al cargar datos            │
│                                             │
│     No se pudo obtener la estructura        │
│     desde el servidor.                      │
│                                             │
│            [ Reintentar ] [ Detalles ]      │
│                                             │
└─────────────────────────────────────────────┘
```

### Estado Vacío:
```
┌─────────────────────────────────────────────┐
│                                             │
│         📭                                  │
│                                             │
│     No hay ubicaciones registradas          │
│                                             │
│        Haga clic en "Nueva Ubicación"       │
│         para comenzar                       │
│                                             │
└─────────────────────────────────────────────┘
```

## 7. Interacciones Definidas

### Expansión/Colapso:
- Click en botón toggle (▶/▼) expande/contrae ese nodo
- Doble click en nombre de nodo expande/contrae
- Click en cualquier otra parte del nodo selecciona el nodo
- Teclado: Enter expande/contrae, Flechas navegan

### Arrastrar y Soltar (Opcional - Para Futuro):
- Activar modo edición con botón específico
- Arrastrar nodo para cambiar su padre
- Línea guía muestra posición de inserción
- Validación en tiempo real (no permitir crear ciclos)
- Cancelar con tecla ESC

### Selección Múltiple:
- Mantener Ctrl (Cmd en Mac) + click para seleccionar múltiples
- Shift + click para seleccionar rango
- Barra de acciones por lote aparece en bottom/top
- Acciones: asignar usuarios, eliminar, exportar

### Menú Contextual:
- Click derecho en nodo muestra menú contextual
- Posicionado relativo al nodo clickeado
- Opciones adaptadas al tipo de nodo y estado
- Teclado: Shift+F10 o tecla de menú contextual

## 8. Especificaciones para Desarrolladores

### Componente TreeView Props:
```javascript
{
  locations: Array,        // Datos jerárquicos de ubicaciones
  onNodeSelect: Function,  // Callback cuando se selecciona un nodo
  onNodeExpand: Function,  // Callback cuando se expande/contrapa
  onNodeAction: Function,  // Callback para acciones de nodo
  searchTerm: String,      // Término de búsqueda actual
  typeFilter: String,      // Filtro de tipo activo
  parentFilter: String,    // ID de padre filtrado
  isLoading: Boolean,      // Estado de carga
  hasError: Boolean,       // Estado de error
  enableDragDrop: Boolean  // Si está activo arrastre y suelta
}
```

### Componente TreeNode Props:
```javascript
{
  node: Object,           // Datos del nodo (id, name, type, etc.)
  level: Number,          // Nivel de profundidad (0=raíz)
  isSelected: Boolean,    // Si está seleccionado
  isExpanded: Boolean,    // Si sus hijos están visibles
  hasChildren: Boolean,   // Si tiene hijos nodos
  userCount: Number,      // Número de usuarios asignados
  equipmentCount: Number, // Número de equipos asignados
  onToggle: Function,     // Callback para toggle de expansión
  onSelect: Function,     // Callback para selección
  onAction: Function,     // Callback para acciones
  contextMenuItems: Array // Items del menú contextual
}
```

### Eventos:
- `node-select`: Cuando se hace click en un nodo
- `node-expand`: Cuando se expande o colapsa un nodo
- `node-action:edit`: Cuando se elige editar nodo
- `node-action:add-child`: Cuando se elige agregar hijo
- `node-action:delete`: Cuando se elige eliminar nodo
- `node-action:assign-user`: Cuando se elige asignar usuario
- `node-contextmenu`: Cuando se abre menú contextual
- `search-change`: Cuando cambia el término de búsqueda
- `filter-change`: Cuando cambia algún filtro

### Estados:
- `idle`: Estado inicial, listo para interactuar
- `loading`: Cargando datos desde API
- `error`: Error al cargar datos
- `empty`: No hay datos para mostrar
- `searching`: Filtrando resultados por búsqueda
- `filtered`: Mostrando resultados filtrados
- `dragging`: En modo arrastre y suelta (opcional)
- `multi-select`: En modo selección múltiple
```