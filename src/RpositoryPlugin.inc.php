<?php
import('classes.plugins.GenericPlugin'); 
import('classes.plugins.GenericPlugin');
require_once('OJSPackager.php');
require_once('RpositoryDAO.inc.php');
require_once('PackedSupplFile.php');
require_once('ZipSupplFile.php');


class RpositoryPlugin extends GenericPlugin {    
    // register hooks and daos to the ojs system
    function register($category, $path){
        if(parent::register($category, $path)){
            Registry::set('RpositoryPlugIn', $this);
            HookRegistry::register('articledao::_updatearticle', array(&$this, 'callback_update'));
            HookRegistry::register('publishedarticledao::_updatepublishedarticle', array(&$this, 'callback_update'));
            
            $this->import('RpositoryDAO');
            $rpositoryDao = new RpositoryDAO();
            DAORegistry::registerDAO('RpositoryDAO', $rpositoryDao);
            return true;
        }
        return false; 
    }
    
    // name of the plugin OJS will use internally
    function getName(){ 
        return 'rpository';
    }
    
    // name of the plugin OJS will show to users
    function getDisplayName(){
        return 'Rpository Plugin';
    }
    
    // description of the plugin OJS will show to users
    function getDescription(){
        return 'creates R-style packages for published articles';
    }
    
    // path to the schema file OJS will try to parse during installation
    function getInstallSchemaFile(){
        return $this->getPluginPath() . '/' . 'install.xml';
    }
    
    function updatePackageIndex(){
	$rcmd = 'tools::write_PACKAGES\(\".\",fields=c\( \"Author\", \"Date\", \"Title\", \"Description\", \"License\", \"Suggests\", \"DOI\", \"CLARIN-PID\"\), type=c\(\"source\"\),verbose=TRUE\)';
        $output = shell_exec('cd ' . $this->getSetting(0, 'documentroot') . $this->getSetting(0, 'path') . '; echo ' . $rcmd  . '| /usr/bin/R -q --vanilla' );
    }
    
    // this is called whenever one of our registered hooks is fired
    function callback_update($hookName, $args){        
        $sql    =& $args[0]; 
        $params =& $args[1];
        
        $articleId = NULL;
        
        // what hook was fired?
        if($hookName === 'articledao::_updatearticle'){
            $articleId          = $params[18];
        }
        elseif($hookName === 'publishedarticledao::_updatepublishedarticle'){
            $articleId          = $params[0];
        }
               
        // get references to DAOs needed for the update     
        $daos           =& DAORegistry::getDAOs();
        $articledao     =& $daos['ArticleDAO'];
        $rpositorydao   =& $daos['RpositoryDAO'];
        
        // do the update and suppress hookcalls in DAO::update()
        if($hookName === 'articledao::_updatearticle'){
            $article    =& $articledao->getArticle($articleId);
            if($article == NULL){
                return FALSE;
            }
            $articledao->update($sql, array(
                $article->getLocale(),
                (int) $article->getUserId(),
                (int) $article->getSectionId(),
                $article->getLanguage(),
                $article->getCommentsToEditor(),
                $article->getCitations(),
                (int) $article->getStatus(),
                (int) $article->getSubmissionProgress(),
                (int) $article->getCurrentRound(),
                $articledao->nullOrInt($article->getSubmissionFileId()),
                $articledao->nullOrInt($article->getRevisedFileId()),
                $articledao->nullOrInt($article->getReviewFileId()),
                $articledao->nullOrInt($article->getEditorFileId()),
                $article->getPages(),
                (int) $article->getFastTracked(),
                (int) $article->getHideAuthor(),
                (int) $article->getCommentsStatus(),
                $article->getStoredDOI(),
                $article->getId()
            ), false);
        }
        elseif($hookName === 'publishedarticledao::_updatepublishedarticle'){
            $publishedarticledao =& $daos['PublishedArticleDAO'];
            $publishedarticledao->update($sql, $params, false);
        }
        
        
        // when the article isn't published we don't do anything to the repository
        if(!$rpositorydao->articleIsPublished($articleId)){
            return FALSE;
        }        
        
        $journal_id = $articledao->getArticleJournalId($articleId);        
        $packager = new OJSPackager(Config::getVar('files', 'files_dir') . '/journals/' . $journal_id . '/articles', new ZipSupplFile());
        
        // create the new package for $articleId
        $archive = $packager->writePackage($articleId);
        
        if($archive == NULL){
            error_log("OJS - rpository: creating archive failed");
            return FALSE;
        }
        
        // insert new Package into repository
        $writtenArchive = $rpositorydao->updateRepository(&$this, $articleId, $archive);
        if($writtenArchive == NULL){
            return FALSE;
        }
        else{
            $this->updatePackageIndex();
        }
        return FALSE;
    }
    
    /**
     * Get the name of the settings file to be installed on new journal
     * creation.
     * @return string
     */
    function getContextSpecificPluginSettingsFile(){
        return $this->getPluginPath() . '/settings.xml';
    }
    
    /**
     * Install default settings on system install.
     * @return string
     */
    function getInstallSitePluginSettingsFile() {
        return $this->getPluginPath() . '/settings.xml';
    }
    
    function getManagementVerbs(){
        $verbs = array();
        if ($this->getEnabled()) {
            $verbs[] = array('settings', __('plugins.generic.googleAnalytics.manager.settings'));
        }
        return parent::getManagementVerbs($verbs);
    }
    
    function manage($verb, $args, &$message){
        if(!parent::manage($verb, $args, $message)){
            return false;
        }
        switch($verb){
            case 'settings':
                $templateMgr =& TemplateManager::getManager();
                $templateMgr->register_function('plugin_url', array(&$this, 'smartyPluginUrl'));
                $journal =& Request::getJournal();

                $this->import('RpositoryPluginSettingsForm');
                $form = new RpositoryPluginSettingsForm($this, $journal->getId());
                if(Request::getUserVar('save')){
                    $form->readInputData();
                    if($form->validate()){
                        $form->execute();
          include 'RpositoryPlugin.inc.php';
                        Request::redirect(null, 'manager', 'plugin');
                        return false;
                    }
                    else{
                        $this->setBreadCrumbs(true);
                        $form->display();
                    }
                }
                else{
                    $this->setBreadCrumbs(true);
                    $form->initData();
                    $form->display();
                }
                return true;
            default:
                // Unknown management verb
                assert(false);
        }
    }
    
    /**
     * Set the page's breadcrumbs, given the plugin's tree of items
     * to append.
     * @param $subclass boolean
     */
    function setBreadcrumbs($isSubclass = false){
        $templateMgr =& TemplateManager::getManager();
        $pageCrumbs = array(
            array(
                Request::url(null, 'user'),
                'navigation.user'
            ),
            array(
                Request::url(null, 'manager'),
                'user.role.manager'
            )
        );
        if ($isSubclass) $pageCrumbs[] = array(
            Request::url(null, 'manager', 'plugins'),
            'manager.plugins'
        );

        $templateMgr->assign('pageHierarchy', $pageCrumbs);
    }
} 
?>
