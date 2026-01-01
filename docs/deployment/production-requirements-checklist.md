# ✅ Checklist de Requerimientos para Deployment a Producción

> **Para**: Usuario/Cliente  
> **Objetivo**: Deployment ANCLA a Digital Ocean  
> **Fecha**: 2026-01-01

---

## 🎯 Resumen

Para realizar el deployment de ANCLA a producción en Digital Ocean, necesito que me proporciones la siguiente información. He organizado los requerimientos por prioridad.

---

## 🔴 CRÍTICO - Requerido Antes de Empezar

### 1. Acceso a Digital Ocean

```
☐ Personal Access Token de Digital Ocean
  → Generar en: Account → API → Generate New Token
  → Scope: Read & Write
  → Envíame el token de forma segura

☐ ¿Región preferida?
  Opciones recomendadas para España:
  - ams3 (Amsterdam) - RECOMENDADO
  - fra1 (Frankfurt)
  - lon1 (Londres)
  
☐ ¿Plan de Droplet?
  Recomendado para producción:
  - Basic 4GB RAM, 2 vCPUs, 80GB SSD (~$24/mes)
  O superior si esperas alto tráfico
```

### 2. Dominio

```
☐ ¿Cuál es tu dominio?
  Ejemplo: ancla.app, firma-electronica.es, etc.
  
☐ ¿Tienes acceso al panel DNS?
  Proveedor: Cloudflare / Route53 / Otro: __________
  
☐ ¿Puedes crear registros wildcard (*.dominio.com)?
  Necesario para multi-tenant
  ☐ Sí  ☐ No  ☐ No estoy seguro
```

### 3. Base de Datos

```
☐ ¿Qué prefieres?
  ☐ Managed Database de Digital Ocean (RECOMENDADO - ~$15/mes)
     - Backups automáticos
     - Alta disponibilidad
     - Sin mantenimiento
     
  ☐ MySQL en el mismo Droplet (Solo si presupuesto limitado)
     - Requiere mantenimiento manual
     - Sin backups automáticos nativos
```

### 4. Almacenamiento de Archivos

```
☐ ¿Qué servicio prefieres?
  ☐ Digital Ocean Spaces (RECOMENDADO - ~$5/mes por 250GB)
     - Compatible S3
     - CDN incluido
     
  ☐ AWS S3
     - Necesito credenciales AWS
```

### 5. Email

```
☐ ¿Qué servicio de email usarás?
  
  ☐ Amazon SES (RECOMENDADO para producción)
     Necesito:
     - AWS Access Key ID: __________
     - AWS Secret Access Key: __________
     - Región: __________
     - ¿Dominio verificado en SES? ☐ Sí ☐ No
     
  ☐ Mailgun / SendGrid / Postmark
     Necesito:
     - SMTP Host: __________
     - SMTP Port: __________
     - Username: __________
     - Password: __________
     
  ☐ Configurar después (usaré log driver temporalmente)
```

---

## 🟡 IMPORTANTE - Necesario para Funcionalidad Completa

### 6. Certificado de Firma Digital

```
☐ ¿Qué tipo de certificado quieres usar?
  
  ☐ Self-signed (temporal, para testing)
     - Lo genero automáticamente
     - NO válido para producción real
     - Gratis
     
  ☐ CA-issued (producción, RECOMENDADO para clientes reales)
     - DigiCert, GlobalSign, etc.
     - ¿Ya lo tienes? ☐ Sí ☐ No
     - Si sí, envíame: .crt y .key files
     - Si no, ¿quieres que te ayude a obtener uno? ☐ Sí ☐ No
```

### 7. Usuario Superadmin Inicial

```
☐ Email del super administrador:
  __________@__________
  
☐ Nombre completo:
  __________
  
☐ ¿Quieres que genere una contraseña temporal?
  ☐ Sí, genera una  
  ☐ No, usaré esta: __________ (cambiarás al primer login)
```

### 8. Configuración de Backup

```
☐ ¿Cuántos días de retención de backups?
  Recomendado: 30 días
  Tu preferencia: __________ días
  
☐ ¿Horario de backup automático?
  Recomendado: 2:00 AM (horario del servidor)
  Tu preferencia: __________ AM/PM
```

---

## 🟢 OPCIONAL - Mejoras de Seguridad y Monitoreo

### 9. Monitoreo de Errores

```
☐ ¿Quieres integrar Sentry (error tracking)?
  ☐ Sí - necesito tu Sentry DSN
  ☐ No - usaré logs locales
  
☐ ¿Tienes cuenta Sentry?
  ☐ Sí - DSN: __________
  ☐ No - ¿crear cuenta gratuita? ☐ Sí ☐ No
```

### 10. Notificaciones

