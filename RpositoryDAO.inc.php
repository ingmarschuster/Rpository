<?php
class RpositoryDAO extends DAO{
    public function getDateFilename($article_id){
        $result =& $this->retrieve('SELECT date, fileName FROM rpository WHERE articleId = ? AND current = 1', array($article_id));
        $return_value = array();
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            
            $return_value['date']       = $row['date'];
            $return_value['filename']   = $row['filename'];
        }
        $result->Close();
        return $return_value;
    }
    
    public function delCurrentEntry($article_id){
        return $this->update('DELETE FROM rpository WHERE articleId = ? AND current = 1', array($article_id));
    }
    
    public function getJournalId($article_id){
        $result =& $this->retrieve('SELECT journals.journal_id FROM journals INNER JOIN articles ON journals.journal_id = articles.article_id WHERE article_id = ?', array($article_id));
        $return_value = NULL;
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            //error_log(print_r($row, true));
            $return_value = $row['journal_id'];
        }
        $result->Close();
        return $return_value;
    }
    
    public function insertNewEntry($article_id, $filename, $pidv1 = NULL, $pidv2 = NULL){
        return $this->update("INSERT INTO rpository (articleId, fileName, current, date, pidv1, pidv2) VALUES (?, ?, 1, CURDATE(), ?, ?)", array($article_id, $filename, $pidv1, $pidv2));
    }
    
    public function getJournalPath($article_id){
        $result =& $this->retrieve('SELECT DISTINCT path FROM journals INNER JOIN articles ON journals.journal_id = articles.article_id WHERE article_id = ?', array($article_id));
        $return_value = NULL;
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            //error_log(print_r($row, true));
            $return_value = $row['path'];
        }
        $result->Close();
        return $return_value;
    }
    
    public function getArtStatement($article_id){
        $result =& $this->retrieve("SELECT published_articles.issue_id, published_articles.date_published, "
                . "S1.setting_value AS sv1, S2.setting_value AS sv2 FROM published_articles "
                . "JOIN article_settings S1 ON published_articles.article_id = S1.article_id "
                . "JOIN article_settings S2 ON published_articles.article_id = S2.article_id "
                . "WHERE S1.setting_name = 'title' AND S2.setting_name = 'abstract' "
                . "AND published_articles.article_id = ? LIMIT 1", array($article_id));
        $row = NULL;
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
        }
        $result->Close();
        return $row;
    }
    
    public function getAuthorStatement($article_id){
        $result =& $this->retrieve("SELECT authors.author_id, authors.primary_contact, authors.seq, "
                ."authors.first_name, authors.middle_name, authors.last_name, authors.country, authors.email "
                ."FROM published_articles JOIN authors ON published_articles.article_id = "
                ."authors.submission_id WHERE published_articles.article_id = ? ORDER BY authors.seq", array($article_id));
        $return_value = array();
        while (!$result->EOF) {
                $row = $result->GetRowAssoc(false);
                $return_value[] = $row;
                $result->MoveNext();
        }
        $result->Close();
        return $return_value;
    }
    
    public function getFileStatement($article_id){
        $result =& $this->retrieve("SELECT F.file_name, F.original_file_name, F.type FROM "
                ."article_files F WHERE F.article_id = ? ORDER BY file_name", array($article_id));
        $return_value = array();
        while (!$result->EOF) {
                $row = $result->GetRowAssoc(false);
                $return_value[] = $row;
                $result->MoveNext();
        }
        $result->Close();
        return $return_value;
    }
    
    /**
     * Check article for recent packages.
     *
     * @param \int $articleId The article in question.
     * 
     * @return \bool Returns TRUE, when there was a package created in the last two days, otherwiese FALSE.
     */
    public function packageCreatedInLast2Days($articleId){
        $result = $this->getDateFilename($articleId);
        if(!array_key_exists('filename', $result)){
            return NULL;
        }
        else{
            $date = new DateTime();
            $today = $date->getTimestamp();
            $lastModified = strtotime($result['date']);

            //  2 days = 8,640,000 msec
            if(($today - $lastModified) < 8640000){
                return $result['filename'];
            }
            else{
                return NULL;
            }
        }
    }
    
    
    /**
     * Check DB for availability of file names.
     *
     * @param \string $filename The file name to check.
     * 
     * @return \bool Returns TRUE, when the file name is available, FALSE otherwise.
     */
    function fileNameAvailable($filename){
        $result =& $this->retrieve("SELECT * FROM rpository "
                ."WHERE fileName = ?", array($filename));
        if($result->EOF){
            return TRUE;
        }
        else{
            return FALSE;
        }
    }
    
    function articleIsPublished($articleId){
        $result =& $this->retrieve("SELECT * FROM published_articles "
                ."WHERE article_id = ?", array($articleId));
        if($result->EOF){
            return FALSE;
        }
        else{
            $row = $result->GetRowAssoc(false);
            if($row['date_published'] != NULL){
                return TRUE;
            }
            else{
                return FALSE;
            }                
        }
    }
    
    function updateRepository($articleId, $writtenArchive){
        $oldFile = $this->packageCreatedInLast2Days($articleId);
	$oldPid = array(NULL, NULL);
        if($oldFile != NULL){
            if(!unlink(OUTPUT_PATH . $oldFile)){
                error_log('OJS - rpository: error deleting file ' . OUTPUT_PATH . $oldFile);
            }
	    $oldPid = array($this->getPidV1($articleId), $this->getPidV2($articleId));
            if(!$this->delCurrentEntry($articleId)){
                error_log('OJS - rpository: error deleting DB entry');
            }
        }        

        $suffix = '';
        if(!$this->fileNameAvailable(basename($writtenArchive) . "_1.0.tar.gz")){
            $suffix = 'a';
            while(!$this->fileNameAvailable(basename($writtenArchive) . $suffix . "_1.0.tar.gz")){
                if($suffix == 'z'){
                    error_log('OJS - rpository: error writing new package to repository');
                    return NULL;
                }
                ++$suffix;
            }
        }
        
        $success = rename($writtenArchive, OUTPUT_PATH . basename($writtenArchive) . $suffix . "_1.0.tar.gz");
        if(!$success){
            error_log('OJS - rpository: error writing new package to repository');
            return NULL;
        }
        unset($success);
        
        $success = $this->insertNewEntry($articleId, basename($writtenArchive) . $suffix . "_1.0.tar.gz", $oldPid[0], $oldPid[1]);
        if(!$success){
            error_log('OJS - rpository: error inserting new package into database');
            return NULL;
        }
	if(!$this->hasPID($articleId)){
	// do pid stuff
	    $success = $this->updatePID($articleId);
	    if(!$success){
		error_log("OJS - rpository: error fetching PID for archive: " . $writtenArchive);
	    }
        }
        return basename($writtenArchive) . $suffix . "_1.0.tar.gz";
    }
    
    function hasPID($articleId) {
	$result =& $this->retrieve("SELECT pidv1, pidv2 FROM rpository "
	                ."WHERE articleId = ? AND current = 1", array($articleId));
	if($result->EOF){
	   return False;
	}
	$row = $result->GetRowAssoc(false);
        if($row['pidv1'] != NULL || $row['pidv2'] != NULL){
	    return True;
	}
	return False;
    }

    function getRPackageFile($articleId){
        $result =& $this->retrieve("SELECT fileName FROM rpository "
                ."WHERE articleId = ? AND current = 1", array($articleId));
        if($result->EOF){
            return NULL;
        }
        else{
            $row = $result->GetRowAssoc();
            if($row['FILENAME'] != NULL){
                return $row['FILENAME'];
            }
            else{
                return NULL;
            }
        }
    }

    function getPidV1($articleId){
        $result =& $this->retrieve("SELECT pidv1 FROM rpository "
                ."WHERE articleId = ? AND current = 1", array($articleId));
        if($result->EOF){
            return NULL;
        }
        else{
            $row = $result->GetRowAssoc(false);
            if($row['pidv1'] != NULL){
                return $row['pidv1'];
            }
            else{
                return NULL;
            }
        }
    }
    
    function getPidV2($articleId){
        $result =& $this->retrieve("SELECT pidv2 FROM rpository "
                ."WHERE articleId = ? AND current = 1", array($articleId));
        if($result->EOF){
            return NULL;
        }
        else{
            $row = $result->GetRowAssoc(false);
            if($row['pidv2'] != NULL){
                return $row['pidv2'];
            }
            else{
                return NULL;
            }
        }
    }
    
    public function getPackageName($article_id){
        $result = $this->retrieve("SELECT fileName FROM rpository " .
                "WHERE articleId = ? AND current = 1", array($article_id));
        $row = NULL;
        if($result->EOF){
            return NULL;
        }
        else{            
            $row = $result->GetRowAssoc(false);
            $result->Close();
            return $row['filename'];
        }
    }
    
    public function getAuthors($submissionId){
        $result = $this->retrieve("SELECT * FROM authors " .
                "WHERE submission_id = ?", array($submissionId));
        $authors = '';
        while(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            //print_r($row);
            $authors .= $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'] . ', ';
            $result->MoveNext();
        }
        $authors = rtrim($authors, ', ');
        return $authors;
    }
    
    public function getDate($articleId){
        $result = $this->retrieve("SELECT date_published FROM published_articles " .
                "WHERE article_id = ? LIMIT 1", array($articleId));
        if(!$result->EOF){
            $row = $result->GetRowAssoc(false);
            return $row['date_published'];
        }
        else{
            return NULL;
        }
    }
    
    function fetchPIDv1($articleId){
        $daos               =& DAORegistry::getDAOs();
        $articleDao         =& $daos['ArticleDAO'];
        
        $url = $this->getPackageName($articleId);
        if($url == ''){
	    error_log("OJS - rpository: getPackageName failed");
            return NULL;
        }
        $fileSize = filesize(OUTPUT_PATH . $url);
        if($fileSize == NULL){
 	    error_log("couldn't get file size");
            return NULL;
        }        
        if($url == NULL){
            error_log("url = null");
            return NULL;
        }
        else{
            $url = REPOSITORY_URL . $url['filename'];
        }
                
        $article = $articleDao->getArticle($articleId);
        $title = $article->getArticleTitle();
        if($title == ''){
	    error_log("couldn't get title");
            return NULL;
        }
        
        $submissionId = $article->_data['id'];
        $authors = $this->getAuthors($submissionId);
        if($authors == ''){
	    error_log("couldn't get authors");
            return NULL;
        }
        
        $date = $this->getDate($articleId);
        if($date == ''){
            error_log("couldn't get publish date");
            return NULL;
        }
        
        // set POST variables
        
        $fields = array('url' => urlencode($url),
            'size' => urlencode($fileSize),
            'title' => urlencode($title),
            'authors' => urlencode($authors),
            'pubdate' => urlencode($date),
            'encoding' => urlencode('xml'));
        $fields_string = '';
        //url-ify the data for the POST
        foreach($fields as $key=>$value){
            $fields_string .= $key.'='.$value.'&';
        }        
        $fields_string = rtrim($fields_string, '&');
        
        // curl stuff
        $ch = curl_init();        
        //curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_URL, PIDV1_SERVICE_URL);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, PIDV1_TIMEOUT);
        curl_setopt($ch, CURLOPT_USERPWD, PIDV1_USER . ":" . PIDV1_PW);
        $result = curl_exec($ch);
        curl_close($ch);
        
        // newlines messed up the xml parser - removing
        $result = str_replace("\\r\\n", '', $result);
        if($result == NULL){
            error_log("OJS - rpository: creating PID failed: no response from PID server");
            return NULL;
        }
        elseif(substr($result, 0, 6)== '<html>'){
            $m = array();
            preg_match_all("/<h1>HTTP Status 403 - Another Handle \/ PID already points here: ([A-F0-9-\/]*)<\/h1>/", $result, $m );
            if($m[1] == ''){
                error_log("OJS - rpository: fetching PID failed: " . $e->getMessage());
                return NULL;
            }
            else{
                //error_log(print_r($m, TRUE));
                return $m[1][0];
            }
        }
        
        
        
        try{
            $xml = new SimpleXMLElement($result);
        }
        catch(Exception $e){
            error_log("OJS - rpository: unexpected PID v1 server response");
            return NULL;
        }
        $out = $xml->Handle->pid;
        
        return (String)$out;
    }
    
    function fetchPIDv2($articleId){
        $url = $this->getPackageName($articleId);
        if($url == ''){
            return NULL;
        }
        $url = REPOSITORY_URL . $url;

        $data = '[{"type":"URL","parsed_data":"' . $url . '"}]';
        $ch = curl_init();
        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, PIDV2_SERVICE_URL);
        curl_setopt($ch,CURLOPT_USERPWD, PIDV2_USER .":".PIDV2_PW);
        curl_setopt($ch, CURLOPT_TIMEOUT, PIDV2_TIMEOUT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept:application/json', 'Content-Type:application/json'));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $data);

        $result = curl_exec($ch);
        curl_close($ch);
        //error_log(print_r($result, TRUE));
        $m = array();
        preg_match_all('/<dd><a href="([A-F0-9-]*)">/', $result, $m );
        if($m[1] == ''){
            return NULL;
        }
        else{
            return PIDV2_PREFIX . "/" . $m[1][0];
        }
    }
    
    function updatePIDv1($article_id, $pid){     
        return $this->update("UPDATE rpository SET pidv1=? WHERE articleId=? AND current=1", array($pid, $article_id));        
    }
    
    function updatePIDv2($article_id, $pid){     
        return $this->update("UPDATE rpository SET pidv2=? WHERE articleId=? AND current=1", array($pid, $article_id));        
    }
    
    function updatePID($articleId){
        /*$pidv1 = $this->fetchPIDv1($articleId);
        //error_log("pidv1 response: " . print_r($pidv1, TRUE));
        if($pidv1 == NULL){
            error_log("OJS - rpository: error fetching pidv1");
        }
        else{
            $this->updatePIDv1($articleId, $pidv1);
        }
	*/
        
        $pidv2 = $this->fetchPIDv2($articleId);
        if($pidv2 == NULL){
            error_log("OJS - rpository: error fetching pidv2");
        }
        else{
            $this->updatePIDv2($articleId, $pidv2);
        }
        return TRUE;
    }
}
?>
