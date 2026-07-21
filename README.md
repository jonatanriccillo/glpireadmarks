# Readmarks — no leídos por usuario en tickets (GLPI 11)

Marca la actividad "no leída" de tickets estilo cliente de correo, por
usuario:

- **Lista de tickets**: los tickets con actividad que no viste aparecen
  en **negrita con fondo tintado** y un acento en el borde izquierdo.
- **Timeline del ticket**: las entradas nuevas quedan resaltadas y una
  línea roja **"No leído"** marca desde dónde no leíste (estilo
  Slack/Teams). Respeta el orden natural o invertido del timeline.
- **Abrir el ticket lo marca leído** automáticamente (el separador se ve
  durante esa visita). Botón **"Marcar como no leído"** para volver
  atrás, más acciones masivas **"Marcar como leído / no leído"** en la
  lista.
- **Filtro, columna y orden**: criterio "Actividad no leída" (Sí/No) en
  el buscador de tickets; columna visible con Sí/No que se puede
  **ordenar** clickeando el encabezado (no leídos primero/último); y una
  **búsqueda guardada pública "Tickets no leídos"** (un click desde el
  panel de búsquedas guardadas, ordenada por última actualización).

Cuenta como actividad: seguimientos, tareas, soluciones y documentos.
Los ítems privados de otros no cuentan. Lo que agregás vos no te marca
el ticket como no leído (a los demás sí). Todo el estado es individual
por usuario; nada se comparte entre técnicos.

## Cómo funciona

Una sola tabla (`glpi_plugin_readmarks_marks`) con una fila por
(usuario, ticket): `last_read_date`, un marcador por timestamp. Un
ticket está no leído si algún ítem del timeline es posterior a tu marca
(o si nunca lo abriste y tiene actividad). Sin cron, sin cola: dos
endpoints ajax y hooks de `item_add`.

Un usuario nuevo ve como no leído todo ticket con actividad histórica:
seleccioná todo en la lista y usá la masiva "Marcar como leído" para
arrancar de cero.

## Requisitos

GLPI 11.0.x. Sin dependencias externas, sin configuración: se instala,
se activa y funciona para todos los usuarios logueados.

Ver `INSTALL.md` para la instalación.
