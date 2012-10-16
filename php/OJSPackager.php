<?php
require_once('PackageDescription.php');
require_once('Tar.php');

class OJSPackager{
    //private $filesStmt;
    //private $articleStmt;
    private $db;
    private $repoPath;
    private $filesPath;
    
    //private $dbHost;
    //private $dbLogin;
    //private $dbPassword;
    //private $dbName;
    //private $db;
    
    public function __construct($repoPath, $filesPath){
        $this->repoPath     = $repoPath;
        $this->filesPath    = $filesPath;
        
        $daos       =& DAORegistry::getDAOs();
        $articledao =& $daos['ArticleDAO'];
    
        $this->dbHost     = $articledao->_dataSource->host;
        $this->dbLogin    = $articledao->_dataSource->user;
        $this->dbPassword = $articledao->_dataSource->password;
        $this->dbName     = $articledao->_dataSource->database;
        $this->db         = mysql_connect($this->dbHost, $this->dbLogin, $this->dbPassword);
        /*
        $this->filesStmt  = 'SELECT F.file_name, F.original_file_name, F.type '
            . 'FROM article_files F WHERE F.article_id = ? ORDER BY file_name';
        $this->articleStmt= 'SELECT published_articles.issue_id,' 
                . 'published_articles.date_published, S1.setting_value, '
                . 'S2.setting_value FROM published_articles JOIN'
                . 'article_settings S1 ON published_articles.article_id = '
                . 'S1.article_id JOIN article_settings S2 ON '
                . 'published_articles.article_id = S2.article_id '
                . 'WHERE S1.setting_name = "title" AND S2.setting_name = '
                . '"abstract" AND published_articles.article_id = ? LIMIT 1';
         * 
         */
    }
    
    // returns true if $str begins with $sub
    private function beginsWith($str, $sub){
        return(substr($str, 0, strlen($sub)) == $sub);
    }
    
    function randomStringGen($length){
        $random= "";
        srand((double)microtime()*1000000);
        $char_list = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $char_list .= "abcdefghijklmnopqrstuvwxyz";
        // Add the special characters to $char_list if needed
        for($i = 0; $i < $length; $i++){    
            $random .= substr($char_list,(rand()%(strlen($char_list))), 1);  
        }  
        return $random;
    }
    
    function deleteDirectory($dir){
        if(!file_exists($dir))
            return true;
        if (!is_dir($dir)) 
            return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') 
                continue;
            if (!$this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) 
                return false;
        }
        return rmdir($dir);
    }


    
    public function writePackage($article_id){    
        $suppPath = $this->filesPath    . "/" . $article_id . "/supp/";
        $preprPath = $this->filesPath   . "/" . $article_id . "/public/";
        $pd = new PackageDescription();
        $authors = "c(";
        $pkgName = "";
        
        $result_journal_path = mysql_query("SELECT path FROM journals INNER JOIN articles ON journals.journal_id = articles.article_id WHERE article_id = $article_id");
        $row_journal_path = mysql_fetch_array($result_journal_path);
        $pd->set("Repository", $row_journal_path['path']);
        $pd->set("Depends", "R (>= 2.14)");
        
        $result_artStmt = mysql_query("SELECT published_articles.issue_id, published_articles.date_published, "
                . "S1.setting_value, S2.setting_value FROM published_articles "
                . "JOIN article_settings S1 ON published_articles.article_id = S1.article_id "
                . "JOIN article_settings S2 ON published_articles.article_id = S2.article_id "
                . "WHERE S1.setting_name = 'title' AND S2.setting_name = 'abstract' "
                . "AND published_articles.article_id = $article_id LIMIT 1");
        $row_artStmt = mysql_fetch_array($result_artStmt);
        
        $pd->set("Date", $row_artStmt[1]);
        $pd->set("Title", $row_artStmt[2]);
        $pd->set("Description", $row_artStmt[3]);
        
        $result_authorStmt = mysql_query("SELECT authors.author_id, authors.primary_contact, authors.seq, authors.first_name, authors.middle_name, authors.last_name, authors.country, authors.email FROM published_articles JOIN authors ON published_articles.article_id = authors.submission_id WHERE published_articles.article_id = $article_id ORDER BY authors.seq");
        
        while($row_authorStmt = mysql_fetch_array($result_authorStmt)){
            if($this->beginsWith($authors, "c(person(")){
                $authors.=", ";
            }
            $authors .= 'person(';
            $authors .= 'given ="'. $row_authorStmt[3] . '", family = "' . $row_authorStmt[5] . '"';
            $pkgName .= $row_authorStmt[5];
            if($row_authorStmt[4] != NULL && strlen($row_authorStmt[4]) > 0){
                $authors.=', middle = "' . $row_authorStmt[4] . '"';
            }
            if($row_authorStmt[7] != NULL && strlen($row_authorStmt[7]) > 0){
                $authors.=', email = "' . $row_authorStmt[7] . '"';
            }
            $authors.=', role = c("aut"';
            if($row_authorStmt[1] == 1){
                // primary_contact
                $authors.=', "cre"';
            }
            $authors.='))';
        }
        $pd->set("Author@R", $authors);
        $temp = explode('-', $pd->get("Date"));
        $pkgName = $temp[0] . $pkgName;
        unset($temp);
        $pd->set("Package", $pkgName);
        
        // path to write the package to
        $archive = $this->repoPath . '/' . $pkgName . '_1.0.tar.gz';
        
        
        
        // TODO
        $pd->set("Version", "1.0");
        $pd->set("License", "GPL (>=3)");
        
        $randomDirName = 'rpo-' . $this->randomStringGen(20);
        mkdir(sys_get_temp_dir() . '/' . $randomDirName);
        $tempDir = sys_get_temp_dir() . '/' . $randomDirName;
        rename($pd->toTempFile(), $tempDir .'/' . 'DESCRIPTION');
        $pw = new Archive_Tar($archive, 'gz');
        
        
        $result_fileStmt = mysql_query("SELECT F.file_name, F.original_file_name, F.type FROM article_files F WHERE F.article_id = $article_id ORDER BY file_name");
        $submissionPreprintName = '';
        while($row_fileStmt = mysql_fetch_array($result_fileStmt)){
            $name       = $row_fileStmt[0];
            $origName   = $row_fileStmt[1];
            $type       = $row_fileStmt[2];
            
            if($type == 'supp'){
                if(!is_dir($tempDir . '/' . 'inst')){
                    mkdir($tempDir . '/' . 'inst');
                }
                if(!copy($suppPath . $name, trim($tempDir . '/' . 'inst' . '/' . $origName))){
                    error_log('OJS - rpository: error copying file: ' .$suppPath . $name . ' to: ' . trim($tempDir . '/' . 'inst' . '/' . $origName));
                }
            }
            elseif($type == 'submission/original'){
                $submissionPreprintName = $origName;
            }
            elseif($type == 'public'){
                if(!is_dir($tempDir . '/' . 'inst' . '/' . 'preprint')){
                    mkdir($tempDir . '/' . 'inst' . '/' . 'preprint');
                }
                copy($preprPath . $name, trim($tempDir . '/' . 'inst' . '/' . 'preprint' . '/' . $submissionPreprintName));
            }
                
            
        }
        if(!$pw->createModify($tempDir, "$pkgName" . '/', $tempDir)){
            error_log("OJS - rpository: error writing archive");
        }
        $this->deleteDirectory($tempDir);
        return $pkgName . '_1.0.tar.gz';
    }
 }
?>
