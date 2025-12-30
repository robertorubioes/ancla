# Guía de Gestión de Usuarios - Administradores de Tenant

> 📅 **Fecha**: 2025-12-30  
> 👥 **Audiencia**: Administradores de organizaciones (Tenant Admins)  
> 🎯 **Objetivo**: Gestionar usuarios y permisos en tu organización

---

## 📋 Introducción

Como administrador de tu organización en Firmalum, tienes control completo sobre quién puede acceder a tu cuenta y qué permisos tienen. Esta guía te enseñará cómo:

- ✅ Invitar nuevos usuarios a tu organización
- ✅ Gestionar roles y permisos
- ✅ Editar información de usuarios
- ✅ Desactivar o eliminar usuarios
- ✅ Gestionar invitaciones pendientes

---

## 🚀 Acceso al Panel de Usuarios

1. Inicia sesión en Firmalum con tu cuenta de administrador
2. Navega a: **Settings → Users** o accede directamente a `/settings/users`
3. Verás el panel de gestión de usuarios de tu organización

**Requisitos:**
- Debes tener el role **Administrator**
- Solo administradores pueden acceder a esta sección

---

## 👥 Roles y Permisos

Firmalum tiene 3 roles disponibles para usuarios de tenant:

### 🔴 Administrator
**Acceso completo** sobre la organización

**Puede:**
- ✅ Gestionar usuarios (invitar, editar, eliminar)
- ✅ Asignar roles y permisos
- ✅ Crear y gestionar procesos de firma
- ✅ Acceder a todos los documentos
- ✅ Ver y exportar audit trail completo
- ✅ Configurar ajustes de la organización

**Ideal para:**
- Fundadores, directores, gerentes
- Responsables de compliance
- Administradores IT

---

### 🔵 Operator
**Puede gestionar documentos y firmas**

**Puede:**
- ✅ Crear nuevos procesos de firma
- ✅ Subir y gestionar documentos
- ✅ Invitar firmantes a documentos
- ✅ Ver audit trail
- ✅ Firmar documentos
- ❌ **NO** puede gestionar usuarios

**Ideal para:**
- Empleados de operaciones
- Asistentes administrativos
- Personal de back-office
- Equipos de ventas

---

### ⚫ Viewer
**Solo lectura** y firma de documentos asignados

**Puede:**
- ✅ Ver documentos asignados
- ✅ Firmar documentos electrónicamente
- ✅ Descargar documentos firmados
- ✅ Acceder a su historial de documentos
- ❌ **NO** puede crear procesos
- ❌ **NO** puede gestionar usuarios

**Ideal para:**
- Consultores externos
- Clientes con acceso limitado
- Personal temporal
- Auditores de solo lectura

---

## ✉️ Invitar un Nuevo Usuario

### Paso 1: Abrir Modal de Invitación

1. En el panel de usuarios, haz click en el botón **"Invite User"** (esquina superior derecha)
2. Se abrirá un modal con el formulario de invitación

### Paso 2: Completar el Formulario

**Campos requeridos:**

- **Full Name**: Nombre completo del usuario
  - Ejemplo: "Juan Pérez González"
  
- **Email Address**: Email válido del usuario
  - Ejemplo: "juan.perez@empresa.com"
  - ⚠️ No puede estar duplicado en tu organización
  
- **Role**: Selecciona el rol apropiado
  - Administrator
  - Operator
  - Viewer

**Campo opcional:**

- **Personal Message**: Mensaje personalizado para incluir en el email de invitación
  - Máximo 500 caracteres
  - Ejemplo: "¡Bienvenido al equipo! Estamos emocionados de tenerte con nosotros."

### Paso 3: Enviar Invitación

1. Click en **"Send Invitation"**
2. El sistema:
   - ✅ Genera un token de invitación seguro (64 caracteres)
   - ✅ Envía un email al usuario invitado
   - ✅ Crea un registro de invitación pendiente
   - ✅ Establece expiración automática (7 días)

### Paso 4: Confirmación

- Verás un mensaje: **"Invitation sent to [email]"**
- La invitación aparecerá en la sección **"Pending Invitations"**

---

