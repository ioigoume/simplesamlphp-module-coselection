<?php

namespace SimpleSAML\Module\coselection\Auth\Process;

use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Database;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module;
use SimpleSAML\Stats;
use SimpleSAML\Utils\HTTP;
use SimpleSAML\XHTML\Template;

/**
 * CO Selection Processing filter
 *
 * Filter for selecting specific CO
 * released to the SP.
 *
 * @package SimpleSAMLphp
 *
 * Configuration:
 *  // co selection
 *  75 => [
 *      'class' => 'coselection:CoSelection',
 *      'intro' => 'Choose CO from list', // default to null
 *      'requiredattributes' => [
 *          'eduPersonUniqueId' => [
 *              //'mode' => 'radio',
 *          ],
 *      ],
 *   ],
 */
class CoSelection extends \SimpleSAML\Auth\ProcessingFilter
{

    private $userIdAttribute = 'eduPersonUniqueId';

    private $basicInfoQuery = 'SELECT'
        . ' person.co_id,'
        . ' cos.name'
        . ' FROM cm_co_people AS person'
        . ' INNER JOIN cm_co_org_identity_links AS link'
        . ' ON person.id = link.co_person_id'
        . ' INNER JOIN cm_cos AS cos'
        . ' ON person.co_id = cos.id'
        . ' INNER JOIN cm_org_identities AS org'
        . ' ON link.org_identity_id = org.id'
        . ' INNER JOIN cm_identifiers AS ident'
        . ' ON org.id = ident.org_identity_id'
        . ' WHERE'
        . ' NOT person.deleted'
        . ' AND (person.status=\'GP\''
        . ' OR person.status=\'A\')'
        . ' AND NOT link.deleted'
        . ' AND NOT cos.id=1'
        . ' AND ident.identifier = :coPersonOrgId';

    /**
     * Initialize attribute selection filter
     *
     * Validates and parses the configuration
     *
     * @param array $config Configuration information
     * @param mixed $reserved For future use
     */
    public function __construct($config, $reserved)
    {
        assert('is_array($config)');
        parent::__construct($config, $reserved);
        if (array_key_exists('requiredattributes', $config)) {
            if (!is_array($config['requiredattributes'])) {
                throw new Error\Exception(
                    'CoSelection: requiredattributes must be an array. ' .
                        var_export($config['requiredattributes'], true) . ' given.'
                );
            }
            $this->requiredAttributes = $config['requiredattributes'];
        }

        if (array_key_exists('intro', $config)) {
            $this->intro = $config['intro'];
        }
    }

    /**
     * Helper function to check whether attribute selection is disabled.
     *
     * @param mixed $option The coselection.disable option. Either an array or a boolean.
     * @param string $entityIdD The entityID of the SP/IdP.
     * @return boolean TRUE if disabled, FALSE if not.
     */
    private static function checkDisable($option, $entityId)
    {
        if (is_array($option)) {
            return in_array($entityId, $option, true);
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
    public function process(&$state)
    {
        assert('is_array($state)');
        assert('array_key_exists("Destination", $state)');
        assert('array_key_exists("entityid", $state["Destination"])');
        assert('array_key_exists("metadata-set", $state["Destination"])');
        assert('array_key_exists("entityid", $state["Source"])');
        assert('array_key_exists("metadata-set", $state["Source"])');
        $spEntityId = $state['Destination']['entityid'];
        $idpEntityId = $state['Source']['entityid'];
        $userAttributes = $state['Attributes'];
        $metadata = MetaDataStorageHandler::getMetadataHandler();
        /**
         * If the attribute selection module is active on a bridge $state['saml:sp:IdP']
         * will contain an entry id for the remote IdP. If not, then the
         * attribute selection module is active on a local IdP and nothing needs to be
         * done.
         */
        if (isset($state['saml:sp:IdP'])) {
            $idpEntityId = $state['saml:sp:IdP'];
            $idpMeta = $metadata->getMetaData($idpEntityId, 'saml20-idp-remote');
            $state['Source'] = $idpMeta;
        }
        $statsData = ['spEntityID' => $spEntityId];
        // Do not use attribute selection if disabled
        // coselection.disable like attributeselection.disable from attribute selection module
        if (isset($state['Source']['coselection.disable']) && self::checkDisable($state['Source']['coselection.disable'], $spEntityId)) {
            Logger::debug('CoSelection: CoSelection disabled for entity ' . $spEntityId . ' with IdP ' . $idpEntityId);
            Stats::log('coselection:disabled', $statsData);
            return;
        }
        if (isset($state['Destination']['coselection.disable']) && self::checkDisable($state['Destination']['coselection.disable'], $idpEntityId)) {
            Logger::debug('CoSelection: CoSelection disabled for entity ' . $spEntityId . ' with IdP ' . $idpEntityId);
            Stats::log('coselection:disabled', $statsData);
            return;
        }
        $state['coselection:intro'] = $this->intro;
        $state['coselection:requiredattributes'] = $this->requiredAttributes;
        // User interaction nessesary. Throw exception on isPassive request
        if (isset($state['isPassive']) && $state['isPassive'] === true) {
            Stats::log('coselection:nopassive', $statsData);
            throw new Error\NoPassive(
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
            Logger::debug('CoSelection: User doesn\'t have the required attributes for co selection');
            Stats::log('coSelection:empty', $statsData);
            return;
        }
        foreach ($state['coselection:requiredattributes'] as $key => $value) {
            if (isset($key) && $key == $this->userIdAttribute) {
                $orgId = $state['Attributes'][$this->userIdAttribute][0];
                Logger::debug("[coselection] process: orgId=" . var_export($orgId, true));
                $basicInfo = $this->getBasicInfo($orgId);
                Logger::debug("[coselection] process: basicInfo=" . var_export($basicInfo, true));
                if (isset($basicInfo) && count($basicInfo) > 0) {
                    $coMembership = [];
                    foreach ($basicInfo as $co) {
                        $coMembership[$co['co_id']] = $co['name'];
                    }
                    $state['coselection:coMembership'] = $coMembership;
                } else {
                    return;
                }
            }
        }
        $state['Attributes'] = $userAttributes;
        if (isset($state['coselection:coMembership']) && count($state['coselection:coMembership']) == 1) {
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

            Stats::log('coSelection:accept', $statsInfo);
            // Resume processing
            Auth\ProcessingChain::resumeProcessing($state);
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
            $id = Auth\State::saveState($state, 'coselection:request');
            $url = Module::getModuleURL('coselection/getcoselection.php');
            HTTP::redirectTrustedURL($url, ['StateId' => $id]);
        }
    }


    private function getBasicInfo($orgId)
    {
        Logger::debug("[coselection] getBasicInfo: orgId=" . var_export($orgId, true));

        $db = Database::getInstance();
        $queryParams = [
            'coPersonOrgId' => [$orgId, \PDO::PARAM_STR],
        ];
        $stmt = $db->read($this->basicInfoQuery, $queryParams);
        if ($stmt->execute()) {
            if ($result = $stmt->fetchAll(\PDO::FETCH_ASSOC)) {
                Logger::debug("[coselection] getBasicInfo: result=" . var_export($result, true));
                return $result;
            }
        } else {
            throw new Error\Exception('Failed to communicate with COmanage Registry: ' . var_export($db->getLastError(), true));
        }

        return null;
    }

    private function showException($e)
    {
        $globalConfig = Configuration::getInstance();
        $t = new Template($globalConfig, 'coselection:exception.tpl.php');
        $t->data['e'] = $e->getMessage();
        $t->show();
        exit();
    }
}
