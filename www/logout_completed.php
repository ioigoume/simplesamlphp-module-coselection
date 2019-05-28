<?php

use SimpleSAML\Configuration;
use SimpleSAML\XHTML\Template;

/**
 * This is the handler for logout completed from the attribute selection page.
 *
 * @package SimpleSAMLphp
 */

$globalConfig = Configuration::getInstance();
$t = new Template($globalConfig, 'coselection:logout_completed.php');
$t->show();
