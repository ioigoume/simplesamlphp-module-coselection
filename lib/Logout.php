<?php

/**
 * Class defining the logout completed handler for the attribute selection page.
 *
 * @package SimpleSAMLphp
 */
class sspmod_coselection_Logout {

	public static function postLogout(SimpleSAML_IdP $idp, array $state) {
		//$url = SimpleSAML_Module::getModuleURL('coselection/logout_completed.php');
		$restartURL = $state[SimpleSAML_Auth_State::RESTART];
		\SimpleSAML\Utils\HTTP::redirectTrustedURL($restartURL);
	}

}
