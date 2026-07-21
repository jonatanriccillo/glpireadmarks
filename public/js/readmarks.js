/**
 * Readmarks: marca actividad no leída en el timeline del ticket y en la
 * lista de tickets.
 *
 * GLPI 11 carga tanto las filas de la lista como el CONTENIDO ENTERO del
 * ticket (tabs + timeline) por ajax después del document.ready — por eso
 * todo acá es "escanear el DOM actual" re-disparado en ajaxComplete, nunca
 * asumir que algo existe al cargar la página.
 */
(function ($) {
    'use strict';

    if (!$ || typeof CFG_GLPI === 'undefined') {
        return;
    }
    var AJAX = CFG_GLPI.root_doc + '/plugins/readmarks/ajax/marks.php';

    // ------------------------------------------------------------- timeline

    function resolveTicketId($tl) {
        // 1) URL clásica del form de ticket
        if (/ticket\.form\.php/.test(window.location.pathname)) {
            var m = window.location.search.match(/[?&]id=(\d+)/);
            if (m) {
                return parseInt(m[1], 10);
            }
        }
        // 2) forms de respuesta dentro del timeline (hidden itemtype+items_id)
        var $it = $tl.find('input[name="itemtype"][value="Ticket"]').first();
        if ($it.length) {
            var v = $it.closest('form').find('input[name="items_id"]').val();
            if (v) {
                return parseInt(v, 10);
            }
        }
        return 0;
    }

    function separatorEl() {
        return $('<div class="readmarks-separator" title="Actividad posterior a tu última lectura"><span>No leído</span></div>');
    }

    function entrySelector(u) {
        return '.timeline-item[data-itemtype="' + u.itemtype + '"][data-items-id="' + u.items_id + '"]';
    }

    function addUnreadButton($tl, id) {
        if ($tl.find('.readmarks-unread-btn').length) {
            return;
        }
        var $btn = $('<button type="button" class="btn btn-sm btn-ghost-secondary readmarks-unread-btn">' +
            '<i class="ti ti-mail me-1"></i>Marcar como no leído</button>');
        $btn.on('click', function () {
            $.post(AJAX, {action: 'mark_unread', id: id}, function () {
                $btn.prop('disabled', true)
                    .html('<i class="ti ti-mail-opened me-1"></i>Quedó como no leído');
            }, 'json');
        });
        $tl.prepend($('<div class="readmarks-actions w-100 d-flex justify-content-end mb-2"></div>').append($btn));
    }

    function decorateTimeline($tl, id, data) {
        addUnreadButton($tl, id);
        if (!data || !Array.isArray(data.unread) || !data.unread.length) {
            return;
        }
        var $anchor = null; // primer no leído (cronológico) presente en el DOM
        data.unread.forEach(function (u) {
            var $el = $tl.find(entrySelector(u));
            if ($el.length) {
                $el.addClass('readmarks-unread');
                if (!$anchor) {
                    $anchor = $el.first();
                }
            }
        });
        if ($anchor) {
            $tl.find('.readmarks-separator').remove();
            // Orden natural: la línea va ARRIBA del primer no leído.
            // Orden invertido (nuevo primero): va DEBAJO (borde del bloque).
            if (data.reversed) {
                separatorEl().insertAfter($anchor);
            } else {
                separatorEl().insertBefore($anchor);
            }
        }
    }

    function processTimelines() {
        $('.itil-timeline').each(function () {
            var $tl = $(this);
            if ($tl.data('readmarksDone')) {
                return; // ya procesado (el flag muere con el DOM si el tab recarga)
            }
            var id = resolveTicketId($tl);
            if (!id) {
                return;
            }
            $tl.data('readmarksDone', true);
            $.post(AJAX, {action: 'timeline', id: id}, function (data) {
                decorateTimeline($tl, id, data);
            }, 'json');
        });
    }

    // ---------------------------------------------------------------- lista

    function initList() {
        var map = {};
        $('input[type="checkbox"][name^="item[Ticket]["]').each(function () {
            var m = this.name.match(/^item\[Ticket\]\[(\d+)\]$/);
            if (m) {
                (map[m[1]] = map[m[1]] || []).push($(this).closest('tr'));
            }
        });
        var ids = Object.keys(map);
        if (!ids.length) {
            return;
        }
        $.post(AJAX, {action: 'status', ids: ids}, function (data) {
            if (!data || !Array.isArray(data.unread)) {
                return;
            }
            // Primero limpiar todo: lo que pasó a leído pierde la clase.
            Object.keys(map).forEach(function (id) {
                map[id].forEach(function ($tr) {
                    $tr.removeClass('readmarks-row-unread');
                });
            });
            data.unread.forEach(function (id) {
                (map[String(id)] || []).forEach(function ($tr) {
                    $tr.addClass('readmarks-row-unread');
                });
            });
        }, 'json');
    }

    // ------------------------------------------------------------- disparos

    var rescanTimer = null;
    $(document).ajaxComplete(function (_e, _xhr, settings) {
        if (settings && settings.url && settings.url.indexOf('readmarks') !== -1) {
            return; // no re-disparar por nuestros propios requests
        }
        clearTimeout(rescanTimer);
        rescanTimer = setTimeout(function () {
            initList();
            processTimelines();
        }, 400);
    });

    // Volver con "Atrás" restaura desde bfcache sin recargar: refrescar lista.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) {
            initList();
        }
    });

    $(function () {
        initList();
        processTimelines();
    });
})(window.jQuery);