## 📧 Qué Recibe el Usuario Invitado

El usuario recibirá un email con:

1. **Información de la organización**: Nombre de tu empresa
2. **Role asignado**: El rol que tendrá en el sistema
3. **Mensaje personalizado**: Si añadiste uno
4. **Botón "Accept Invitation"**: Link para crear su cuenta
5. **Fecha de expiración**: 7 días para aceptar

---

## 🔄 Gestionar Invitaciones Pendientes

En la sección **"Pending Invitations"** verás todas las invitaciones que aún no han sido aceptadas.

### Reenviar Invitación

**Cuándo usarlo:**
- El usuario no recibió el email
- El email fue a spam
- El usuario eliminó el email por error

**Cómo hacerlo:**
1. Localiza la invitación en la tabla
2. Click en el icono de **reenvío** (flecha)
3. El sistema:
   - ✅ Genera un nuevo token
   - ✅ Extiende la expiración +7 días
   - ✅ Envía un nuevo email

**Límites:**
- Máximo 3 reenvíos por invitación
- El contador de reenvíos se muestra en la UI

### Cancelar Invitación

**Cuándo usarlo:**
- El usuario ya no se unirá a la organización
- Enviaste la invitación por error
- El email era incorrecto

**Cómo hacerlo:**
1. Click en el icono **X** (cancelar)
2. La invitación se elimina inmediatamente
3. El link de invitación queda invalidado

---

## ✏️ Editar Usuarios Existentes

### Cómo Editar

1. Localiza el usuario en la tabla
2. Click en el icono de **edición** (lápiz)
3. Modifica los campos necesarios:
   - Nombre completo
   - Email
   - Role

4. Click en **"Update User"**

### Restricciones

⚠️ **No puedes cambiar tu propio role**
- Protección para evitar quedar sin acceso admin
- Pide a otro administrador que lo haga si es necesario

---

## 🔒 Desactivar Usuarios

**Desactivar** es una acción reversible que **impide el acceso** sin eliminar al usuario.

### Cuándo Desactivar

- ✅ Empleado en licencia temporal
- ✅ Usuario que no debe acceder temporalmente
- ✅ Suspensión disciplinaria
- ✅ Usuario inactivo que puede volver

### Cómo Desactivar

1. Localiza el usuario en la tabla
2. Click en el icono de **desactivación** (prohibido)
3. El usuario:
   - ❌ No podrá hacer login
   - ✅ Sus datos se mantienen en el sistema
   - ✅ Su historial de actividad se conserva

### Cómo Reactivar

1. El mismo icono cambia a **reactivación** (check)
2. Click para reactivar
3. El usuario puede volver a acceder inmediatamente

### Restricciones

⚠️ **No puedes desactivarte a ti mismo**
- Protección para evitar quedar sin acceso

---

## 🗑️ Eliminar Usuarios

**Eliminar** es una acción que marca al usuario como eliminado (soft delete).

### ⚠️ Advertencia

Esta acción **no puede deshacerse** fácilmente. Considera usar **Desactivar** si hay posibilidad de que el usuario vuelva.

### Cuándo Eliminar

- ✅ Empleado que dejó la empresa permanentemente
- ✅ Usuario creado por error
- ✅ Cuenta duplicada
- ✅ Limpieza de usuarios obsoletos

### Cómo Eliminar

1. Localiza el usuario en la tabla
2. Click en el icono de **eliminación** (papelera)
3. Se abrirá un modal de confirmación
4. **Lee el mensaje de advertencia**
5. Click en **"Delete"** para confirmar

### Validaciones Automáticas

El sistema **NO permitirá** eliminar usuarios si:

❌ **El usuario tiene procesos de firma activos**
- Debes completar o cancelar los procesos primero
- Mensaje: "Cannot delete user with active signing processes"

❌ **Intentas eliminarte a ti mismo**
- Protección de seguridad
- Mensaje: "You cannot delete your own account"

### Qué Sucede al Eliminar

- ✅ Usuario marcado como `deleted` (soft delete)
- ✅ No puede hacer login
- ✅ Su historial se conserva para audit trail
- ✅ Sus procesos completados permanecen visibles
- ✅ Su email queda disponible para una nueva invitación

