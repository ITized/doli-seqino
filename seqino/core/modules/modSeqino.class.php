<?php
/* Copyright (C) 2026 ITized
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

if (!defined('NOREQUIREUSER')) {
    define('NOREQUIREUSER', '1');
}
if (!defined('NOREQUIREDB')) {
    define('NOREQUIREDB', '1');
}
if (!defined('NOREQUIRESOC')) {
    define('NOREQUIRESOC', '1');
}
if (!defined('NOREQUIRETRAN')) {
    define('NOREQUIRETRAN', '1');
}
if (!defined('NOREQUIREMENU')) {
    define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
    define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
    define('NOREQUIREAJAX', '1');
}

require_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module Seqino.
 */
class modSeqino extends DolibarrModules
{
    /**
     * Constructor.
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;

        $this->numero = 185212;
        $this->rights_class = 'seqino';
        $this->family = 'financial';
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        $this->description = 'Electronic invoicing integration with Seqino PDP';
        $this->descriptionlong = 'Open-source V1 for outbound, inbound and e-reporting synchronization with queue + cron workers.';
        $this->editor_name = 'ITized';
        $this->editor_url = 'https://itized.fr';
        $this->version = '1.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->picto = 'generic';

        $this->module_parts = array(
            'hooks' => array('invoicecard', 'supplierinvoicecard', 'cron'),
            'triggers' => 1,
            'css' => array(),
            'js' => array(),
        );

        $this->dirs = array('/seqino/temp');
        $this->config_page_url = array('setup.php@seqino');

        $this->hidden = false;
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        $this->phpmin = array(8, 1);
        $this->need_dolibarr_version = array(18, 0);
        $this->langfiles = array('seqino@seqino');

        $this->const = array(
            0 => array('SEQINO_ENVIRONMENT', 'chaine', 'sandbox', 'Environment selector (sandbox|production)', 0, 'current', $conf->entity),
            1 => array('SEQINO_API_BASE_URL_SANDBOX', 'chaine', 'https://pdp-sandbox.seqino.dev', 'Seqino sandbox API base URL', 0, 'current', $conf->entity),
            2 => array('SEQINO_API_BASE_URL_PRODUCTION', 'chaine', 'https://pdp-api.seqino.dev', 'Seqino production API base URL', 0, 'current', $conf->entity),
            3 => array('SEQINO_API_TOKEN', 'chaine', '', 'Seqino PDP API token', 0, 'current', $conf->entity),
            4 => array('SEQINO_API_TIMEOUT', 'integer', '30', 'Seqino API timeout in seconds', 0, 'current', $conf->entity),
            5 => array('SEQINO_CRON_BATCH_SIZE', 'integer', '50', 'Max queue rows processed per cron run', 0, 'current', $conf->entity),
            6 => array('SEQINO_TOKEN_USED_COUNT', 'integer', '0', '1 token = 1 payload accounting usage counter', 0, 'current', $conf->entity),
            7 => array('SEQINO_TOKEN_AVAILABLE_COUNT', 'integer', '0', 'Token quota snapshot from provider', 0, 'current', $conf->entity),
        );

        $this->tabs = array();
        $this->dictionaries = array();
        $this->boxes = array();

        $this->rights = array();
        $r = 0;
        $this->rights[$r][0] = 18521201;
        $this->rights[$r][1] = 'Read Seqino synchronization status';
        $this->rights[$r][4] = 'read';

        $r++;
        $this->rights[$r][0] = 18521202;
        $this->rights[$r][1] = 'Manage Seqino settings and queue';
        $this->rights[$r][4] = 'write';

        $this->cronjobs = array(
            0 => array(
                'label' => 'Seqino outbound invoice sync',
                'jobtype' => 'method',
                'class' => '/seqino/class/SeqinoCronJobs.class.php',
                'objectname' => 'SeqinoCronJobs',
                'method' => 'runOutbound',
                'parameters' => '',
                'comment' => 'Queue-based outbound invoice sync to Seqino PDP',
                'frequency' => 5,
                'unitfrequency' => 60,
                'status' => 1,
                'test' => '$conf->seqino->enabled',
            ),
            1 => array(
                'label' => 'Seqino inbound invoice sync',
                'jobtype' => 'method',
                'class' => '/seqino/class/SeqinoCronJobs.class.php',
                'objectname' => 'SeqinoCronJobs',
                'method' => 'runInbound',
                'parameters' => '',
                'comment' => 'Queue-based inbound vendor invoice draft sync from Seqino PDP',
                'frequency' => 5,
                'unitfrequency' => 60,
                'status' => 1,
                'test' => '$conf->seqino->enabled',
            ),
            2 => array(
                'label' => 'Seqino e-reporting sync',
                'jobtype' => 'method',
                'class' => '/seqino/class/SeqinoCronJobs.class.php',
                'objectname' => 'SeqinoCronJobs',
                'method' => 'runEreporting',
                'parameters' => '',
                'comment' => 'Queue-based e-reporting payload sync to Seqino PDP',
                'frequency' => 10,
                'unitfrequency' => 60,
                'status' => 1,
                'test' => '$conf->seqino->enabled',
            ),
        );

        $this->menu = array();

        // NOTE: Placeholder for direct interactive widget overrides in a future extension.
    }

    /**
     * Init function called when module is enabled.
     */
    public function init($options = '')
    {
        $sql = array();
        $sql[] = 'CREATE TABLE IF NOT EXISTS '.MAIN_DB_PREFIX."seqino_queue (
            rowid integer AUTO_INCREMENT PRIMARY KEY,
            entity integer NOT NULL DEFAULT 1,
            direction varchar(10) NOT NULL,
            payload_type varchar(32) NOT NULL,
            payload_id varchar(64) NOT NULL,
            payload text NULL,
            status varchar(20) NOT NULL DEFAULT 'queued',
            retries integer NOT NULL DEFAULT 0,
            external_id varchar(128) NULL,
            last_error text NULL,
            datec datetime NOT NULL,
            tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_seqino_queue_entity_status (entity, status),
            INDEX idx_seqino_queue_payload_type (payload_type)
        ) ENGINE=innodb";

        return $this->_init($sql, $options);
    }

    /**
     * Remove function called when module is disabled.
     */
    public function remove($options = '')
    {
        $sql = array();

        return $this->_remove($sql, $options);
    }
}
