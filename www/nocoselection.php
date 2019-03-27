<?php
/**
 * This is the page the user lands on when choosing "no" in the attribute selection form.
 *
 * @package SimpleSAMLphp
 */
if (!array_key_exists('StateId', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest(
        'Missing required StateId query parameter.'
    );
}

$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'coselection:request');

$resumeFrom = SimpleSAML_Module::getModuleURL(
    'coselection/getcoselection.php',
    array('StateId' => $id)
);

$logoutLink = SimpleSAML_Module::getModuleURL(
    'coselection/logout.php',
    array('StateId' => $id)
);

$aboutService = null;

$statsInfo = array();
if (isset($state['Destination']['entityid'])) {
    $statsInfo['spEntityID'] = $state['Destination']['entityid'];
}
SimpleSAML_Stats::log('coselection:reject', $statsInfo);

$globalConfig = SimpleSAML_Configuration::getInstance();

$t = new SimpleSAML_XHTML_Template($globalConfig, 'coselection:nocoselection.php');
$t->data['dstMetadata'] = $state['Destination'];
$t->data['resumeFrom'] = $resumeFrom;
$t->data['aboutService'] = $aboutService;
$t->data['logoutLink'] = $logoutLink;
$t->show();