---

## 🔍 Búsqueda y Filtros

### Búsqueda de Texto

Usa la barra de búsqueda para encontrar usuarios por:
- **Nombre**: "Juan"
- **Email**: "juan@empresa.com"

La búsqueda es **en tiempo real** (live search).

### Filtros

**Por Role:**
- All Roles
- Administrator
- Operator
- Viewer

**Por Status:**
- All Statuses
- Active
- Inactive
- Invited

### Limpiar Filtros

Click en **"Clear Filters"** para resetear búsqueda y filtros.

---

## 📊 Información de la Tabla de Usuarios

La tabla muestra:

### Avatar
- Inicial del nombre en círculo con gradiente

### Nombre y Email
- Nombre completo del usuario
- Email de contacto
- Etiqueta **(You)** si eres tú mismo

### Role Badge
- 🔴 Rojo: Administrator
- 🔵 Azul: Operator
- ⚫ Gris: Viewer

### Status Badge
- 🟢 Verde: Active
- ⚫ Gris: Inactive
- 🟡 Amarillo: Invited (pendiente de aceptar invitación)

### Last Login
- Timestamp relativo: "2 hours ago", "3 days ago", "Never"
- Indica actividad del usuario

### Acciones
- 🖊️ **Edit**: Modificar información
- 🔒 **Deactivate/Activate**: Cambiar estado
- 🗑️ **Delete**: Eliminar usuario

---

## 🎯 Mejores Prácticas

### Seguridad

1. **Principio de mínimo privilegio**
   - Asigna el rol más bajo necesario para cada usuario
   - No todos necesitan ser Administrator

2. **Revisión periódica**
   - Revisa la lista de usuarios mensualmente
   - Desactiva usuarios que ya no necesitan acceso
   - Elimina cuentas obsoletas

3. **Invitaciones**
   - Verifica el email antes de enviar
   - Usa mensajes personalizados para contexto
   - Reenvía solo si es necesario (max 3 veces)

### Gestión de Roles

**Administrator:**
- Solo para personal de confianza
- Mínimo 2 admins por organización (redundancia)
- Máximo 5 admins (seguridad)

**Operator:**
- Personal que necesita crear procesos diariamente
- Puede ser la mayoría del equipo

**Viewer:**
- Usuarios externos o temporales
- Personal que solo firma ocasionalmente

### Onboarding de Usuarios

1. **Planifica roles antes de invitar**
2. **Envía invitación con mensaje personalizado**
3. **Confirma que aceptaron la invitación**
4. **Proporciona capacitación básica**
5. **Monitorea su primer uso**

---

## ❓ Preguntas Frecuentes (FAQ)

### ¿Cuántos usuarios puedo tener?

Depende de tu plan:
- **Free**: 3 usuarios
- **Basic**: 10 usuarios
- **Pro**: 50 usuarios
- **Enterprise**: Ilimitado

Consulta con el superadmin si necesitas más usuarios.

### ¿Qué pasa si una invitación expira?

- Las invitaciones expiran después de **7 días**
- Puedes **reenviar** la invitación (genera nuevo token con 7 días más)
- Máximo 3 reenvíos por invitación
- Si llegas al límite, cancela y crea una nueva invitación

### ¿Puedo cambiar el email de un usuario?

Sí, puedes editar el email de cualquier usuario excepto el tuyo propio (requiere otro admin).

**Importante:**
- El usuario debe verificar el nuevo email
- Su sesión activa se mantendrá
- Recibirá notificaciones en el nuevo email

### ¿Qué pasa con los documentos de un usuario eliminado?

Los documentos y procesos **se conservan**:
- ✅ Procesos completados permanecen visibles
- ✅ Documentos firmados están disponibles
- ✅ Audit trail se mantiene intacto
- ✅ El historial es inmutable

El nombre del usuario eliminado aparece en el historial.

### ¿Puedo recuperar un usuario eliminado?

Técnicamente es un **soft delete**, pero requiere intervención técnica. Es mejor:
- Usar **Desactivar** si hay posibilidad de retorno
- **Eliminar** solo si es definitivo

