<?php
require_once('descr_escape.php');

class PackageDescription{
    private $entries;
    private $enc;
    private $repr;
    
    // constructor
    public function __construct(){
        $this->entries  = array();
        $this->enc      = 'US-ASCII';
        $this->repr     = null;
    }
    
    // TODO: still necessary?
    public function getEncoding(){
        return $this->enc;
    }
    
    public function get($fieldName){
	if (array_key_exists($fieldName, $this->entries)){
	    return $this->entries[$fieldName];
	} else {
	   return NULL; 
	}
    }
    
    public function set($fieldName, $content){
        $this->repr = null;
	if ($fieldName == 'Description') {
	    $content = strip_tags($content);
	}
        $this->entries[$fieldName] = descr_escape($content);
    }
    
    public function __toString(){
        if($this->repr === null){
            $sb = '';
            foreach($this->entries as $key => $value){
                $sb .= $key . ': ' . $value . "\n";
            }
            $this->repr = $sb;
        }
    return $this->repr;
    }
    
    // write the package description to a temp file and return the path to it
    public function toTempFile(){        
        if(!((array_key_exists('Maintainer', $this->entries) && array_key_exists('Author', $this->entries)) || array_key_exists("Authors@R", $this->entries))){
            error_log("OJS - rpository: Neither Author/Maintainer nor Authors@R set in Package DESCRIPTION file.");
            return null;
        }
        if(!array_key_exists('Package', $this->entries)){
            error_log("OJS - rpository: field 'Package' not set in Package DESCRIPTION file.");
            return null;
        }
        if(!array_key_exists('Version', $this->entries)){
            error_log("OJS - rpository: field 'Version' not set in Package DESCRIPTION file.");
            return null;
        }
        if(!array_key_exists('License', $this->entries)){
            error_log("OJS - rpository: field 'License' not set in Package DESCRIPTION file.");
            return null;
        }
        if(!array_key_exists('Description', $this->entries)){
            error_log("OJS - rpository: field 'Description' not set in Package DESCRIPTION file.");
            return null;
        }
        if(!array_key_exists('Title', $this->entries)){
            error_log("OJS - rpository: field 'Title' not set in Package DESCRIPTION file.");
            return null;
        }
        
        $fName  = tempnam('', 'rpo');
        $f      = fopen($fName, 'w');
        fwrite($f, $this->__toString());
        
        fclose($f);
        return $fName;
    }
}
?>
