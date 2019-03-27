<?php
/**
 * This is the handler for logout completed from the attribute selection page.
 *
 * @package SimpleSAMLphp
 */

$globalConfig = SimpleSAML_Configuration::getInstance();
$t = new SimpleSAML_XHTML_Template($globalConfig, 'coselection:logout_completed.php');
$t->show();
