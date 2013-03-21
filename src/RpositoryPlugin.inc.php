<?php 
import('classes.plugins.GenericPlugin'); 
import('classes.plugins.GenericPlugin');
require_once('OJSPackager.php');
require_once('RpositoryDAO.inc.php');
require_once('PidWebserviceCredentials.inc.php');

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
        $output = shell_exec('cd ' . OUTPUT_PATH . '; echo ' . $rcmd  . '| /usr/bin/R -q --vanilla' );
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
        $packager = new OJSPackager(Config::getVar('files', 'files_dir') . '/journals/' . $journal_id . '/articles');
        
        // create the new package for $articleId
        $archive = $packager->writePackage($articleId);
        
        if($archive == NULL){
            error_log("OJS - rpository: creating archive failed");
            return FALSE;
        }
        
        // insert new Package into repository
        $writtenArchive = $rpositorydao->updateRepository($articleId, $archive);
        if($writtenArchive == NULL){
            return FALSE;
        }
        else{
            $this->updatePackageIndex();
        }
        
        
        
        return FALSE;
    }
} 
?>

