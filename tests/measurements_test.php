<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/measurements.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/customers.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/measurements.php';

tests_reset();

$shop = business_register([
    'business_name' => 'Measure Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'measures@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'measurement tests start from a signed-in shop');
$customer = customer_create([
    'name' => 'Hauwa Sani',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$kaftan = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$trouser = db_select('fashion_garment_types', ['code' => 'trouser'])[0];
$kaftanKeys = array_column(measurement_active_fields($kaftan['id']), 'field_key');
$trouserKeys = array_column(measurement_active_fields($trouser['id']), 'field_key');
check($kaftanKeys !== $trouserKeys, 'each garment type keeps its own fields');
check(in_array('neck', $kaftanKeys, true) && in_array('top_length', $kaftanKeys, true), 'kaftan uses this shop field list');

$first = measurement_save($customer['id'], $kaftan['id'], 'inches', [
    'neck' => '16',
    'shoulder' => '18',
    'chest' => '',
]);
check(isset($first['id']), 'a measurement is saved');
$stored = db_select('fashion_measurements', ['id' => $first['id']])[0];
check_eq((string) $stored['unit'], 'inches', 'the unit is stored with the measurement');
check_eq((string) $stored['customer_id'], $customer['id'], 'the measurement stays on the customer');
check_eq((string) $stored['created_by'], (string) current_user_id(), 'the measurement records who saved it');
check(str_contains((string) $stored['values_json'], '"neck":"16"'), 'the first values are stored as text');

$second = measurement_save($customer['id'], $kaftan['id'], 'cm', [
    'neck' => '41.5',
    'shoulder' => '46',
    'not_our_field' => '9',
]);
check(isset($second['id']) && $second['id'] !== $first['id'], 'a second save creates a new version');
$still = db_select('fashion_measurements', ['id' => $first['id']])[0];
check_eq((string) $still['unit'], 'inches', 'the first version keeps its unit');
check_eq((string) $still['values_json'], (string) $stored['values_json'], 'saving does not overwrite the earlier version');
check_eq(count(db_select('fashion_measurements', ['customer_id' => $customer['id']])), 2, 'versions increase');
$latestRow = db_select('fashion_measurements', ['id' => $second['id']])[0];
check_eq((string) $latestRow['unit'], 'cm', 'the new version stores its own unit');
check(!str_contains((string) $latestRow['values_json'], 'not_our_field'), 'a field from outside this garment is ignored');
check(isset(measurement_save($customer['id'], $kaftan['id'], 'inches', ['neck' => '16.555'])['error']), 'a measurement keeps at most two decimal places');
check_eq(count(db_select('fashion_measurements', ['customer_id' => $customer['id']])), 2, 'a rejected measurement is not stored');
check_throws(
    static fn () => db_update('fashion_measurements', ['unit' => 'cm'], ['id' => $first['id']]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    static fn () => db_delete('fashion_measurements', ['id' => $first['id']]),
    RuntimeException::class,
    'This table is append-only.'
);

$profile = measurement_profile($customer['id'], $kaftan['id']);
check_eq($profile['latest'][0]['values']['neck'], '41.5', 'the latest version for the garment is the newest save');
check_eq($profile['latest'][0]['unit'], 'cm', 'the latest version keeps its unit');
check_eq($profile['history'][0]['values']['neck'], '16', 'earlier versions stay in the history');
check_eq($profile['prefill']['unit'], 'cm', 'the form starts from the latest version');

$_POST = [
    'csrf' => csrf_token(),
    'action' => 'take',
    'garment_id' => $kaftan['id'],
    'unit' => 'inches',
    'values' => ['neck' => '17', 'shoulder' => '18'],
];
try {
    router_dispatch('POST', '/customers/' . $customer['id'] . '/measurements');
    check(false, 'taking a measurement redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/customers/' . $customer['id'] . '?garment=' . $kaftan['id'], 'taking a measurement returns to the customer');
}
$_POST = [
    'csrf' => csrf_token(),
    'action' => 'update',
    'garment_id' => $kaftan['id'],
    'unit' => 'cm',
    'values' => ['neck' => '42', 'shoulder' => '46'],
];
try {
    router_dispatch('POST', '/customers/' . $customer['id'] . '/measurements');
    check(false, 'updating a measurement redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/customers/' . $customer['id'] . '?garment=' . $kaftan['id'], 'updating a measurement returns to the customer');
}
check_eq(count(db_select('fashion_measurements', ['customer_id' => $customer['id']])), 4, 'take and update each add a version');
check_eq((string) db_select('fashion_measurements', ['id' => $first['id']])[0]['values_json'], (string) $stored['values_json'], 'the first version is still unchanged');

$_GET['garment'] = $kaftan['id'];
$_SERVER['REQUEST_URI'] = base_path() . '/customers/' . $customer['id'];
ob_start();
customers_show($customer['id']);
$open = (string) ob_get_clean();
check(str_contains($open, t('new_measurement')), 'the profile has New measurement');
check(str_contains($open, 'name="action" value="new"'), 'New measurement saves a new version');
check(!str_contains($open, 'name="action" value="take"') && !str_contains($open, 'name="action" value="update"'), 'the two old measurement buttons are one button');
check(str_contains($open, t('measure_keeps_old')), 'the form says old measurements are kept');
check(str_contains($open, t('view_old_measurements')), 'each garment can open its old measurements');
check(str_contains($open, '<details class="old-measures">'), 'old measurements are closed until the button is used');
check(!str_contains($open, '<details class="old-measures" open'), 'old measurements are hidden by default');
check(str_contains($open, t('use_as_start')), 'an old version can be used as a starting point');
check(str_contains($open, t('add_other')), 'the form can add one other field');
check(str_contains($open, t('clear_all')), 'the form can clear every box');
check(str_contains($open, t('clear_other_fields')), 'the form can clear the other fields');
check(str_contains($open, 'value="42"'), 'New measurement starts from the latest values');
check(str_contains($open, 'Amina Bello'), 'a measurement shows who took it');
check(str_contains($open, 'neck'), 'the profile shows this shop kaftan fields');
check(str_contains($open, '41.5'), 'the profile shows the latest measurement');
check(str_contains($open, '>16<'), 'the history still contains the first measurement');
$_GET['from'] = $first['id'];
ob_start();
customers_show($customer['id']);
$fromOld = (string) ob_get_clean();
check(str_contains($fromOld, '<details class="new-measure" open'), 'Use as starting point opens the form');
check(str_contains($fromOld, 'value="16"'), 'Use as starting point fills the old values');
unset($_GET['from']);

$ourField = db_select('fashion_measurement_fields', ['garment_type_id' => $kaftan['id'], 'field_key' => 'neck'])[0];
field_rename((string) $ourField['id'], 'neck opening');
check_eq(db_select('fashion_measurement_fields', ['id' => $ourField['id']])[0]['name'], 'neck opening', 'a shop can rename a measurement field');
field_add($kaftan['id'], 'Cap height');
$added = db_select('fashion_measurement_fields', ['garment_type_id' => $kaftan['id'], 'field_key' => 'cap_height']);
check(count($added) === 1 && (int) $added[0]['active'] === 1, 'a shop can add a measurement field');
$withCap = measurement_save($customer['id'], $kaftan['id'], 'inches', ['neck' => '17', 'cap_height' => '23']);
check(isset($withCap['id']), 'a new field can be saved');
check(str_contains((string) db_select('fashion_measurements', ['id' => $withCap['id']])[0]['values_json'], '"cap_height":"23"'), 'the new field is stored on the new version');
field_set_active((string) $added[0]['id'], false);
$withoutCap = measurement_save($customer['id'], $kaftan['id'], 'inches', ['neck' => '17', 'cap_height' => '23']);
check(!str_contains((string) db_select('fashion_measurements', ['id' => $withoutCap['id']])[0]['values_json'], 'cap_height'), 'an inactive field is skipped');
$withOther = measurement_save($customer['id'], $kaftan['id'], 'inches', ['neck' => '18'], [
    ['label' => '', 'value' => ''],
    ['label' => 'Thigh', 'value' => '22'],
    ['label' => '', 'value' => '9'],
]);
check(isset($withOther['id']), 'a measurement with an other field is saved');
$otherJson = (string) db_select('fashion_measurements', ['id' => $withOther['id']])[0]['values_json'];
check(str_contains($otherJson, '"other:Thigh":"22"'), 'an other field is stored on the new version');
check(substr_count($otherJson, 'other:') === 1, 'blank extra rows are ignored');
$paired = measurement_other_rows([
    'label' => ['', 'Cuff note'],
    'value' => ['4', ''],
]);
$ignored = measurement_save($customer['id'], $kaftan['id'], 'inches', ['neck' => '18'], $paired);
check(isset($ignored['id']), 'blank paired rows still save the real fields');
check(!str_contains((string) db_select('fashion_measurements', ['id' => $ignored['id']])[0]['values_json'], 'other:'), 'a blank label or value is ignored');

$other = business_register([
    'business_name' => 'Other Measure Shop',
    'owner_name' => 'Bala',
    'email' => 'measures-other@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'a second shop can sign up');
$otherKaftan = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$otherFields = array_column(measurement_active_fields($otherKaftan['id']), 'name');
check(!in_array('neck opening', $otherFields, true), 'another shop does not see the renamed field');
check(!in_array('Cap height', $otherFields, true), 'another shop does not see the added field');
check_eq(db_update('fashion_measurement_fields', ['name' => 'Stolen'], ['id' => (string) $ourField['id']]), 0, 'another shop cannot rename this field');
try {
    measurement_save($customer['id'], $kaftan['id'], 'cm', ['neck' => '40']);
    check(false, 'another shop cannot save on this customer');
} catch (HttpException $e) {
    check($e->status() === 404, 'another shop cannot save on this customer');
}
$theirCustomer = customer_create([
    'name' => 'Bala Customer',
    'phone' => '08130001122',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$theirSave = measurement_save($theirCustomer['id'], $otherKaftan['id'], 'cm', ['neck' => '40']);
check(isset($theirSave['id']), 'a shop saves against its own garment');
check(db_select('fashion_measurements', ['customer_id' => $customer['id']]) === [], 'a shop cannot read another customer measurement');
$blockedRemove = garment_remove($kaftan['id']);
check(isset($blockedRemove['error']), 'another shop cannot delete this garment');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
check_eq(db_select('fashion_measurement_fields', ['id' => $ourField['id']])[0]['name'], 'neck opening', 'the field name stays after the other shop');
check_eq(count(db_select('fashion_measurements', ['customer_id' => $customer['id']])), 8, 'the other shop did not add a version');

$fresh = garment_add('Unused wrap');
$freshRows = db_select('fashion_garment_types', ['name' => 'Unused wrap']);
check(count($freshRows) === 1, 'a garment type can be added');
$freshId = (string) $freshRows[0]['id'];
ob_start();
garments_editor($freshId);
$unusedEditor = (string) ob_get_clean();
check(str_contains($unusedEditor, t('garment_delete_confirm')), 'the confirmation says an unused garment is deleted');
$removed = garment_remove($freshId);
check(!empty($removed['deleted']), 'a garment with no measurements is deleted');
check(db_select('fashion_garment_types', ['id' => $freshId]) === [], 'the unused garment is gone');

garment_add('Used wrap');
$usedId = (string) db_select('fashion_garment_types', ['name' => 'Used wrap'])[0]['id'];
field_add($usedId, 'Across', 'cm');
$across = db_select('fashion_measurement_fields', ['garment_type_id' => $usedId, 'field_key' => 'across'])[0];
$usedSave = measurement_save($customer['id'], $usedId, 'inches', ['across' => '20']);
check(isset($usedSave['id']), 'a measurement can be saved on a new garment');
$archivedGarment = garment_remove($usedId);
check(!empty($archivedGarment['archived']), 'a garment with measurements is archived');
check((int) db_select('fashion_garment_types', ['id' => $usedId])[0]['active'] === 0, 'an archived garment is hidden from new forms');
check(count(db_select('fashion_measurements', ['garment_type_id' => $usedId])) === 1, 'archiving a garment keeps its measurements');

$spareRow = db_select('fashion_measurement_fields', ['garment_type_id' => $kaftan['id'], 'field_key' => 'spare_point']);
field_add($kaftan['id'], 'Spare point');
$spareRow = db_select('fashion_measurement_fields', ['garment_type_id' => $kaftan['id'], 'field_key' => 'spare_point'])[0];
$spareGone = field_remove((string) $spareRow['id']);
check(!empty($spareGone['deleted']), 'a field with no saved values is deleted');
check(db_select('fashion_measurement_fields', ['id' => $spareRow['id']]) === [], 'the unused field row is gone');
$acrossGone = field_remove((string) $across['id']);
check(!empty($acrossGone['archived']), 'a field with saved values is archived');
check((int) db_select('fashion_measurement_fields', ['id' => $across['id']])[0]['active'] === 0, 'the archived field stays in the table');
check(str_contains((string) db_select('fashion_measurements', ['id' => $usedSave['id']])[0]['values_json'], '"across":"20"'), 'old measurement values stay after the field is archived');

$orderBefore = array_column(field_rows($kaftan['id']), 'id');
field_reorder($kaftan['id'], (string) $orderBefore[1], 'up');
$orderAfter = array_column(field_rows($kaftan['id']), 'id');
check_eq($orderAfter[0], $orderBefore[1], 'moving a field up saves the new order without JavaScript');
field_order($kaftan['id'], array_reverse($orderAfter));
$orderReversed = array_column(field_rows($kaftan['id']), 'id');
check_eq($orderReversed, array_reverse($orderAfter), 'a posted field order is saved');

$_SERVER['REQUEST_URI'] = base_path() . '/settings/garments';
ob_start();
garments_form();
$garmentPage = (string) ob_get_clean();
check(str_contains($garmentPage, 'class="garment-card"'), 'garment types are tappable cards');
check(str_contains($garmentPage, t('add_garment')), 'the settings page can add a garment type');
check(str_contains($garmentPage, url('/settings/garments/' . $kaftan['id'])), 'a card opens that garment');
ob_start();
garments_editor($kaftan['id']);
$editorPage = (string) ob_get_clean();
check(str_contains($editorPage, 'data-drag-handle'), 'each field has a drag handle');
check(str_contains($editorPage, t('drag_hint')), 'the editor explains drag, the corner bin, and the remove button');
check(str_contains($editorPage, t('move_up')) && str_contains($editorPage, t('move_down')), 'each field can move up and down without dragging');
check(str_contains($editorPage, 'name="unit"'), 'a field has a unit');
check(str_contains($editorPage, t('add_field')), 'the editor can add a field');
check(str_contains($editorPage, t('field_removed')), 'removing a field offers undo');
check(str_contains($editorPage, 'data-field-bin'), 'dragging shows a delete drop zone');
check(str_contains($editorPage, t('garment_archive_confirm')), 'the confirmation says a used garment is archived');
check(str_contains($editorPage, 'data-saved="' . e(t('saved')) . '"'), 'the editor can confirm a save without leaving the page');

$liveField = field_rows($kaftan['id'])[0];
$liveName = (string) $liveField['name'];
$_SERVER['HTTP_ACCEPT'] = 'application/json';
$_POST = [
    'csrf' => csrf_token(),
    'action' => 'field_save',
    'id' => (string) $liveField['id'],
    'garment_id' => $kaftan['id'],
    'name' => 'Live neck',
    'unit' => 'cm',
];
ob_start();
$liveRedirected = false;
try {
    router_dispatch('POST', '/settings/garments');
} catch (RedirectException $e) {
    $liveRedirected = true;
}
$liveBody = (string) ob_get_clean();
unset($_SERVER['HTTP_ACCEPT']);
$_POST = [];
check($liveRedirected === false, 'a live save stays on the editor');
$liveJson = json_decode($liveBody, true);
check(is_array($liveJson) && !empty($liveJson['ok']) && ($liveJson['name'] ?? '') === 'Live neck', 'a live save returns the new field text');
check_eq((string) db_select('fashion_measurement_fields', ['id' => $liveField['id']])[0]['name'], 'Live neck', 'a live save is stored');
field_save((string) $liveField['id'], $liveName, (string) ($liveField['unit'] ?? ''));

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.communication',
]);
check(module_enabled('fashion.measurements') === false, 'measurements is switched off');
ob_start();
settings_form();
$settings = (string) ob_get_clean();
check(!str_contains($settings, 'href="' . url('/settings/garments') . '"'), 'Measurements settings link is hidden');
try {
    ob_start();
    router_dispatch('GET', '/settings/garments');
    ob_end_clean();
    check(false, 'garment settings stay closed');
} catch (HttpException $e) {
    ob_end_clean();
    check($e->status() === 403, 'garment settings return 403');
}
try {
    measurement_save($customer['id'], $kaftan['id'], 'inches', ['neck' => '16']);
    check(false, 'saving a measurement stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'saving a measurement returns 403 when measurements is off');
}
ob_start();
customers_show($customer['id']);
$plain = (string) ob_get_clean();
check(!str_contains($plain, t('measurements')), 'the measurement section leaves the profile');
check(!str_contains($plain, t('new_measurement')), 'New measurement leaves the profile');
check(str_contains($plain, 'Hauwa Sani'), 'the customer profile still opens');
