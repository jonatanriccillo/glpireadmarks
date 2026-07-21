<?php
/**
 * PluginReadmarksMark — estado de lectura por (usuario, ticket).
 *
 * Una fila por usuario+ticket con `last_read_date` (marcador por timestamp,
 * tipo Slack): un ticket está "no leído" si algún ítem visible de su timeline
 * es posterior a la marca del usuario (o si no hay marca y hay actividad).
 * Ítems privados de otros no cuentan (el usuario podría no verlos).
 */
class PluginReadmarksMark extends CommonDBTM
{
    public const SEARCH_OPTION_ID = 28950;

    /** Fecha "nunca leído" para comparar contra marcas ausentes. */
    public const EPOCH = '1970-01-01 00:00:00';

    /**
     * Tablas del timeline que cuentan como actividad. Las claves son los
     * mismos strings que usa el timeline en data-itemtype (verificado en
     * timeline.html.twig: anchor = entry['type'] ~ '_' ~ id).
     * 'fk': columna que apunta al ticket; 'typed': la tabla tiene columna
     * itemtype (= 'Ticket'); 'private': la tabla tiene is_private; 'id':
     * columna que expone el timeline como data-items-id (para Document_Item
     * es documents_id: getTimelineItems arma el entry con los fields del
     * Document, no del Document_Item); 'author': columna del autor — lo
     * escrito por uno mismo NUNCA cuenta como no leído para uno (semántica
     * de correo; además cubre lo creado ANTES de instalar el plugin, donde
     * el hook bumpOwnOnAdd no existió para marcarlo).
     */
    private const SOURCES = [
        // El ticket en sí es el primer "mensaje" del hilo: uno recién creado,
        // sin seguimientos, cuenta como no leído (su descripción renderiza en
        // el timeline como data-itemtype="Ticket").
        'Ticket'        => ['table' => 'glpi_tickets',         'fk' => 'id',         'typed' => false, 'private' => false, 'id' => 'id',           'author' => 'users_id_recipient'],
        'ITILFollowup'  => ['table' => 'glpi_itilfollowups',   'fk' => 'items_id',   'typed' => true,  'private' => true,  'id' => 'id',           'author' => 'users_id'],
        'TicketTask'    => ['table' => 'glpi_tickettasks',     'fk' => 'tickets_id', 'typed' => false, 'private' => true,  'id' => 'id',           'author' => 'users_id'],
        'ITILSolution'  => ['table' => 'glpi_itilsolutions',   'fk' => 'items_id',   'typed' => true,  'private' => false, 'id' => 'id',           'author' => 'users_id'],
        'Document_Item' => ['table' => 'glpi_documents_items', 'fk' => 'items_id',   'typed' => true,  'private' => false, 'id' => 'documents_id', 'author' => 'users_id'],
    ];

    /**
     * Filtros comunes de una fuente para un usuario: excluir lo autorado por
     * él (NULL-safe: autor desconocido sí cuenta) y lo privado de otros.
     */
    private static function sourceFilters(array $src, int $users_id): array
    {
        $filters = [];
        $filters[] = ['OR' => [
            [$src['author'] => ['<>', $users_id]],
            [$src['author'] => null],
        ]];
        if ($src['private']) {
            $filters['is_private'] = 0;
        }
        return $filters;
    }