### ¿Cuántas veces se puede reenviar una invitación?

Máximo **3 reenvíos** por invitación. Cada reenvío:
- Genera un nuevo token seguro
- Extiende la expiración +7 días
- Invalida el token anterior

Si llegas al límite, cancela y crea una nueva invitación.

### ¿Puedo tener múltiples administradores?

**Sí, es altamente recomendado**:
- Mínimo **2 administradores** por organización
- Redundancia en caso de ausencia
- Seguridad (admin no puede eliminarse a sí mismo)

### ¿Qué pasa si desactivo al único admin?

❌ **No puedes**. El sistema previene:
- Desactivarte a ti mismo
- Eliminarte a ti mismo
- Cambiar tu propio role a no-admin

Siempre debe haber al menos 1 admin activo.

### ¿Los usuarios reciben notificaciones de cambios?

Actualmente:
- ✅ Email al recibir invitación
- ✅ Email de bienvenida al aceptar
- ⏳ Notificaciones de cambio de role (próximamente)

---

## 🔐 Seguridad

### Passwords

Cuando un usuario acepta una invitación, debe crear un password que cumpla:

- ✅ Mínimo 8 caracteres
- ✅ Letras mayúsculas y minúsculas
- ✅ Al menos un número
- ✅ Al menos un símbolo especial

Ejemplo: `M1P@ssw0rd!`

### Tokens de Invitación

- **Longitud**: 64 caracteres
- **Generación**: Cryptographically secure (`Str::random(64)`)
- **Unicidad**: Validada en base de datos
- **Expiración**: 7 días automáticamente
- **Uso único**: No reutilizable

### Protecciones Automáticas

El sistema previene:
- ❌ Admin cambiando su propio role
- ❌ Admin desactivándose a sí mismo
- ❌ Admin eliminándose a sí mismo
- ❌ Eliminar usuarios con procesos activos
- ❌ Invitar emails duplicados
- ❌ Más de 3 reenvíos por invitación

---

## 📱 Flujo Completo de Onboarding

### Para el Administrador

```
1. Click "Invite User"
2. Completar formulario (email, nombre, role, mensaje)
3. Click "Send Invitation"
4. ✅ Invitación enviada
```

### Para el Usuario Invitado

```
1. Recibe email "You've been invited to join [Organización]"
2. Click "Accept Invitation"
3. Completa formulario de registro:
   - Crea password seguro
   - Confirma password
4. Click "Create Account & Join"
5. ✅ Login automático al sistema
6. Recibe email de bienvenida
```

**Tiempo total**: ~5 minutos

---

## 🎨 Interfaz de Usuario

### Panel Principal

**Elementos visuales:**
- 🔍 Barra de búsqueda en la parte superior
- 🎯 Botón "Invite User" destacado
- 📊 Filtros por role y status
- 📋 Tabla con todos los usuarios
- 📄 Sección de invitaciones pendientes

### Tabla de Usuarios

**Columnas:**
1. **User**: Avatar + Nombre + Email
2. **Role**: Badge con color
3. **Status**: Badge con estado actual
4. **Last Login**: Última actividad
5. **Actions**: Iconos de acciones

### Modals

**Invite User Modal:**
- Formulario limpio y claro
- Validaciones en tiempo real
- Botones: "Send Invitation" / "Cancel"

**Edit User Modal:**
- Pre-rellenado con datos actuales
- Campos editables
- Botones: "Update User" / "Cancel"

**Delete Confirmation Modal:**
- Advertencia clara
- Información del usuario a eliminar
- Botones: "Delete" (rojo) / "Cancel"

---

## 📈 Monitoreo y Auditoría

### Last Login

Monitorea la actividad de usuarios:
- **Recently**: Verde, activo
- **Days ago**: Usuario activo pero no reciente
- **Weeks ago**: Considera contactar o desactivar
- **Never**: Usuario no ha accedido (invited pero no aceptado)

### Invitaciones

Revisa el status:
- 🟡 **Pending**: Esperando aceptación
- 🟢 **Accepted**: Usuario creó su cuenta
- 🔴 **Expired**: Expiró, necesita reenvío

