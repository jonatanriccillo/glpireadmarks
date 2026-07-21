<?php
/**
 * Endpoints de estado de lectura. Todo por POST: el kernel de GLPI 11 valida
 * el CSRF de los XHR contra el header X-Glpi-Csrf-Token, que el jQuery de
 * GLPI agrega solo. Respuestas JSON.
 */

include('../../../inc/includes.php');

Session::checkLoginUser();

header('Content-Type: application/json; charset=UTF-8');

$users_id = (int) Session::getLoginUserID();
$action   = $_POST['action'] ?? '';

/** Carga el ticket validando visibilidad, o corta con 403. */
$loadTicket = static function (int $tickets_id): Ticket {
    $ticket = new Ticket();
    if ($tickets_id <= 0 || !$ticket->getFromDB($tickets_id) || !$ticket->canViewItem()) {
        http_response_code(403);
        echo json_encode(['error' => 'forbidden']);
        exit;
    }
    return $ticket;
};

switch ($action) {
    case 'timeline':
        // Devuelve el set no leído Y sube la marca en el mismo request:
        // el separador se ve en esta visita y el ticket queda leído.
        $tickets_id = (int) ($_POST['id'] ?? 0);
        $loadTicket($tickets_id);
        $unread = PluginReadmarksMark::unreadSet($tickets_id, $users_id);
        PluginReadmarksMark::markRead($users_id, $tickets_id);
        echo json_encode([
            'unread'   => $unread,
            'reversed' => (($_SESSION['glpitimeline_order'] ?? '')
                           === CommonITILObject::TIMELINE_ORDER_REVERSE),
        ]);
        exit;

    case 'status':
        $ids = $_POST['ids'] ?? [];
        $ids = is_array($ids) ? array_slice(array_map('intval', $ids), 0, 500) : [];
        echo json_encode(['unread' => PluginReadmarksMark::unreadSubset($ids, $users_id)]);
        exit;

    case 'mark_unread':
        $tickets_id = (int) ($_POST['id'] ?? 0);
        $loadTicket($tickets_id);
        PluginReadmarksMark::markUnread($users_id, $tickets_id);
        echo json_encode(['ok' => true]);
        exit;

    case 'mark_read':
        $tickets_id = (int) ($_POST['id'] ?? 0);
        $loadTicket($tickets_id);
        PluginReadmarksMark::markRead($users_id, $tickets_id);
        echo json_encode(['ok' => true]);
        exit;
}

http_response_code(400);
echo json_encode(['error' => 'bad request']);
