<?php 

import('classes.plugins.GenericPlugin');
require_once('OJSPackager.php');

class RpositoryPlugin extends GenericPlugin {
    function register($category, $path){
        if(parent::register($category, $path)){
            Registry::set('RpositoryPlugIn', $this);
            HookRegistry::register('articledao::_updatearticle', array(&$this, 'callback_update'));
            HookRegistry::register('publishedarticledao::_updatepublishedarticle', array(&$this, 'callback_update'));
            return true;
        }
        return false; 
    }
    
    function getName(){ 
        return 'rpository';
    }
    
    function getDisplayName(){
        return 'Rpository Plugin';
    }
    
    function getDescription(){
        return 'creates R-style packages for published articles';
    }
    
    function getInstallSchemaFile(){
        return $this->getPluginPath() . '/' . 'install.xml';
    }
    
    function callback_update($hookName, $args){
        $sql    =& $args[0]; 
        $params =& $args[1]; 
        $value  =& $args[2];
         
        $articleId = NULL;
        $articlePublished = NULL;
        
        if($hookName === 'articledao::_updatearticle'){
            $articleId      = $params[18];
            $articlePublished  = ($params[6] === 3);
        }
        elseif($hookName === 'publishedarticledao::_updatepublishedarticle'){
            $articleId = $params[0];
            $articlePublished = true;
        }
        
        // get references to DAOs needed for the update     
        $daos       =& DAORegistry::getDAOs();
        $articledao =& $daos['ArticleDAO'];
        
        
        // TODO
        // this shouldn't work
        // every journal manager can get hold of the DB login/pw
        //
        // something like rpositoryDAO for rpository-specific DB transactions 
        // looks like the way to go
        $dbHost     = $articledao->_dataSource->host;
        $dbLogin    = $articledao->_dataSource->user;
        $dbPassword = $articledao->_dataSource->password;
        $dbName     = $articledao->_dataSource->database;
        $db         = mysql_connect($dbHost, $dbLogin, $dbPassword);
        
        
        
        // TODO
        // get $path dynamically 
        $path = '/var/www/Rpository/';

        
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
        
        if(!$articlePublished){
            return true;
        }        
        if(!$db){
            error_log('OJS - rpository: connecting to MySQL failed');
            return true;
        }
        if(!mysql_select_db($dbName, $db)){            
            error_log('OJS - rpository: selecting DB failed');
            return true;
        }
        
        $result = null;
        if(!$result = mysql_query("SELECT date, fileName FROM rpository WHERE articleId = $articleId AND current = 1", $db)){
            error_log('OJS - rpository: SQL query failed');
            return true;
        }
        
        $resultIsEmpty = true;
        $modifiedInLast2Days = false;
        $row = mysql_fetch_array($result);
        if($row){
            $resultIsEmpty = false;
            $date = new DateTime();
            $today = $date->getTimestamp();
            $lastModified = strtotime($row['date']);

            //  2 days = 8,640,000 msec
            if(($today - $lastModified) < 8640000){
                $modifiedInLast2Days = true;
            }
        }
        
        if((!$resultIsEmpty)&&($modifiedInLast2Days)){
            $result_oldFilename = mysql_query("SELECT fileName FROM rpository WHERE articleId = $articleId");
            $row_oldFilename = mysql_fetch_array($result_oldFilename);
            unlink($path . $row_oldFilename[0]);
            if(!mysql_query("DELETE FROM rpository WHERE articleId = $articleId AND current = 1", $db)){
                error_log('OJS - rpository: error deleting DB entry');
            }
           
            
            if(!unlink($path . $row['fileName'])){
                error_log('OJS - rpository: error deleting file:' . $row['fileName']);
            }
        }
        
        $result_journal_id = mysql_query("SELECT journals.journal_id FROM journals INNER JOIN articles ON journals.journal_id = articles.article_id WHERE article_id = $articleId");
        $row_journal_id = mysql_fetch_array($result_journal_id);
        
        $test = new OJSPackager('/var/www/Rpository', Config::getVar('files', 'files_dir') . '/journals/' . $row_journal_id['journal_id'] . '/articles');
        $writtenArchive = $test->writePackage($articleId);
        
        //exec("/opt/Rpository/art_to_repo.sh ". $articleId);        
        
        if(!mysql_query("INSERT INTO rpository (articleId, fileName, current, date) VALUES ($articleId, '$writtenArchive', 1, CURDATE())", $db)){
            error_log("OJS - rpository: error inserting DB entry " . mysql_error());
        }
        
        // return true to suppress a 2nd article update in DAO::update()
        // after the callback ran through
        return true;
    }
} 
?>

