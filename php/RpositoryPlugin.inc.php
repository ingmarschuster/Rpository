<?php 
import('classes.plugins.GenericPlugin'); 
class RpositoryPlugin extends GenericPlugin {
    function register($category, $path){
        $success = parent::register($category, $path);
        if($success && $this->getEnabled()){
            HookRegistry::register('articledao::_updatearticle', array(&$this, 'callback_updatearticle'));
            return true;
        }
        return false; 
    }
    
    function getName() { 
        return 'rpository';
    }
    
    function getDisplayName(){
        return 'rpositoryPlugin';
    }
    
    function getDescription(){
        return 'creates R-style packages for published articles';
    }
    
    function getInstallSchemaFile(){
        return $this->getPluginPath() . '/' . 'schema.xml';
    }

    
    function callback_updatearticle($hookName, $args){   
        $sql    =& $args[0]; 
        $params =& $args[1]; 
        $value  =& $args[2];
                
        $articleId      = $params[18];
        $articlePublished  = ($params[6] === 3);
        
        // get references to DAOs needed for the update     
        $daos       =& DAORegistry::getDAOs();
        $articledao =& $daos['ArticleDAO'];
        $article    =& $articledao->getArticle($articleId);
        
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

        
        // do the article update and suppress hookcalls in DAO::update()
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
            if(!mysql_query("DELETE FROM rpository WHERE articleId = $articleId AND current = 1", $db)){
                error_log('OJS - rpository: error deleting DB entry');
            }
           
            
            if(!unlink($path . $row['fileName'])){
                error_log('OJS - rpository: error deleting file:' . $row['fileName']);
            }
        }
        
        
        exec("/opt/Rpository/art_to_repo.sh ". $articleId);
        
        // TODO
        // this is a quick hack.
        // filename should be known here and given as an argument to art_to_repo.sh
        $latest_ctime = 0;
        $latest_filename = '';

        $d = dir($path);
        while (false !== ($entry = $d->read())){
            $filepath = "{$path}/{$entry}";
            if(is_file($filepath) && (filectime($filepath) > $latest_ctime) && ($filepath != "{$path}/PACKAGES") &&($filepath != "{$path}/PACKAGES.gz")){
                $latest_ctime = filectime($filepath);
                $latest_filename = $entry;
            }
        }
        if(!mysql_query("INSERT INTO rpository (articleId, fileName, current, date) VALUES ($articleId, '$latest_filename', 1, CURDATE())", $db)){
            error_log("OJS - rpository: error inserting DB entry " . mysql_error());
        }
        
        // return true to suppress a 2nd article update in DAO::update()
        // after the callback ran through
        return true; 
    }
} 
?>