    public static function canCreate(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    public static function canView(): bool
    {
        return (bool) Session::getLoginUserID();
    }

    // ------------------------------------------------------------------ marcas

    /** last_read_date del usuario para un ticket, o null si nunca lo leyó. */
    public static function getMarkDate(int $users_id, int $tickets_id): ?string
    {
        global $DB;

        $it = $DB->request([
            'SELECT' => ['last_read_date'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'users_id' => $users_id,
                'itemtype' => 'Ticket',
                'items_id' => $tickets_id,
            ],
        ]);
        foreach ($it as $row) {
            return $row['last_read_date'];
        }
        return null;
    }

    /**
     * Marca el ticket como leído "hasta ahora". Monotónico: nunca retrocede
     * una marca existente (GREATEST), así un hook tardío no des-lee nada.
     */
    public static function markRead(int $users_id, int $tickets_id): void
    {
        global $DB;

        $table = self::getTable();
        $DB->doQuery(
            "INSERT INTO `$table` (`users_id`, `itemtype`, `items_id`, `last_read_date`, `date_mod`)
             VALUES ($users_id, 'Ticket', $tickets_id, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               `last_read_date` = GREATEST(COALESCE(`last_read_date`, '" . self::EPOCH . "'), VALUES(`last_read_date`)),
               `date_mod` = NOW()"
        );
    }

    /** Vuelve el ticket a no leído para el usuario (borra su marca). */
    public static function markUnread(int $users_id, int $tickets_id): void
    {
        global $DB;

        $DB->delete(self::getTable(), [
            'users_id' => $users_id,
            'itemtype' => 'Ticket',
            'items_id' => $tickets_id,
        ]);
    }

    /**
     * Hook item_add (ITILFollowup / TicketTask / ITILSolution / Document_Item):
     * si hay usuario de sesión, agregó el ítem desde la UI con el ticket a la
     * vista → subir SU marca. Sin sesión (mail collector, cron, API) no hace
     * nada.
     */
    public static function bumpOwnOnAdd(CommonDBTM $item): void
    {
        $users_id = (int) Session::getLoginUserID();
        if ($users_id <= 0) {
            return;
        }
        $tickets_id = self::resolveTicketId($item);
        if ($tickets_id > 0) {
            self::markRead($users_id, $tickets_id);
        }
    }

    private static function resolveTicketId(CommonDBTM $item): int
    {
        if ($item instanceof Ticket) {
            return (int) ($item->fields['id'] ?? 0);
        }
        if ($item instanceof TicketTask) {
            return (int) ($item->fields['tickets_id'] ?? 0);
        }
        if (($item->fields['itemtype'] ?? '') === 'Ticket') {
            return (int) ($item->fields['items_id'] ?? 0);
        }
        return 0;
    }

    // ------------------------------------------------------------ estado batch

    /**
     * Subconjunto de $tickets_ids con actividad no leída para el usuario.
     * Una query por tabla fuente + una por marcas, sin importar cuántos IDs.
     *
     * @param int[] $tickets_ids
     * @return int[]
     */
    public static function unreadSubset(array $tickets_ids, int $users_id): array
    {
        global $DB;

        $tickets_ids = array_values(array_filter(array_map('intval', $tickets_ids)));
        if ($tickets_ids === []) {
            return [];
        }

        $marks = [];
        $it = $DB->request([
            'SELECT' => ['items_id', 'last_read_date'],
            'FROM'   => self::getTable(),
            'WHERE'  => [
                'users_id' => $users_id,
                'itemtype' => 'Ticket',
                'items_id' => $tickets_ids,
            ],
        ]);
        foreach ($it as $row) {
            $marks[(int) $row['items_id']] = $row['last_read_date'] ?? self::EPOCH;
        }

        $last = [];
        foreach (self::SOURCES as $src) {
            $where = [$src['fk'] => $tickets_ids];
            if ($src['typed']) {
                $where['itemtype'] = 'Ticket';
            }
            $where = array_merge($where, self::sourceFilters($src, $users_id));
            $it = $DB->request([
                'SELECT'  => [$src['fk'] . ' AS tid', 'MAX' => 'date_creation AS last_date'],
                'FROM'    => $src['table'],
                'WHERE'   => $where,
                'GROUPBY' => $src['fk'],
            ]);
            foreach ($it as $row) {
                $tid = (int) $row['tid'];
                if (!isset($last[$tid]) || $row['last_date'] > $last[$tid]) {
                    $last[$tid] = $row['last_date'];
                }
            }
        }

        $unread = [];
        foreach ($last as $tid => $last_date) {
            if ($last_date > ($marks[$tid] ?? self::EPOCH)) {
                $unread[] = $tid;
            }
        }
        return $unread;
    }

    /**
     * Ítems no leídos del timeline de UN ticket, orden cronológico asc.
     *
     * @return array<int, array{itemtype: string, items_id: int, date: string}>
     */
    public static function unreadSet(int $tickets_id, int $users_id): array
    {
        global $DB;

        $mark = self::getMarkDate($users_id, $tickets_id) ?? self::EPOCH;

        $items = [];
        foreach (self::SOURCES as $type => $src) {
            $where = [
                $src['fk']      => $tickets_id,
                'date_creation' => ['>', $mark],
            ];
            if ($src['typed']) {
                $where['itemtype'] = 'Ticket';
            }
            $where = array_merge($where, self::sourceFilters($src, $users_id));
            $it = $DB->request([
                'SELECT' => [$src['id'] . ' AS exposed_id', 'date_creation'],
                'FROM'   => $src['table'],
                'WHERE'  => $where,
            ]);
            foreach ($it as $row) {
                $items[] = [
                    'itemtype' => $type,
                    'items_id' => (int) $row['exposed_id'],
                    'date'     => $row['date_creation'],
                ];
            }
        }

        usort($items, static fn ($a, $b) => strcmp($a['date'], $b['date']));
        return $items;
    }

    /**
     * Condición SQL "el ticket referenciado por $tickets_id_ref tiene
     * actividad no leída para el usuario de sesión". Para el addWhere de la
     * SearchOption (correlacionada, sin joins).
     */
    public static function unreadSqlCondition(string $tickets_id_ref): string
    {
        $uid   = (int) Session::getLoginUserID();
        $epoch = self::EPOCH;
        $mark  = "COALESCE((SELECT `rm`.`last_read_date` FROM `glpi_plugin_readmarks_marks` `rm`
                   WHERE `rm`.`users_id` = $uid AND `rm`.`itemtype` = 'Ticket'
                     AND `rm`.`items_id` = $tickets_id_ref), '$epoch')";

        $exists = [];
        foreach (self::SOURCES as $src) {
            $conds = ["`t`.`{$src['fk']}` = $tickets_id_ref", "`t`.`date_creation` > $mark"];
            if ($src['typed']) {
                $conds[] = "`t`.`itemtype` = 'Ticket'";
            }
            $conds[] = "(`t`.`{$src['author']}` IS NULL OR `t`.`{$src['author']}` <> $uid)";
            if ($src['private']) {
                $conds[] = "`t`.`is_private` = 0";
            }
            $exists[] = 'EXISTS (SELECT 1 FROM `' . $src['table'] . '` `t` WHERE ' . implode(' AND ', $conds) . ')';
        }
        return '(' . implode(' OR ', $exists) . ')';
    }

    // -------------------------------------------------------- acciones masivas

    public static function showMassiveActionsSubForm(MassiveAction $ma): bool
    {
        switch ($ma->getAction()) {
            case 'markread':
            case 'markunread':
                echo Html::submit(__('Aplicar', 'readmarks'), ['name' => 'massiveaction']);
                return true;
        }
        return parent::showMassiveActionsSubForm($ma);
    }

    public static function processMassiveActionsForOneItemtype(
        MassiveAction $ma,
        CommonDBTM $item,
        array $ids
    ): void {
        $action   = $ma->getAction();
        $users_id = (int) Session::getLoginUserID();

        if (!$item instanceof Ticket || $users_id <= 0
            || !in_array($action, ['markread', 'markunread'], true)) {
            parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
            return;
        }

        foreach ($ids as $id) {
            if (!$item->getFromDB($id) || !$item->canViewItem()) {
                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                continue;
            }
            if ($action === 'markread') {
                self::markRead($users_id, (int) $id);
            } else {
                self::markUnread($users_id, (int) $id);
            }
            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
        }
    }
}
