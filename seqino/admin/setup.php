<?php
/* Copyright (C) 2026 ITized */

declare(strict_types=1);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

$langs->loadLangs(array('admin', 'seqino@seqino'));

if (empty($user->admin)) {
    accessforbidden();
}

$action = GETPOST('action', 'aZ09');
if ($action === 'save') {
    if (!function_exists('newToken') || !function_exists('checkToken')) {
        accessforbidden();
    }

    if (!checkToken()) {
        accessforbidden('Invalid CSRF token');
    }

    $environment = GETPOST('SEQINO_ENVIRONMENT', 'aZ09');
    $sandboxUrl = trim((string) GETPOST('SEQINO_API_BASE_URL_SANDBOX', 'alphanohtml'));
    $productionUrl = trim((string) GETPOST('SEQINO_API_BASE_URL_PRODUCTION', 'alphanohtml'));
    $apiToken = trim((string) GETPOST('SEQINO_API_TOKEN', 'alphanohtml'));
    $apiTimeout = (int) GETPOST('SEQINO_API_TIMEOUT', 'int');

    if (!in_array($environment, array('sandbox', 'production'), true)) {
        setEventMessages($langs->trans('SeqinoInvalidEnvironment'), null, 'errors');
    } elseif (!filter_var($sandboxUrl, FILTER_VALIDATE_URL) || !filter_var($productionUrl, FILTER_VALIDATE_URL)) {
        setEventMessages($langs->trans('SeqinoInvalidBaseUrl'), null, 'errors');
    } elseif ($apiTimeout < 1 || $apiTimeout > 120) {
        setEventMessages($langs->trans('SeqinoInvalidTimeout'), null, 'errors');
    } else {
        dolibarr_set_const($db, 'SEQINO_ENVIRONMENT', $environment, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'SEQINO_API_BASE_URL_SANDBOX', $sandboxUrl, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'SEQINO_API_BASE_URL_PRODUCTION', $productionUrl, 'chaine', 0, '', $conf->entity);
        dolibarr_set_const($db, 'SEQINO_API_TIMEOUT', (string) $apiTimeout, 'integer', 0, '', $conf->entity);

        if ($apiToken !== '') {
            dolibarr_set_const($db, 'SEQINO_API_TOKEN', $apiToken, 'chaine', 0, '', $conf->entity);
        }

        setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
    }
}

$page_name = 'SeqinoSetup';
llxHeader('', $langs->trans($page_name));

print load_fiche_titre($langs->trans($page_name));

print '<div class="opacitymedium">'.$langs->trans('SeqinoSetupFoundationOnly').'</div>';

print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans('Parameter').'</td><td>'.$langs->trans('Value').'</td></tr>';

$currentEnv = getDolGlobalString('SEQINO_ENVIRONMENT');
if ($currentEnv === '') {
    $currentEnv = 'sandbox';
}

print '<tr><td>'.$langs->trans('SeqinoEnvironment').'</td><td>';
print '<select name="SEQINO_ENVIRONMENT">';
print '<option value="sandbox"'.($currentEnv === 'sandbox' ? ' selected' : '').'>sandbox</option>';
print '<option value="production"'.($currentEnv === 'production' ? ' selected' : '').'>production</option>';
print '</select>';
print '</td></tr>';

print '<tr><td>'.$langs->trans('SeqinoSandboxUrl').'</td><td><input type="text" class="flat minwidth500" name="SEQINO_API_BASE_URL_SANDBOX" value="'.dol_escape_htmltag(getDolGlobalString('SEQINO_API_BASE_URL_SANDBOX')).'"></td></tr>';
print '<tr><td>'.$langs->trans('SeqinoProductionUrl').'</td><td><input type="text" class="flat minwidth500" name="SEQINO_API_BASE_URL_PRODUCTION" value="'.dol_escape_htmltag(getDolGlobalString('SEQINO_API_BASE_URL_PRODUCTION')).'"></td></tr>';
print '<tr><td>'.$langs->trans('SeqinoApiToken').'</td><td><input type="password" class="flat minwidth400" name="SEQINO_API_TOKEN" value=""></td></tr>';
print '<tr><td>'.$langs->trans('SeqinoApiTimeout').'</td><td><input type="number" min="1" max="120" class="flat width75" name="SEQINO_API_TIMEOUT" value="'.((int) getDolGlobalInt('SEQINO_API_TIMEOUT') ?: 30).'"></td></tr>';
print '<tr><td>'.$langs->trans('SeqinoTokenUsedCount').'</td><td>'.((int) getDolGlobalInt('SEQINO_TOKEN_USED_COUNT')).'</td></tr>';
print '<tr><td>'.$langs->trans('SeqinoTokenAvailableCount').'</td><td>'.((int) getDolGlobalInt('SEQINO_TOKEN_AVAILABLE_COUNT')).'</td></tr>';
print '</table>';

print '<div class="center marginbottomonly margintoponly">';
print '<input type="submit" class="button button-save" value="'.$langs->trans('Save').'">';
print '</div>';
print '</form>';

// TODO: implement advanced real-time webhook routing here.

llxFooter();
$db->close();
