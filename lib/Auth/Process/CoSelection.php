<?php
/**
 * CO Selection Processing filter
 *
 * Filter for selecting specific CO
 * released to the SP.
 *
 * @package SimpleSAMLphp
 *
 * Configuration:
 * 79 => array(
 *   'class' => 'coselection:CoSelection',
 *   //'intro' => 'VO list:', // default to null
 *   'requiredattributes' => array(
 *     'eduPersonUniqueId' => array(
 *       //'mode' => 'radio',
 *     ),
 *   ),
 *   'blacklist' => array(
 *     'https://aai-dev.egi.eu/registry/shibboleth',
 *     'https://comanage-dev.aai-dev.grnet.gr/registry/shibboleth',
 *     'https://comanage-upstream.aai-dev.grnet.gr/registry/shibboleth',
 *   ),
 * ),
 */
class sspmod_coselection_Auth_Process_CoSelection extends SimpleSAML_Auth_ProcessingFilter {

  private $_userIdAttribute = 'eduPersonUniqueId';

  // List of SP entity IDs that should be excluded from this filter.
  private $_blacklist = array();

  private $_basicInfoQuery = 'select'
    . ' person.co_id,'
    . ' cos.name'
    . ' from cm_co_people person'
    . ' inner join cm_co_org_identity_links link'
    . ' on person.id = link.co_person_id'
    . ' inner join cm_cos cos'
    . ' on person.co_id = cos.id'
    . ' inner join cm_org_identities org'
    . ' on link.org_identity_id = org.id'
    . ' inner join cm_identifiers ident'
    . ' on org.id = ident.org_identity_id'
    . ' where'
    . ' not person.deleted'
    . ' and (person.status=\'GP\''
    . ' or person.status=\'A\')'
    . ' and not link.deleted'
    . ' and not cos.id=1'
    . ' and ident.identifier = :coPersonOrgId';

  /**
   * Initialize attribute selection filter
   *
   * Validates and parses the configuration
   *
   * @param array $config   Configuration information
   * @param mixed $reserved For future use
   */
  public function __construct($config, $reserved) {
    assert('is_array($config)');
    parent::__construct($config, $reserved);
    if (array_key_exists('requiredattributes', $config)) {
      if (!is_array($config['requiredattributes'])) {
        throw new SimpleSAML_Error_Exception(
          'CoSelection: requiredattributes must be an array. ' .
          var_export($config['requiredattributes'], true) . ' given.'
        );
      }
      $this->_requiredattributes = $config['requiredattributes'];
    }
    if (array_key_exists('blacklist', $config)) {
      if (!is_array($config['blacklist'])) {
        SimpleSAML_Logger::error("CoSelection Configuration error: 'blacklist' not an array");
        throw new SimpleSAML_Error_Exception(
          "CoSelection configuration error: 'blacklist' not an array");
      }
      $this->_blacklist = $config['blacklist'];
    }
    if (array_key_exists('intro', $config)) {
      $this->_intro = $config['intro'];
    }
  }

