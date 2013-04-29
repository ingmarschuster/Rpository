<?php
interface PackedSupplFile
{
	function canHandle($file_path);
	function unpackInto($file_path, $dir_path);
}


function get_content_dir($dir_path) {
    if (!is_dir($dir_path)) {
        throw new Exception($dir_path . ' should be a directory but isn\'t.');
    }
    
    $numEntries = 0;
    $lastEntry = "";
    foreach(new DirectoryIterator($dir_path) as $entry) {
        if (strncmp((string) $entry, '.', 1)) {
            $lastEntry = (string)$entry;
            $numEntries += 1;
           // echo $numEntries." \n".$lastEntry."   \n\n";
        }
    }
    
   // echo "Final " . $numEntries." \n".$lastEntry."   \n\n";
    
    if ($numEntries == 0) {
        //empty directory, just return it
        return $dir_path . '/' . ((string) $lastEntry);
    } elseif ($numEntries == 1) {
        if (!is_dir($dir_path . '/' . ((string) $lastEntry))) {
            //there is no directory below the current one.
            return $dir_path . '/';
        } else {
            //there is only entry in this directory;
            //as it's a subfolder, recurse into it.
            //echo "recursion ".$lastEntry." \n";
            return get_content_dir($dir_path . '/' . $lastEntry);
        }
    } else {
        // there is more than one entry in this directory. Stop.
        return $dir_path . '/';
    }
}

function move_dir_contents($srcDir, $destDir) {

if (file_exists($destDir)) {
  if (is_dir($destDir)) {
    if (is_writable($destDir)) {
      if ($handle = opendir($srcDir)) {
        while (false !== ($file = readdir($handle))) {
	  if (in_array($file, array(".",".."))) {
		continue;
	  } elseif (is_file($srcDir . '/' . $file) || is_dir($srcDir . '/' . $file)) {
            rename($srcDir . '/' . $file, $destDir . '/' . $file);
          }
        }
        closedir($handle);
      } else {
        echo "$srcDir could not be opened.\n";
      }
    } else {
      echo "$destDir is not writable!\n";
    }
  } else {
    echo "$destDir is not a directory!\n";
  }
} else {
  echo "$destDir does not exist\n";
}
}

?>
