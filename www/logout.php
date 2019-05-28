<?php

use SimpleSAML\Auth\State;
use SimpleSAML\Error\BadRequest;
use SimpleSAML\IdP;

/**
 * This is the handler for logout started from the attribute selection page.
 *
 * @package SimpleSAMLphp
 */

if (!array_key_exists('StateId', $_GET)) {
    throw new BadRequest('Missing required StateId query parameter.');
}
$state = State::loadState($_GET['StateId'], 'coselection:request');

$state['Responder'] = array('sspmod_coselection_Logout', 'postLogout');

$idp = IdP::getByState($state);
$idp->handleLogoutRequest($state, NULL);
assert('FALSE');