  /**
   * Helper function to check whether attribute selection is disabled.
   *
   * @param mixed $option  The coselection.disable option. Either an array or a boolean.
   * @param string $entityIdD  The entityID of the SP/IdP.
   * @return boolean  TRUE if disabled, FALSE if not.
   */
  private static function checkDisable($option, $entityId) {
    if (is_array($option)) {
      return in_array($entityId, $option, TRUE);
    } else {
      return (boolean)$option;
    }
  }
  /**
   * Process a authentication response
   *
   * This function saves the state, and redirects the user to the page where
   * the user can authorize the release of the attributes.
   *
   * @param array &$state The state of the response.
   *
   * @return void
   */
  public function process(&$state) {
    assert('is_array($state)');
    assert('array_key_exists("Destination", $state)');
    assert('array_key_exists("entityid", $state["Destination"])');
    assert('array_key_exists("metadata-set", $state["Destination"])');
    assert('array_key_exists("entityid", $state["Source"])');
    assert('array_key_exists("metadata-set", $state["Source"])');
    $spEntityId = $state['Destination']['entityid'];
    $idpEntityId = $state['Source']['entityid'];
    $userAttributes = $state['Attributes'];
    $metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();

    if (isset($state['SPMetadata']['entityid']) && in_array($state['SPMetadata']['entityid'], $this->_blacklist, true)) {
      SimpleSAML_Logger::debug("CoSelection: Skipping blacklisted SP ". var_export($state['SPMetadata']['entityid'], true));
      return;
    }
    /**
     * If the attribute selection module is active on a bridge $state['saml:sp:IdP']
     * will contain an entry id for the remote IdP. If not, then the
     * attribute selection module is active on a local IdP and nothing needs to be
     * done.
     */
    if (isset($state['saml:sp:IdP'])) {
      $idpEntityId = $state['saml:sp:IdP'];
      $idpmeta         = $metadata->getMetaData($idpEntityId, 'saml20-idp-remote');
      $state['Source'] = $idpmeta;
    }
    $statsData = array('spEntityID' => $spEntityId);
    // Do not use attribute selection if disabled
    // coselection.disable like attributeselection.disable from attribute selection module
    if (isset($state['Source']['coselection.disable']) && self::checkDisable($state['Source']['coselection.disable'], $spEntityId)) {
      SimpleSAML_Logger::debug('CoSelection: CoSelection disabled for entity ' . $spEntityId . ' with IdP ' . $idpEntityId);
      SimpleSAML_Stats::log('coselection:disabled', $statsData);
      return;
    }
    if (isset($state['Destination']['coselection.disable']) && self::checkDisable($state['Destination']['coselection.disable'], $idpEntityId)) {
      SimpleSAML_Logger::debug('CoSelection: CoSelection disabled for entity ' . $spEntityId . ' with IdP ' . $idpEntityId);
      SimpleSAML_Stats::log('coselection:disabled', $statsData);
      return;
    }
    $state['coselection:intro'] = $this->_intro;
    $state['coselection:requiredattributes'] = $this->_requiredattributes;
    // User interaction nessesary. Throw exception on isPassive request
    if (isset($state['isPassive']) && $state['isPassive'] === true) {
      SimpleSAML_Stats::log('coselection:nopassive', $statsData);
      throw new SimpleSAML_Error_NoPassive(
        'Unable to give attribute selection on passive request.'
      );
    }
    // Skip attribute selection when user's attributes
    $hasValues = false;
    foreach ($state['coselection:requiredattributes'] as $key => $value) {
      if (!empty($userAttributes[$key])) {
        $hasValues = true;
        break;
      }
    }
    if (!$hasValues) {
      SimpleSAML_Logger::debug('CoSelection: User doesn\'t have the required attributes for co selection');
      SimpleSAML_Stats::log('coSelection:empty', $statsData);
      return;
    }
    foreach ($state['coselection:requiredattributes'] as $key => $value) {
      if (isset($key) && $key == $this->_userIdAttribute) {
        $orgId = $state['Attributes'][$this->_userIdAttribute][0];
        SimpleSAML_Logger::debug("[coselection] process: orgId=". var_export($orgId, true));
        $basicInfo = $this->_getBasicInfo($orgId);
        SimpleSAML_Logger::debug("[coselection] process: basicInfo=". var_export($basicInfo, true));
        if(isset($basicInfo) && count($basicInfo) > 0){
          $coMembership = array();
          foreach ($basicInfo as $co){
            $coMembership[$co['co_id']] = $co['name'];
          }
          $state['coselection:coMembership'] = $coMembership;
        }else{
          return;
        }
      }
    }
    $state['Attributes'] = $userAttributes;
    if(isset($state['coselection:coMembership']) && count($state['coselection:coMembership']) == 1){
      $state['COSelected'] = $state['coselection:coMembership'];
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

      SimpleSAML_Stats::log('coSelection:accept', $statsInfo);
      // Resume processing
      SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
    } elseif (!isset($state['coselection:coMembership']) || count($state['coselection:coMembership']) < 1) {
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
      return;
    } else {
      // Save state and redirect
      $id = SimpleSAML_Auth_State::saveState($state, 'coselection:request');
      $url = SimpleSAML_Module::getModuleURL('coselection/getcoselection.php');
      SimpleSAML\Utils\HTTP::redirectTrustedURL($url, array('StateId' => $id));
    }
  }


  private function _getBasicInfo($orgId)
  {
    SimpleSAML_Logger::debug("[coselection] _getBasicInfo: orgId=". var_export($orgId, true));

    $db = SimpleSAML\Database::getInstance();
    $queryParams = array(
      'coPersonOrgId' => array($orgId, PDO::PARAM_STR),
    );
    $stmt = $db->read($this->_basicInfoQuery, $queryParams);
    if ($stmt->execute()) {
      if ($result = $stmt->fetchAll(PDO::FETCH_ASSOC)) {
        SimpleSAML_Logger::debug("[coselection] _getBasicInfo: result=". var_export($result, true));
        return $result;
      }
    } else {
      throw new Exception('Failed to communicate with COmanage Registry: '.var_export($db->getLastError(), true));
    }

    return null;
  }

  private function _showException($e)
  {
    $globalConfig = SimpleSAML_Configuration::getInstance();
    $t = new SimpleSAML_XHTML_Template($globalConfig, 'coselection:exception.tpl.php');
    $t->data['e'] = $e->getMessage();
    $t->show();
    exit();
  }
}
