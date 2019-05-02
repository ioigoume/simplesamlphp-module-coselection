<?php

/**
 * Class defining the logout completed handler for the attribute selection page.
 *
 * @package SimpleSAMLphp
 */

namespace SimpleSAML\Module\CoSelection;

class Logout {

	public static function postLogout(\SimpleSAML\IdP $idp, array $state) {
		//$url = \SimpleSAML\Module::getModuleURL('coselection/logout_completed.php');
		$restartURL = $state[\SimpleSAML\Auth\State::RESTART];
		\SimpleSAML\Utils\HTTP::redirectTrustedURL($restartURL);
	}

}
