<?php

use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Stats;
use SimpleSAML\XHTML\Template;

/**
 * Attribute selection script
 *
 * This script displays a page to the user, which requests that the user
 * authorizes the release of attributes.
 *
 * @package SimpleSAMLphp
 */
/**
 * Explicit instruct attribute selection page to send no-cache header to browsers to make 
 * sure the users attribute information are not store on client disk.
 * 
 * In an vanilla apache-php installation is the php variables set to:
 *
 * session.cache_limiter = nocache
 *
 * so this is just to make sure.
 */
session_cache_limiter('nocache');
$globalConfig = Configuration::getInstance();
Logger::info('CoSelection - coselection: Accessing co selection interface');
if (!array_key_exists('StateId', $_REQUEST)) {
    throw new BadRequest(
        'Missing required StateId query parameter.'
    );
}
$id = $_REQUEST['StateId'];
$state = Auth\State::loadState($id, 'coselection:request');

// Handle user's choice and return to simplesaml to load the next plugin
if (array_key_exists('coSelection', $_REQUEST)) {
  // Check if this is the value that the user chose
  $choice = explode(":", $_REQUEST['coSelection']);
  $cosInState = $state['coselection:coMembership'];
  if($cosInState[$choice[0]] != $choice[1]){
    Logger::debug('getcoselection: the requested co is not in the retrieved list ');
    throw new BadRequest('Missing required CO id retrieved from query.');
  }else{
    // Add the CO selected in the state
    $state['COSelected'] = array($choice[0] => $choice[1]);
  }
}


if (array_key_exists('core:SP', $state)) {
    $spEntityId = $state['core:SP'];
} else if (array_key_exists('saml:sp:State', $state)) {
    $spEntityId = $state['saml:sp:State']['core:SP'];
} else {
    $spEntityId = 'UNKNOWN';
}

// The user has pressed the yes-button
if ( array_key_exists('yes', $_REQUEST) || array_key_exists('no', $_REQUEST) ) {
  if (isset($state['Destination']['entityid'])) {
      $statsInfo['spEntityID'] = $state['Destination']['entityid'];
  }
  // Remove the fields that we do not want any more
  if (array_key_exists('coselection:coMembership', $state)) {
    unset($state['coselection:coMembership']);
  }
  if (array_key_exists('coselection:intro', $state)) {
    unset($state['coselection:intro']);
  }
  if (array_key_exists('coselection:requiredattributes', $state)) {
    unset($state['coselection:requiredattributes']);
  }
}

// The user has pressed the yes-button
if (array_key_exists('yes', $_REQUEST)) {
  Stats::log('coSelection:accept', $statsInfo);
  // Resume processing
  Auth\ProcessingChain::resumeProcessing($state);
}elseif(array_key_exists('no', $_REQUEST)){
  Stats::log('coSelection:abort', $statsInfo);
  // Resume processing
  Auth\ProcessingChain::resumeProcessing($state);
}


////////////// End of handling users choice
///
///

// Make, populate and layout attribute selection form
$t = new Template($globalConfig, 'coselection:coselectionform.php');
$t->data['srcMetadata'] = $state['Source'];
$t->data['dstMetadata'] = $state['Destination'];
$t->data['yesTarget'] = Module::getModuleURL('coselection/getcoselection.php');
$t->data['yesData'] = array('StateId' => $id);
$t->data['noTarget'] = Module::getModuleURL('coselection/nocoselection.php');
$t->data['noData'] = array('StateId' => $id);
$t->data['attributes'] = $state['Attributes'];
$t->data['logoutLink'] = Module::getModuleURL('coselection/logout.php');
$t->data['logoutData'] = array('StateId' => $id);
// Fetch privacyPolicy
if (array_key_exists('privacypolicy', $state['Destination'])) {
    $privacyPolicy = $state['Destination']['privacypolicy'];
} elseif (array_key_exists('privacypolicy', $state['Source'])) {
    $privacyPolicy = $state['Source']['privacypolicy'];
} else {
    $privacyPolicy = false;
}
if ($privacyPolicy !== false) {
    $privacyPolicy = str_replace(
        '%SPENTITYID%',
        urlencode($spEntityId), 
        $privacyPolicy
    );
}
$t->data['sppp'] = $privacyPolicy;
if (array_key_exists('coselection:coMembership', $state)) {
    $t->data['selectco'] = $state['coselection:coMembership'];
} else {
    $t->data['selectco'] = array();
}

if (array_key_exists('coselection:intro', $state)) {
    $t->data['intro'] = $state['coselection:intro'];
} else {
    $t->data['intro'] = array();
}
$t->show();