### Resend Count

Visible en invitaciones pendientes:
- `0/3`: Sin reenvíos
- `1/3`: 1 reenvío, quedan 2
- `3/3`: Máximo alcanzado, considera nueva invitación

---

## 🚨 Solución de Problemas

### "Email already exists"

**Problema:** Intentas invitar un email que ya está en uso.

**Solución:**
- Verifica en la tabla de usuarios si ya existe
- Si está inactivo, reactívalo en lugar de invitar
- Si está activo, usa otro email

### "Cannot delete user with active signing processes"

**Problema:** Usuario tiene procesos de firma pendientes o en progreso.

**Solución:**
1. Identifica los procesos activos del usuario
2. Opciones:
   - Esperar a que completen
   - Cancelar los procesos manualmente
   - Reasignar procesos a otro usuario (requiere superadmin)
3. Luego podrás eliminar al usuario

### "Invitation cannot be resent"

**Problema:** Llegaste al límite de 3 reenvíos.

**Solución:**
1. Cancela la invitación actual
2. Crea una nueva invitación con el mismo email
3. El usuario recibirá un nuevo link

### "This invitation is invalid or has expired"

**Problema:** El usuario intenta acceder pero el link no funciona.

**Solución:**
1. Verifica el status en "Pending Invitations"
2. Si está expirado: Reenvía la invitación
3. Si fue cancelado: Crea una nueva invitación
4. Si fue aceptado: El usuario ya tiene cuenta, debe hacer login

---

## 📞 Soporte

### Contacto

Si necesitas ayuda:

1. **Documentación técnica**: [`docs/`](../)
2. **Guía superadmin**: [`docs/admin/superadmin-guide.md`](superadmin-guide.md)
3. **Soporte técnico**: Contacta a tu superadmin

### Reportar Problemas

Si encuentras un bug o problema:
1. Documenta el error (screenshots)
2. Nota los pasos para reproducirlo
3. Contacta al equipo de soporte
4. Incluye: fecha, hora, email del usuario afectado

---

## ✅ Checklist del Administrador

### Configuración Inicial
- [ ] Revisa tu propio perfil de admin
- [ ] Invita al menos 1 admin adicional (redundancia)
- [ ] Invita a los usuarios principales de tu organización
- [ ] Verifica que los emails de invitación se entregan correctamente

### Mantenimiento Regular
- [ ] Revisa usuarios mensualment<br>
- [ ] Desactiva usuarios que ya no necesitan acceso
- [ ] Monitorea "Last Login" para detectar inactividad
- [ ] Limpia invitaciones expiradas no usadas

### Seguridad
- [ ] No compartas tu password de admin
- [ ] Usa passwords diferentes para cada servicio
- [ ] Habilita 2FA (two-factor authentication)
- [ ] Revisa el audit trail periódicamente

---

## 📚 Recursos Adicionales

### Documentación Relacionada

- [Guía Superadmin](superadmin-guide.md) - Para gestión de organizaciones
- [E0-002 Implementation Summary](../implementation/e0-002-user-management-summary.md) - Detalles técnicos
- [Sprint 6 Plan](../planning/sprint6-plan.md) - Contexto del proyecto

### Videos Tutorial (Próximamente)

- Cómo invitar un usuario
- Gestión de roles y permisos
- Mejores prácticas de seguridad

---

## 🎯 Resumen Rápido

### Invitar Usuario
```
Settings → Users → Invite User → Completar formulario → Send
```

### Editar Usuario
```
Tabla → Icono lápiz → Modificar campos → Update User
```

### Desactivar Usuario
```
Tabla → Icono prohibido → Usuario desactivado
```

### Eliminar Usuario
```
Tabla → Icono papelera → Confirmar → Delete
```

### Reenviar Invitación
```
Pending Invitations → Icono flecha → Nueva invitación enviada
```

---

**¡Listo!** Ya sabes cómo gestionar usuarios en tu organización Firmalum.

Si tienes dudas adicionales, consulta la documentación completa o contacta a soporte.

---

*Guía creada: 2025-12-30*  
*Versión: 1.0*  
*Para: Firmalum User Management (E0-002)*
