<?php

namespace SimpleSAML\Module\coselection;

use SimpleSAML\Auth\State;
use SimpleSAML\Utils\HTTP;
/**
 * Class defining the logout completed handler for the attribute selection page.
 *
 * @package SimpleSAMLphp
 */
class Logout
{

	public static function postLogout(\SimpleSAML\IdP $idp, array $state)
	{
		//$url = \SimpleSAML\Module::getModuleURL('coselection/logout_completed.php');
		$restartUrl = $state[State::RESTART];
		HTTP::redirectTrustedURL($restartUrl);
	}
}
