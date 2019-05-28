<?php

use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\Module;
use SimpleSAML\Stats;
use SimpleSAML\XHTML\Template;

/**
 * This is the page the user lands on when choosing "no" in the attribute selection form.
 *
 * @package SimpleSAMLphp
 */
if (!array_key_exists('StateId', $_REQUEST)) {
    throw new BadRequest(
        'Missing required StateId query parameter.'
    );
}

$id = $_REQUEST['StateId'];
$state = State::loadState($id, 'coselection:request');

$resumeFrom = Module::getModuleURL(
    'coselection/getcoselection.php',
    array('StateId' => $id)
);

$logoutLink = Module::getModuleURL(
    'coselection/logout.php',
    array('StateId' => $id)
);

$aboutService = null;

$statsInfo = array();
if (isset($state['Destination']['entityid'])) {
    $statsInfo['spEntityID'] = $state['Destination']['entityid'];
}
Stats::log('coselection:reject', $statsInfo);

$globalConfig = Configuration::getInstance();

$t = new Template($globalConfig, 'coselection:nocoselection.php');
$t->data['dstMetadata'] = $state['Destination'];
$t->data['resumeFrom'] = $resumeFrom;
$t->data['aboutService'] = $aboutService;
$t->data['logoutLink'] = $logoutLink;
$t->show();
