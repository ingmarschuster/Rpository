<?php
class ZipSupplFile implements PackedSupplFile
{
	function canHandle($file_path)
	{
		$finfo = new finfo(FILEINFO_MIME);
		$fType = $finfo->file($file_path);
		$pattern = "/application\/zip/";
		
		if(preg_match($pattern, $fType)) return true;
		
		return false;
	}
	
	function unpackInto($file_path, $dir_path)
	{
		$zip = zip_open($file_path);
        if (!is_dir($dir_path)) {
            throw new Exception($dir_path . ' should be a directory but isn\'t.');
        }
		
		if($zip)
		{
			while($zip_entry = zip_read($zip))
			{
				zip_entry_open($zip, $zip_entry);
				if (substr(zip_entry_name($zip_entry), -1) == '/')
				{
                    //this $zip_entry is a directory. create it.
					$zdir = substr(zip_entry_name($zip_entry), 0, -1);
					mkdir($dir_path.'/'.$zdir);
				}
				else{
					$file = basename(zip_entry_name($zip_entry));
					$fp = fopen($dir_path.'/'.zip_entry_name($zip_entry), "w+");
					//echo zip_entry_name($zip_entry);
					
					if (zip_entry_open($zip, $zip_entry, "r")) {
						$buf = zip_entry_read($zip_entry, zip_entry_filesize($zip_entry));
						zip_entry_close($zip_entry);
					}
       
					fwrite($fp, $buf);
					fclose($fp);

				}
			}
			zip_close($zip);
		}
	}
}
?>
