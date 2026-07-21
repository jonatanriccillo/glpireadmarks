<?php
/**
 * Readmarks plugin for GLPI 11.
 *
 * Per-user read state for ticket activity, mail-client style: unread
 * tickets show bold + tinted in the ticket list, and the ticket timeline
 * highlights unread entries with a "No leído" separator. Opening a ticket
 * marks it read; a button / massive action marks it unread again.
 *
 * Licensed under GPLv3.
 */

define('PLUGIN_READMARKS_VERSION', '1.0.0');
define('PLUGIN_READMARKS_MIN_GLPI', '11.0.0');
define('PLUGIN_READMARKS_MAX_GLPI', '11.9.99');

function plugin_init_readmarks(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['readmarks'] = true;

    // Registered without a login guard so mail collector / API / cron adds
    // don't break: bumpOwnOnAdd() exits early when there is no session user.
    $PLUGIN_HOOKS['item_add']['readmarks'] = [
        'Ticket'        => ['PluginReadmarksMark', 'bumpOwnOnAdd'],
        'ITILFollowup'  => ['PluginReadmarksMark', 'bumpOwnOnAdd'],
        'TicketTask'    => ['PluginReadmarksMark', 'bumpOwnOnAdd'],
        'ITILSolution'  => ['PluginReadmarksMark', 'bumpOwnOnAdd'],
        'Document_Item' => ['PluginReadmarksMark', 'bumpOwnOnAdd'],
    ];

    if (!Session::getLoginUserID()) {
        return;
    }

    $PLUGIN_HOOKS['use_massive_action']['readmarks'] = 1;
    $PLUGIN_HOOKS['add_javascript']['readmarks']     = 'js/readmarks.js';
    $PLUGIN_HOOKS['add_css']['readmarks']            = 'css/readmarks.css';
}

function plugin_version_readmarks(): array
{
    return [
        'name'         => 'Readmarks',
        'version'      => PLUGIN_READMARKS_VERSION,
        'author'       => 'Jonatan Riccillo',
        'license'      => 'GPLv3',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_READMARKS_MIN_GLPI,
                'max' => PLUGIN_READMARKS_MAX_GLPI,
            ],
        ],
    ];
}

function plugin_readmarks_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_READMARKS_MIN_GLPI, 'lt')) {
        if (method_exists('Plugin', 'messageIncompatible')) {
            echo Plugin::messageIncompatible('core', PLUGIN_READMARKS_MIN_GLPI);
        }
        return false;
    }
    return true;
}

function plugin_readmarks_check_config($verbose = false): bool
{
    return true;
}