```
☐ ¿Email para alertas del sistema?
  __________@__________
  (CPU alto, errores críticos, espacio bajo, etc.)
```

### 11. Tenants Iniciales

```
☐ ¿Quieres que cree organizaciones (tenants) iniciales?
  ☐ No, las crearé manualmente después
  ☐ Sí, crear estas:
  
  Tenant 1:
  - Nombre: __________
  - Subdominio: __________
  - Email admin: __________
  - Plan: ☐ Trial ☐ Basic ☐ Professional
  
  Tenant 2:
  - Nombre: __________
  - Subdominio: __________
  - Email admin: __________
  - Plan: ☐ Trial ☐ Basic ☐ Professional
```

### 12. Extras

```
☐ ¿Necesitas Load Balancer? (para alta disponibilidad)
  ☐ No (un solo servidor es suficiente)
  ☐ Sí (múltiples servidores + load balancer ~$10/mes extra)
  
☐ ¿Habilitar CDN para archivos estáticos?
  ☐ Sí (incluido en Spaces)
  ☐ No necesario por ahora
  
☐ ¿Snapshots automáticos del servidor?
  ☐ Sí (backups semanales del Droplet ~$2.40/mes)
  ☐ No necesario
```

---

## 📋 Información que YO proporcionaré DESPUÉS del Deployment

Una vez completado el deployment, te entregaré:

✅ **Accesos:**
- URL de acceso: https://tu-dominio.com
- Credenciales superadmin
- SSH access al servidor (si lo necesitas)

✅ **Documentación:**
- Guía de uso para superadmin
- Guía de gestión de usuarios
- Guía de creación de tenants
- Procedimientos de backup y restauración

✅ **Configuración DNS:**
- Registros exactos a añadir en tu DNS:
  - A record para dominio principal
  - CNAME wildcard para subdominos
  - TXT records para email (SPF, DKIM, DMARC)
  - TXT records para SSL (si usa DNS challenge)

✅ **Scripts de Mantenimiento:**
- Deploy script (para futuras actualizaciones)
- Backup script manual
- Health check script
- Log rotation config

✅ **Credenciales Encriptadas:**
- Archivo .gpg con todas las passwords y keys
- Instrucciones de uso del archivo encriptado

---

## 💰 Estimación de Costos Mensuales

### Configuración Básica (Recomendada)
```
Droplet Basic 4GB:        $24/mes
Managed Database 1GB:     $15/mes
Spaces 250GB:             $5/mes
Snapshots semanales:      $2.40/mes
─────────────────────────────────
TOTAL:                    ~$46/mes
```

### Configuración Premium (Alta Disponibilidad)
```
Load Balancer:            $10/mes
Droplets 2x Basic 4GB:    $48/mes
Managed Database 4GB:     $60/mes
Spaces 1TB + CDN:         $30/mes
Redis Managed 1GB:        $15/mes
Snapshots:                $4.80/mes
─────────────────────────────────
TOTAL:                    ~$168/mes
```

**NO incluidos en estimación:**
- Amazon SES: $0.10 por 1,000 emails (muy económico)
- Certificado CA-issued: $0-300/año (depende del proveedor)
- Dominio: variable según registrar

---

## 🚀 Próximos Pasos

1. **Completa este checklist** y envíamelo
2. **Proporciona las credenciales** de forma segura:
   - Puedes usar: 1Password, LastPass, PGP, o mensaje privado encriptado
3. **Confirma el presupuesto** según tus necesidades
4. **Yo procederé con**:
   - Provisioning del servidor
   - Configuración completa
   - Deployment de la aplicación
   - Tests de verificación
   - Entrega de documentación y credenciales

**Tiempo estimado de deployment**: 3-4 horas una vez tenga toda la información.

---

## 📞 Contacto

Si tienes dudas sobre algún punto:
- ¿No estás seguro qué elegir? → Te recomiendo la mejor opción
- ¿Necesitas ayuda con algún servicio? → Te guío paso a paso
- ¿Tienes restricciones de presupuesto? → Ajusto la configuración

---

## ✅ Checklist Rápido de Entrega

**Antes de enviarme la información, verifica:**

- [ ] He completado TODOS los puntos CRÍTICOS (1-5)
- [ ] He completado los puntos IMPORTANTES (6-8)
- [ ] He decidido qué OPCIONALES quiero (9-12)
- [ ] Tengo acceso a mi panel DNS para configurar registros
- [ ] Confirmo el presupuesto estimado
- [ ] He preparado las credenciales de forma segura

**Cuando todo esté listo, envíame este checklist completado.**

---

**Versión**: 1.0  
**Fecha**: 2026-01-01  
**Proyecto**: ANCLA - Firma Electrónica Avanzada  
**Estado**: MVP 100% Completo ✅ - Listo para Producción
