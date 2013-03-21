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
    
    public function insertNewEntry($article_id, $filename){
        return $this->update("INSERT INTO rpository (articleId, fileName, current, date) VALUES (?, ?, 1, CURDATE())", array($article_id, $filename));
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
}
?>
