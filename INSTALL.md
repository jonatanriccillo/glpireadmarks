# Instalación — readmarks

## Requisitos

- GLPI 11.0.x (desarrollado y probado contra 11.0.8).
- Nada más: sin dependencias, sin config.

## Instalación

1. Descargá o cloná este repositorio dentro de la carpeta `plugins/` de
   tu instalación de GLPI, de forma que quede en `plugins/readmarks/`.
2. Entrá a **Configuración > Complementos**.
3. Buscá "Readmarks" en la lista, hacé click en **Instalar** y después
   en **Activar**.

## Verificación rápida

1. Con otro usuario logueado, que alguien (o el mail collector) agregue
   un seguimiento a un ticket.
2. En la lista de tickets de ese usuario, el ticket aparece en negrita.
3. Al abrirlo: línea roja "No leído" sobre la actividad nueva.
4. Vuelve a la lista: ya no está en negrita.
5. Botón "Marcar como no leído" en el timeline lo devuelve a negrita.
6. En el buscador de tickets, el criterio "Actividad no leída = Sí"
   filtra exactamente los tickets en negrita.

## Desinstalar

Desactivar y desinstalar desde **Configuración > Complementos**. Borra
la tabla `glpi_plugin_readmarks_marks`; no toca nada más.
