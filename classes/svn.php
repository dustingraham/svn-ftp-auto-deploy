<?php

class Svn
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
    
    public function checkoutChanges($rVer)
    {
        $changes = $this->getRecentChanges($rVer);
        
        foreach($changes as $f)
        {
            $path = $this->config['svn_root'].$f;
            
            // Strip /trunk/wwwroot/ and change / to \
            $file = substr($f, strlen($this->config['svn_subfolder'])+1);
            $target = $this->fs->getTempFolder() . str_replace('/','\\', $file);
            
            //var_dump($target, $file, $f, $path); exit;
            
            // Ensure Directory Exists
            $this->fs->ensureFolderExists($target);
            
            $cmd = 'svn export '.$path.' '.$target;
            exec($cmd);
            
            //var_dump($cmd);
        }
        
        $svn_ver = $this->getSvnVersion();
        $this->fs->addSvnVersion($svn_ver);
        
        return $changes;
    }
    
    protected function getRecentChanges($rVer)
    {
        $raw_log = $this->getChangeLog($rVer);
        
        $changes = $this->getChangeArr($raw_log);
        
        return array_unique($changes);
    }
    
    public function getCurrentVersion()
    {
        return $this->getSvnVersion();
    }
    
    protected function getSvnVersion()
    {
        $cmd = 'svn info '.$this->config['svn_root'];
        $x = exec($cmd, $result);
        
        $str = implode(' ', $result);
        
        //var_dump($result,$cmd, $x, $str);
        preg_match('/Revision: ([0-9]+)/', $str, $matches);
        
        return $matches[1];
    }
    
    protected function getChangeLog($remote_ver)
    {
        //$remote_ver = $this->getRemoteVersion();
        $remote_ver++;
        
        // We want the subfolder here because we only need to export
        // the files that should be uploaded.
        $repo = $this->config['svn_root'].$this->config['svn_subfolder'];
        
        $cmd = 'svn log ' . $repo . ' -v -r'.$remote_ver.':HEAD';
        echo $cmd . "\r\n";
        
        $out = null;
        $return = null;
        $exec = exec($cmd, $out);
        
        return $out;
    }
    
    protected function getChangeArr($lines)
    {
        ob_start();
        
        /*Patterns for svn log  conversion*/
        $svnRevStart = "/\-{72,}/"; //72 dashes(-)
        $noOfChanges = "/\|\ (\d)+\ line(s)*/";  //get 3 lines from the text "r2 | palanirajaap | 2008-09-18 16:08:12 +0530 (Thu, 18 Sep 2008) | 3 lines" //but never used
        $fileStatus = "/(\ ){3}[A-Z]{1}/";  //"   M /lib/templates/admin/login.html"
        $skipStatusChars = 5;  //get the file, by skipping "...M.";
        
        //The task
        //$lines = explode("\n",$svnlog);
        echo "\r\n". 'SVN Export Details' . "\r\n";
        //print_r($lines);
        
        $files = array();
        $delFiles = array();
        $filesWithStatus = array();
        $comments = false;
        
        $totLines = count($lines)-1; //skip the last line of 72 dashes(-);
        for($i=0;$i<$totLines;$i++)
        {
            //echo "\nInside FOR i = $i";
            $curLine = $lines[$i];
            //remove \r and \n
            $curLine = str_replace("\r", "", $curLine);
            $curLine = str_replace("\n", "", $curLine);
            
            //echo "\nLine $i has length : ".strlen($curLine);//get the empty line between files and comment
            if(!strlen($curLine))
            {
                $comments = true;
                continue;//skip the empty line
            }
            //check if it is begining of the revision
            preg_match($svnRevStart, $curLine, $matches);
            if(count($matches))
            {
                $comments = false;
                echo "\nVersion start at $i. ";//var_dump($matches);
                $i++; //get the meta tags
                $metaTags = $lines[$i];
                echo "\n\tMetaTags at $i: ".$metaTags;
                //var_dump($curLine);
                //skip the "changed paths"
                $i++;
            }
            else
            {
                //get the list
                if($comments)
                {
                    //skip the comments 
                    continue;
                }
                else
                {
                    //$files[] = $curLine;
                    //split the Status and File
                    preg_match($fileStatus, $curLine, $status); //will return array("   M"," ")
                    $sts = trim($status[0]);
                    //skip the file if it is deleted
                    $file = trim(substr($curLine, $skipStatusChars)); //get the file, by skipping "...M."; 
                    
                    // Ensure we don't copy files outside of the svn_subfolder
                    if (strpos($file, $this->config['svn_subfolder']) === false)
                    {
                        // Found external file
                        echo 'External, Skipping: ' . $f . "\r\n";
                        continue;
                    }
                    
                    $fileWithStatus = $sts."@@".$file;
                    //print_r($fileWithStatus);
                    if($sts == 'D')
                    {
                        $delFiles[] = $file;
                    }
                    else
                    {
                        $files[] = $file;
                    }
                }
            }
        }
        echo "\r\n".'Completed SVN Parsing'."\r\n";
        $info = ob_get_clean();
        
        echo $info;
        
        // Map old path to new path.
        //foreach($files as $k => $v) $files[$k] = map($v);
        
        return $files;
    }
    
}

