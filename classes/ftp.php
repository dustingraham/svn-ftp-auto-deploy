<?php

class Ftp
{
    
    /**
     * @var FileSystem
     */
    private $fs;
    
    /**
     * @var array
     */
    private $config;
    
    public function __construct($fs, $config)
    {
        $this->fs = $fs;
        $this->config = $config;
    }
    
    protected function log($msg)
    {
        if ($this->config['verbose'])
        {
            echo "\r\n[FTP] " . $msg . "\r\n";
        }
    }
    
    public function getCurrentVersion()
    {
	    $conn_id = $this->ftpGetConnection();
        
	    $this->log('Connected:' . $conn_id);
	    
	    $this->ftpGoDir($conn_id, $this->config['ftp_root']);
	    
	    $this->log('Went to:' . $this->config['ftp_root']);
        
	    $temp = $this->fs->getTempFolder();
        
	    $this->log('Temp is: '.$temp);
	    
	    $this->log('PWD is: '.ftp_pwd($conn_id));
	    
        $filepath = substr($this->config['version_file'], 1);
        // $this->config['ftp_root'].'  '.$this->config['version_file']
	    $this->log('Attempt GET: '.$filepath);
	    
        //ftp_get($conn_id, $temp.$this->config['version_file'], $this->config['ftp_root'].$this->config['version_file'], FTP_BINARY);
        $success = ftp_get($conn_id, $temp.$this->config['version_file'], $filepath, FTP_BINARY);
        
        if (!$success)
        {
            $this->log('GET Failed');
            exit;
        }
        
        $this->log('GET Success');
        
	    $data = file_get_contents($temp.$this->config['version_file']);
        
        $this->log('FTP Version: ' . $data);
	    
        return $data;
    }
    
    public function putChanges($changes)
    {
        $conn_id = $this->ftpGetConnection();
        //echo ftp_pwd($conn_id);exit;
        
        foreach($changes as $change)
        {
	        // We want to strip the svn's subfolder from the change.
	        // because that subfolder is exported to the $temp folder.
	        $source = substr($change, strlen($this->config['svn_subfolder'])+1);
	        $source = $this->fs->getTempFolder() . str_replace('/','\\', $source);
            $source = str_replace('\\','\\\\', $source);
            
            // The ftp destination directory.
            $destination = $this->config['ftp_root'] . substr($change, strlen($this->config['svn_subfolder']));
            
            $this->ftpGoDir($conn_id, dirname($destination));
            
            if (is_dir($source))
            {
                // There was a change in folder attributes...?
                // "goDir" will create the directory at least.
                // Upload would fail.
                continue;
            }
            
            //echo $dir;
            //echo ftp_pwd($conn_id) . '<br />';
            //echo ftp_chdir($conn_id, $dir) . '<br />';
            //echo ftp_pwd($conn_id) . '<br />';
            
            // upload the file
            //$upload = false;
            //$upload = ftp_put($conn_id, $destination, $source, FTP_ASCII);
			$this->log('Source: '.$source);
			$this->log('Destination: '.$source);
			
            $upload = ftp_put($conn_id, $destination, $source, FTP_BINARY); 
            
            //var_dump($upload, $change, $destination, $source);
            
            // check upload status
            if (!$upload)
            { 
                echo "FTP upload has failed! ( " . $upload . " )\r\nReconnecting...\r\n";
                
                // Try to Re-Aquire Connection
                ftp_close($conn_id);
                $conn_id = $this->ftpGetConnection();
                
                echo 'Connection aquired, navigating to directory.'."\r\n";
                
                $this->ftpGoDir($conn_id, dirname($destination));
                
                $upload = ftp_put($conn_id, $destination, $source, FTP_BINARY); 
                
                if (!$upload)
                {
	                echo 'Could not upload on second try, exiting.'."\r\n";
	                var_dump($destination, $source);
	                exit;
                }
                
            }
            else
            {
                //echo "Uploaded $source to $destination <br />";
                echo "Up: $destination \r\n";
            }
        }
        
        
        // close the FTP stream 
        ftp_close($conn_id); 
    }
    
    protected function ftpGoDir($conn_id, $dir)
    {
        $parts = explode('/', ltrim($dir, '/'));
        
        $current = '/';
        ftp_chdir($conn_id, $current);
        
        foreach($parts as $part)
        {
            //var_dump(ftp_pwd($conn_id));
            //var_dump($dir, $current, $part);
            
            $current .= $part . '/';
            // Try to navigate
            if (@ftp_chdir($conn_id, $current))
            {
                continue;
            }
            
            // Doesn't exist, make it.
            ftp_mkdir($conn_id, $current);
            ftp_chdir($conn_id, $current);
        }
    }
    
    
    protected function ftpGetConnection()
    {
        $ftp_server = $this->config['server'];
        $ftp_user = $this->config['user'];
        $ftp_pass = $this->config['pass'];
        
        // set up basic connection
        $conn_id = ftp_connect($ftp_server); 
        
        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user, $ftp_pass); 
        
        // check connection
        if ((!$conn_id) || (!$login_result))
        { 
            echo "FTP connection has failed! <br />";
            echo "Attempted to connect to $ftp_server for user $ftp_user";
            exit; 
        }
        else
        {
            echo 'Connected to [[ '.$ftp_server . ' ]] for user [[ '.$ftp_user.' ]]'."\r\n";
        }
        
        // Passive Connection
        ftp_pasv($conn_id, true);
        
        return $conn_id;
    }
}

