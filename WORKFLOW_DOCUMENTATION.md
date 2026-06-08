# Flujo de Estructura Organizacional - Sistema OTI

## 1. Visualización del Árbol Jerárquico
**Objetivo:** Mostrar la estructura organizacional completa en formato de árbol colapsable/expandible

**Pasos:**
1. Usuario accede al módulo de estructura orgánica
2. Sistema llama a `GET /api/locations.php?action=get-tree` para obtener el árbol completo
3. Frontend renderiza el árbol con:
   - Nodos raíz (sedes) sin padre
   - Nodos hijos indentados según nivel (sede > piso > área > subárea)
   - Indicadores visuales de nivel y tipo de ubicación (colores, íconos)
   - Contadores de usuarios y equipos por ubicación
   - Botones de acción por nodo (expandir/colapsar, editar, eliminar, agregar hijo)

**Estados:**
- Carga: Mostrar esqueleto/loading
- Error: Mostrar mensaje de error con opción de reintentar
- Vacío: Mostrar estado vacío con mensaje instructivo

## 2. Creación/Edición de Ubicaciones
**Objetivo:** Permitir crear y modificar ubicaciones en cualquier nivel de la jerarquía

**Flujos:**

### 2.1 Crear Ubicación
1. Usuario hace clic en "Nueva Ubicación" o en "+" de un nodo específico
2. Sistema muestra modal de creación con formulario contextual:
   - Campos comunes: nombre, descripción
   - Campos específicos por tipo:
     * SEDE: nombre, descripción, activo/inactivo
     * PISO: nombre, número de piso, edificio, descripción
     * ÁREA: nombre, tipo de área, descripción, ubicación padre (sede/piso)
     * SUBAREA: nombre, descripción, ubicación padre (área)
3. Selector de ubicación padre filtrado por tipo compatible y con búsqueda
4. Validaciones en tiempo real:
   - Nombre requerido
   - Campos específicos según tipo (ej: número de piso solo para PISO)
5. Al enviar: llamada a `POST /api/locations.php?action=create`
6. Éxito: cerrar modal, recargar árbol, mostrar notificación
7. Error: mostrar mensaje de error en formulario

### 2.2 Editar Ubicación
1. Usuario hace clic en nodo existente o botón de editar
2. Sistema muestra modal de edición con datos pre-cargados
3. Mismo formulario que creación pero con campos editables
4. Al enviar: llamada a `POST /api/locations.php?action=update`
5. Manejo de respuesta igual que creación

## 3. Búsqueda y Filtrado de Ubicaciones
**Objetivo:** Encontrar ubicaciones específicas mediante diversos criterios

**Componentes:**
- Búsqueda por texto: busca en nombre de ubicación
- Filtro por tipo: SEDE, PISO, ÁREA, SUBAREA, OFICINA
- Filtro por ubicación padre: mostrar solo hijos de una sede específica

**Implementación:**
1. Usuario escribe en campo de búsqueda o selecciona filtros
2. Sistema llama a `GET /api/locations.php?action=search&q={query}&type={type}&sede_id={sede_id}`
3. Resultados mostrados en lista o filtrando el árbol existente
4. Cada resultado muestra indicadores de jerarquía (sede > piso > área)

## 4. Asignación de Usuarios a Ubicaciones
**Objetivo:** Asignar usuarios a ubicaciones con detección automática de subáreas

**Flujos:**

### 4.1 Asignación Básica
1. Usuario selecciona opción "Asignar Usuario" desde nodo o menú principal
2. Sistema muestra panel con:
   - Lista de usuarios disponibles (sin ubicación asignada)
   - Selector de ubicación objetivo (con visualización de jerarquía)
3. Usuario selecciona usuario y ubicación
4. Sistema llama a `POST /api/locations.php?action=assign-user`
5. Éxito: actualizar contadores, mostrar notificación

### 4.2 Detección Automática de Subáreas
1. Al seleccionar un área como ubicación objetivo
2. Sistema verifica si el área tiene subáreas
3. Si tiene subáreas, muestra mensaje: "Esta área tiene subáreas. ¿También desea asignar el usuario a las subáreas?"
4. Opciones: 
   - Solo al área seleccionada
   - Al área y todas sus subáreas
   - Selección específica de subáreas
5. Según elección, hacer llamadas apropiadas a la API de asignación

## 5. Visualización de Detalles de Ubicación
**Objetivo:** Mostrar información completa de una ubicación específica

**Pasos:**
1. Usuario hace clic en nodo del árbol
2. Sistema llama a `GET /api/locations.php?action=get-detail&id={locationId}`
3. Se muestra modal/detail view con:
   - Información básica de la ubicación (nombre, tipo, descripción, etc.)
   - Breadcrumbs/ruta de navegación (sede > piso > área)
   - Lista de usuarios asignados con sus datos básicos
   - Contadores de equipos por estado (activo, mantenimiento, etc.)
   - Historial de cambios (si está disponible en el futuro)

## 6. Operaciones por Lote
**Objetivo:** Realizar acciones en múltiples ubicaciones simultáneamente

**Funcionalidades:**
- Selección múltiple de nodos (checkbox o shift+click)
- Menú de acciones por lote:
  - Asignar usuarios seleccionados a ubicación
  - Eliminar/desactivar ubicaciones (con validación de dependencias)
  - Exportar reporte

## 7. Confirmaciones Críticas
**Objetivo:** Prevenir acciones irreversibles sin confirmación

**Implementación:**
- Antes de eliminar ubicación: verificar si tiene hijos o usuarios/equipos asignados
- Mostrar modal de confirmación con:
  - Descripción de lo que se eliminará
  - Lista de dependencias que se afectarán
  - Opciones: Cancelar, Continuar (posiblemente con soft delete)