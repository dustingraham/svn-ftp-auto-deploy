<?php

class FileSystem
{
    
    private $_temp;
    
    private $config;
    
    public function __construct($config)
    {
        $this->config = $config;
    }
    
    public function addSvnVersion($ver)
    {
        // Add "version_file" version file to "changes" to be uploaded
        $ver_file = $this->getTempFolder() . $this->config['version_file'];
        $this->ensureFolderExists($ver_file);
        file_put_contents($ver_file, $ver);
    }
    
    public function getTempFolder()
    {
        if (empty($this->_temp))
        {
            $this->setupTempFolder();
        }
        
        return $this->_temp;
    }
    
    protected function setupTempFolder()
    {
        $tmp = $_SERVER['TEMP'];
        $dirname = $tmp . DIRECTORY_SEPARATOR  . uniqid('deploy-');
        mkdir($dirname);
        
        $this->_temp = $dirname . DIRECTORY_SEPARATOR;
    }
    
    public function removeTempFolder()
    {
	    if (!empty($this->_temp))
        {
	        $this->unlinkDirectory($this->_temp);
        }
    }
    
    protected function unlinkDirectory($folder)
    {
	    if(is_dir($folder))
	    {
	        $dh = opendir($folder);
	    }
	    
	    if (!$dh)
	    {
		    return false;
	    }
	    
	    while(false !== ($file = readdir($dh)))
	    {
	        if($file != '.' AND $file != '..')
	        {
	            if(!is_dir($folder.$file))
	            {
	                unlink($folder.$file);
	            }
	            else
	            {
	                $this->unlinkDirectory($folder.$file.'/');
	            }
	        }
	    }
	    
	    closedir($dh);
	    rmdir($folder);
	    return true;
	}
	
    public function ensureFolderExists($target)
    {
        $temp = $this->getTempFolder();
        
        $sub = str_replace($temp, '', $target);
        //var_dump($temp, $target, $sub);exit;
        $file = dirname($sub);
        $parts = explode('\\', $file);
        $folder = $temp;
        foreach($parts as $part)
        {
            $folder .= $part . DIRECTORY_SEPARATOR;
            //echo 'Make: ' . dirname($target) . '<br />';
            
            if (!file_exists($folder))
            {
                mkdir($folder);
            }
        }
    }
    
}