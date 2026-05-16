<?php
/* Copyright (C) 2026 ITized */

declare(strict_types=1);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'seqino@seqino'));

if (empty($user->admin)) {
    accessforbidden();
}

$page_name = 'SeqinoSetup';
llxHeader('', $langs->trans($page_name));

print load_fiche_titre($langs->trans($page_name));

print '<div class="opacitymedium">'.$langs->trans('SeqinoSetupFoundationOnly').'</div>';
print '<ul>';
print '<li>'.$langs->trans('SeqinoEnvironment').': '.dol_escape_htmltag(getDolGlobalString('SEQINO_ENVIRONMENT')).'</li>';
print '<li>'.$langs->trans('SeqinoSandboxUrl').': '.dol_escape_htmltag(getDolGlobalString('SEQINO_API_BASE_URL_SANDBOX')).'</li>';
print '<li>'.$langs->trans('SeqinoProductionUrl').': '.dol_escape_htmltag(getDolGlobalString('SEQINO_API_BASE_URL_PRODUCTION')).'</li>';
print '</ul>';

// TODO: implement advanced real-time webhook routing here.

llxFooter();
$db->close();
