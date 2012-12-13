<?php 
define("OUTPUT_PATH", "/var/www/Rpository/");
import('classes.plugins.GenericPlugin');
require_once('OJSPackager.php');
require_once('RpositoryDAO.inc.php');

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
    
    // this is called whenever one of our registered hooks is fired
    function callback_update($hookName, $args){
        $sql    =& $args[0]; 
        $params =& $args[1]; 
         
        $articleId = NULL;
        $articlePublished = NULL;
        
        // what hook was fired?
        if($hookName === 'articledao::_updatearticle'){
            $articleId          = $params[18];
            $articlePublished   = ($params[6] === 3);
        }
        elseif($hookName === 'publishedarticledao::_updatepublishedarticle'){
            $articleId          = $params[0];
            $articlePublished   = true;
        }
        
        // get references to DAOs needed for the update     
        $daos           =& DAORegistry::getDAOs();
        $articledao     =& $daos['ArticleDAO'];
        $rpositorydao   =& $daos['RpositoryDAO'];
        
        
        // do the update and suppress hookcalls in DAO::update()
        if($hookName === 'articledao::_updatearticle'){
            $articledao->update($sql, array(
                $article    =& $articledao->getArticle($articleId),
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
        if(!$articlePublished){
            return true;
        }
        
        // check whether or not we already created an archive for $articleId and if so when
        $result = $rpositorydao->getDateFilename($articleId);
        $resultIsEmpty = !array_key_exists('filename', $result);
        $modifiedInLast2Days = false;
        if(!$resultIsEmpty){
            $date = new DateTime();
            $today = $date->getTimestamp();
            $lastModified = strtotime($result['date']);

            //  2 days = 8,640,000 msec
            if(($today - $lastModified) < 8640000){
                $modifiedInLast2Days = true;
            }
        }
        
        // we already created an archive and it was in the last two days -> delete the old archive and db entry
        if((!$resultIsEmpty)&&($modifiedInLast2Days)){
            if(!unlink(OUTPUT_PATH . $result['filename'])){
                error_log('OJS - rpository: error deleting file ' . OUTPUT_PATH . $result['filename']);
            }
            if(!$rpositorydao->delCurrentEntry($articleId)){
                error_log('OJS - rpository: error deleting DB entry');
            }
        }
        // get journal_id for building the correct path to the article files and feed it to an OJSPackager
        //$journal_id = $rpositorydao->getJournalId($articleId);
        $journal_id = $articledao->getArticleJournalId($articleId);        
        $test = new OJSPackager(OUTPUT_PATH, Config::getVar('files', 'files_dir') . '/journals/' . $journal_id . '/articles');
        
        // create the new package for $articleId
        $writtenArchive = $test->writePackage($articleId);
        
        // check for conflicting file names - in case of collusion add a suffix to archive name
        if(!$rpositorydao->insertNewEntry($articleId, $writtenArchive)){
            $suffix = 'a';
            do{
                if($suffix > 'z'){
                    error_log('OJS - rpository: error creating archive');
                    break;
                }
                if(!unlink($writtenArchive)){
                    error_log('OJS - rpository: error deleting file ' . $writtenArchive);
                }
                $writtenArchive = $test->writePackage($articleId, $suffix);
                ++$suffix;
            }
            while(!$rpositorydao->insertNewEntry($articleId, $writtenArchive));
        }
        
        // return true to suppress a 2nd article update in DAO::update()
        // after the callback ran through
         
        return true;
    }
} 
?>

