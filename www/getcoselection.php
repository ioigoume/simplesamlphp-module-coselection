<?php
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
$globalConfig = \SimpleSAML\Configuration::getInstance();
\SimpleSAML\Logger::info('CoSelection - coselection: Accessing co selection interface');
if (!array_key_exists('StateId', $_REQUEST)) {
    throw new \SimpleSAML\Error\BadRequest(
        'Missing required StateId query parameter.'
    );
}
$id = $_REQUEST['StateId'];
$state = \SimpleSAML\Auth\State::loadState($id, 'coselection:request');

// Handle user's choice and return to simplesaml to load the next plugin
if (array_key_exists('coSelection', $_REQUEST)) {
  // Check if this is the value that the user chose
  $choice = explode(":", $_REQUEST['coSelection']);
  $cosInState = $state['coselection:coMembership'];
  if($cosInState[$choice[0]] != $choice[1]){
    \SimpleSAML\Logger::debug('getcoselection: the requested co is not in the retrieved list ');
    throw new \SimpleSAML\Error\BadRequest('Missing required CO id retrieved from query.');
  }else{
    // Add the CO selected in the state
    $state['COSelected'] = array($choice[0] => $choice[1]);
  }
}


if (array_key_exists('core:SP', $state)) {
    $spentityid = $state['core:SP'];
} else if (array_key_exists('saml:sp:State', $state)) {
    $spentityid = $state['saml:sp:State']['core:SP'];
} else {
    $spentityid = 'UNKNOWN';
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
  \SimpleSAML\Stats::log('coSelection:accept', $statsInfo);
  // Resume processing
  \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
}elseif(array_key_exists('no', $_REQUEST)){
  \SimpleSAML\Stats::log('coSelection:abort', $statsInfo);
  // Resume processing
  \SimpleSAML\Auth\ProcessingChain::resumeProcessing($state);
}


////////////// End of handling users choice
///
///

// Make, populate and layout attribute selection form
$t = new \SimpleSAML\XHTML\Template($globalConfig, 'coselection:coselectionform.php');
$t->data['srcMetadata'] = $state['Source'];
$t->data['dstMetadata'] = $state['Destination'];
$t->data['yesTarget'] = \SimpleSAML\Module::getModuleURL('coselection/getcoselection.php');
$t->data['yesData'] = array('StateId' => $id);
$t->data['noTarget'] = \SimpleSAML\Module::getModuleURL('coselection/nocoselection.php');
$t->data['noData'] = array('StateId' => $id);
$t->data['attributes'] = $state['Attributes'];
$t->data['logoutLink'] = \SimpleSAML\Module::getModuleURL('coselection/logout.php');
$t->data['logoutData'] = array('StateId' => $id);
// Fetch privacypolicy
if (array_key_exists('privacypolicy', $state['Destination'])) {
    $privacypolicy = $state['Destination']['privacypolicy'];
} elseif (array_key_exists('privacypolicy', $state['Source'])) {
    $privacypolicy = $state['Source']['privacypolicy'];
} else {
    $privacypolicy = false;
}
if ($privacypolicy !== false) {
    $privacypolicy = str_replace(
        '%SPENTITYID%',
        urlencode($spentityid), 
        $privacypolicy
    );
}
$t->data['sppp'] = $privacypolicy;
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
