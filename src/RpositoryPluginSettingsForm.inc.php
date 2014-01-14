<?php
/**
 * @file ReferralPluginSettingsForm.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReferralPluginSettingsForm
 * @ingroup plugins_generic_referral
 *
 * @brief Form for journal managers to modify referral plugin settings
 */

// $Id$


import('lib.pkp.classes.form.Form');

class RpositoryPluginSettingsForm extends Form{

    /** @var $journalId int */
    var $journalId;

    /** @var $plugin object */
    var $plugin;

    /**
     * Constructor
     * @param $plugin object
     * @param $journalId int
     */
    function RpositoryPluginSettingsForm(&$plugin, $journalId) {
        $this->journalId = $journalId;
        $this->plugin =& $plugin;

        parent::Form($plugin->getTemplatePath() . 'settingsForm.tpl');
    }

    /**
     * Initialize form data.
     */
    function initData(){
        $plugin =& $this->plugin;

        $pidstatus = $plugin->getSetting(0, 'pidstatus');
        $output_path = $plugin->getSetting(0, 'documentroot') . $plugin->getSetting(0,'path');
		$path = $plugin->getSetting(0, 'path');
		$hostname = $plugin->getSetting(0, 'hostname');
		$documentroot = $plugin->getSetting(0, 'documentroot');
        $repository_url = $plugin->getSetting(0, 'hostname') . "/" . $plugin->getSetting(0,'path');
        $pidv1_user = $plugin->getSetting(0, 'pidv1_user');
        $pidv1_pw = $plugin->getSetting(0, 'pidv1_pw');
        $pidv1_service_url = $plugin->getSetting(0, 'pidv1_service_url');
        $pidv1_timeout = $plugin->getSetting(0, 'pidv1_timeout');
        $pidv2_user = $plugin->getSetting(0, 'pidv2_user');
        $pidv2_pw = $plugin->getSetting(0, 'pidv2_pw');
        $pidv2_service_url = $plugin->getSetting(0, 'pidv2_service_url');
        $pidv2_timeout = $plugin->getSetting(0, 'pidv2_timeout');
        $pidv2_prefix = $plugin->getSetting(0, 'pidv2_prefix');
        
        $daos           =& DAORegistry::getDAOs();
        $rpositorydao   =& $daos['RpositoryDAO'];        
        $archives_without_pidv1 = $rpositorydao->getArticlesWithoutPid(1);
        $archives_without_pidv2 = $rpositorydao->getArticlesWithoutPid(2);
        
        $this->_data = array(
            'pidstatus' => $pidstatus,
            'output_path' => $output_path,
			'path' => $path,
			'hostname' => $hostname,
			'documentroot' => $documentroot,
            'repository_url' => $repository_url,
            'pidv1_user' => $pidv1_user,
            'pidv1_pw' => $pidv1_pw,
            'pidv1_service_url' => $pidv1_service_url,
            'pidv1_timeout' => $pidv1_timeout,
            'pidv2_user' => $pidv2_user,
            'pidv2_pw' => $pidv2_pw,
            'pidv2_service_url' => $pidv2_service_url,
            'pidv2_timeout' => $pidv2_timeout,
            'pidv2_prefix' => $pidv2_prefix,
            'archives_without_pidv1' => count($archives_without_pidv1),
            'archives_without_pidv2' => count($archives_without_pidv2)
        );
    }

    /**
     * Assign form data to user-submitted data.
     */
    function readInputData() {
        $this->readUserVars(array('pidstatus', 'path', 'hostname', 'documentroot',
            'pidv1_user', 'pidv1_pw', 'pidv1_service_url', 'pidv1_timeout',
            'pidv2_user', 'pidv2_pw', 'pidv2_service_url', 'pidv2_timeout',
            'pidv2_prefix', 'fetch_missing_pids_v1', 'fetch_missing_pids_v2'));
    }

    /**
     * Save settings. 
     */
    function execute() {
        $plugin =& $this->plugin;

        $newPidStatus = trim($this->getData('pidstatus'));
        $newOutputPath= trim($this->getData('documentroot').$this->getData('path'));
		$newPath= trim($this->getData('path'));
		$newHostname= trim($this->getData('hostname'));
		$newDocumentroot= trim($this->getData('documentroot'));
        $newRepositoryUrl = trim($this->getData('hostname') . "/" . $this->getData('path'));

        $plugin->updateSetting(0, 'pidstatus', $newPidStatus, 'int');
        $plugin->updateSetting(0, 'output_path', $newOutputPath, 'string');
		$plugin->updateSetting(0, 'path', $newPath, 'string');
		$plugin->updateSetting(0, 'hostname', $newHostname, 'string');
		$plugin->updateSetting(0, 'documentroot', $newDocumentroot, 'string');
	    $plugin->updateSetting(0, 'repository_url', $newRepositoryUrl, 'string');

        if($newPidStatus == 1){
            $newPidV1User = trim($this->getData('pidv1_user'));
            $plugin->updateSetting(0, 'pidv1_user', $newPidV1User, 'string');
            $newPidV1Pw = trim($this->getData('pidv1_pw'));
            $plugin->updateSetting(0, 'pidv1_pw', $newPidV1Pw, 'string');
            $newPidV1ServiceUrl = trim($this->getData('pidv1_service_url'));
            $plugin->updateSetting(0, 'pidv1_service_url', $newPidV1ServiceUrl, 'string');
            $newPidV1Timeout = trim($this->getData('pidv1_timeout'));
            $plugin->updateSetting(0, 'pidv1_timeout', $newPidV1Timeout, 'int');
            $fetch_pidv1 = trim($this->getData('fetch_missing_pids_v1'));
            
            if($fetch_pidv1 == 'on'){
                $daos           =& DAORegistry::getDAOs();
                $rpositorydao   =& $daos['RpositoryDAO'];
                $pids_to_fetch = $rpositorydao->getArticlesWithoutPid(1);
                foreach($pids_to_fetch as $articleId){
                    $rpositorydao->updatePID($this->plugin, $articleId);
                }
            }
        }
        elseif($newPidStatus == 2){
            $newPidV2User = trim($this->getData('pidv2_user'));
            $plugin->updateSetting(0, 'pidv2_user', $newPidV2User, 'string');
            $newPidV2Pw = trim($this->getData('pidv2_pw'));
            $plugin->updateSetting(0, 'pidv2_pw', $newPidV2Pw, 'string');
            $newPidV2ServiceUrl = trim($this->getData('pidv2_service_url'));
            $plugin->updateSetting(0, 'pidv2_service_url', $newPidV2ServiceUrl, 'string');
            $newPidV2Timeout = trim($this->getData('pidv2_timeout'));
            $plugin->updateSetting(0, 'pidv2_timeout', $newPidV2Timeout, 'int');
            $newPidV2Prefix = trim($this->getData('pidv2_prefix'));
            $plugin->updateSetting(0, 'pidv2_prefix', $newPidV2Prefix, 'string');
            $fetch_pidv2 = trim($this->getData('fetch_missing_pids_v2'));
            
            if($fetch_pidv2 == 'on'){
                $daos           =& DAORegistry::getDAOs();
                $rpositorydao   =& $daos['RpositoryDAO'];
                $pids_to_fetch = $rpositorydao->getArticlesWithoutPid(2);
                foreach($pids_to_fetch as $articleId){
                    $rpositorydao->test($articleId);
                }
            }
        }
    
    }
}

?>
