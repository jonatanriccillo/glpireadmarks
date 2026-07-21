<?php
/**
 * Install / uninstall + search & massive-action hooks for Readmarks.
 */

function plugin_readmarks_install(): bool
{
    global $DB;

    if (!$DB->tableExists('glpi_plugin_readmarks_marks')) {
        $DB->runFile(Plugin::getPhpDir('readmarks') . '/sql/empty-1.0.0.sql');
    }
    return true;
}

function plugin_readmarks_uninstall(): bool
{
    global $DB;

    if ($DB->tableExists('glpi_plugin_readmarks_marks')) {
        $DB->doQuery('DROP TABLE `glpi_plugin_readmarks_marks`');
    }
    return true;
}

/**
 * SearchOption "Actividad no leída": sirve como criterio de búsqueda
 * (Sí/No), como columna visible (Sí/No vía giveItem) y para ordenar
 * (no leídos primero/último). La tabla empieza con glpi_plugin_readmarks,
 * así SQLProvider delega WHERE/SELECT/ORDER/render a los hooks del plugin.
 */
function plugin_readmarks_getAddSearchOptionsNew($itemtype): array
{
    if ($itemtype !== 'Ticket') {
        return [];
    }
    return [
        [
            'id'            => PluginReadmarksMark::SEARCH_OPTION_ID,
            'table'         => 'glpi_plugin_readmarks_marks',
            'field'         => 'last_read_date',
            'name'          => __('Actividad no leída', 'readmarks'),
            'datatype'      => 'bool',
            'massiveaction' => false,
        ],
    ];
}

/** Valor de la columna: la condición de no-leído como booleano 0/1. */
function plugin_readmarks_addSelect($itemtype, $ID, $name)
{
    if ($itemtype !== 'Ticket' || (int) $ID !== PluginReadmarksMark::SEARCH_OPTION_ID) {
        return '';
    }
    $cond = PluginReadmarksMark::unreadSqlCondition('glpi_tickets.id');
    return "$cond AS `ITEM_$name`";
}

/**
 * Orden por la expresión completa (no por el alias del SELECT: si se
 * ordena sin mostrar la columna, el alias no existe en la query).
 */
function plugin_readmarks_addOrderBy($itemtype, $ID, $order, $name)
{
    if ($itemtype !== 'Ticket' || (int) $ID !== PluginReadmarksMark::SEARCH_OPTION_ID) {
        return '';
    }
    $cond = PluginReadmarksMark::unreadSqlCondition('glpi_tickets.id');
    return " $cond $order ";
}

/** Render de la celda: Sí (no leído) / No. */
function plugin_readmarks_giveItem($itemtype, $ID, $data, $num)
{
    if ($itemtype !== 'Ticket' || (int) $ID !== PluginReadmarksMark::SEARCH_OPTION_ID) {
        return '';
    }
    $val = (int) ($data[$num][0]['name'] ?? 0);
    return $val === 1
        ? '<span class="fw-bold text-primary">Sí</span>'
        : '<span class="text-secondary">No</span>';
}

/**
 * SQLProvider intenta un LEFT JOIN por convención de FK
 * (glpi_tickets.plugin_readmarks_marks_id, que no existe) al ver la tabla
 * del plugin en la SearchOption. Este hook lo reemplaza por el join real:
 * la marca del usuario de sesión para cada ticket (a lo sumo una fila por
 * la unicity users+itemtype+items_id, no duplica resultados).
 */
function plugin_readmarks_addLeftJoin($itemtype, $ref_table, $new_table, $linkfield, &$already_link_tables)
{
    if ($new_table !== 'glpi_plugin_readmarks_marks' || $itemtype !== 'Ticket') {
        return '';
    }
    $uid = (int) Session::getLoginUserID();
    // En UNA sola línea: parseJoinString() parsea el ON con un regex sin /s
    // y un salto de línea deja el paréntesis sin cerrar (SQL roto).
    return " LEFT JOIN `glpi_plugin_readmarks_marks` ON (`glpi_plugin_readmarks_marks`.`itemtype` = 'Ticket' AND `glpi_plugin_readmarks_marks`.`items_id` = `glpi_tickets`.`id` AND `glpi_plugin_readmarks_marks`.`users_id` = $uid) ";
}

function plugin_readmarks_addWhere($link, $nott, $itemtype, $ID, $val, $searchtype)
{
    if ($itemtype !== 'Ticket' || (int) $ID !== PluginReadmarksMark::SEARCH_OPTION_ID) {
        return '';
    }
    $condition = PluginReadmarksMark::unreadSqlCondition('glpi_tickets.id');
    // datatype bool: $val 1 = sí (no leído), 0 = no. $nott invierte.
    $want_unread = ((int) $val === 1) xor (bool) $nott;
    return $want_unread ? $condition : "NOT $condition";
}

function plugin_readmarks_MassiveActions($itemtype): array
{
    if ($itemtype !== 'Ticket' || !Session::getLoginUserID()) {
        return [];
    }
    $sep = MassiveAction::CLASS_ACTION_SEPARATOR;
    return [
        'PluginReadmarksMark' . $sep . 'markread'   => __('Marcar como leído', 'readmarks'),
        'PluginReadmarksMark' . $sep . 'markunread' => __('Marcar como no leído', 'readmarks'),
    ];
}
