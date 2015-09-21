<?php

namespace App\Model;

use App\Model\Image;

class ImageStorage
{
    /* @var \SQLite3 */
    protected $db = null;
    protected $tableName = 'images';
    protected $storage = null;
    protected $urlPrefix = null;

    public function __construct(\SQLite3 $db, $path = null, $urlPrefix = null)
    {
        $this->db = $db;
        $this->checkDb();
        
        if(!is_null($path)){
            $this->setStoragePath($path);
        }
        if(!is_null($urlPrefix)){
            $this->setUrlPrefix($urlPrefix);
        }
    }
    
    /**
     * Set path for store image files
     * 
     * @param string $path
     * @return \App\Model\ImageStorage
     */
    public function setStoragePath($path)
    {
        $relativePath = ROOT_PATH . DIRECTORY_SEPARATOR . $path;
        if(!is_dir($relativePath)){
            mkdir($relativePath, 0775, true);
        }
        $this->storage = realpath($relativePath);
        
        return $this;
    }
    
    /**
     * Set root url prefix for images
     * 
     * @param string $prefix
     * @return \App\Model\ImageStorage
     */
    public function setUrlPrefix($prefix)
    {
        $this->urlPrefix = $prefix;
        
        return $this;
    }
    
    /**
     * Check db for ready for work
     */
    protected function checkDb() {
        $tableName = $this->tableName;
        $result = $this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName'");
        if (!$result->fetchArray()) {
            $sqlQuery = 'CREATE TABLE ' . $tableName  . ' ('
                . 'source_url VARCHAR(255), '
                . 'file_path VARCHAR(255), '
                . 'web_url VARCHAR(255) '
                . ')';
            $this->db->exec($sqlQuery);
            $this->db->exec("CREATE UNIQUE INDEX source_url ON {$this->tableName}(source_url)");
        }
    }
    
    /**
     * Get canonical filename from source url
     * 
     * @param type $url
     * @return type
     */
    public static function urlToFileName($url)
    {
        $urlParts = explode('/', $url);
        $fullFileName = urldecode($urlParts[count($urlParts) - 1]);
        $rawFileName = strtolower(substr($fullFileName, 0, strrpos($fullFileName, '.')));
        $fileName = str_replace(array(' ', '-'), '_', $rawFileName);
        return $fileName;
    }
    
    /**
     * Remove urls which already are in a database
     * 
     * @param array $sourceUrls
     */
    public function removeDoubles(array $sourceUrls)
    {
        $srcUrlsString = "'" . implode("','", $sourceUrls) . "'";
        $result = $this->db->query("SELECT source_url FROM {$this->tableName} WHERE source_url IN($srcUrlsString)");
        $dbSourceUrls = array();
        while($dbRow = $result->fetchArray()){
            $dbSourceUrls[] = $dbRow['source_url'];
        }
        
        return array_diff($sourceUrls, $dbSourceUrls);
    }

    /**
     * Save image information in database and image file on system
     * 
     * @param Image $image
     * @param string $fileName
     * @return Image
     * @throws \Exception
     */
    public function saveImage(Image $image, $fileName)
    {   
        if ($image->resized && $image->watermarked) {
            $fullFilePath = $this->storage . DIRECTORY_SEPARATOR . $fileName . $image->ext;
            file_put_contents($fullFilePath, $image->rawData);
            $image->filePath = $fullFilePath;
            $image->url = $this->urlPrefix . '/' . $fileName . $image->ext;

            $parameters = array(
                'source_url' => $image->sourceUrl,
                'file_path' => $image->filePath,
                'url' => $image->url,
            );
            $sqlData = "'" . implode("','", $parameters) . "'";
            $dbResult = $this->db->exec("INSERT INTO {$this->tableName} VALUES ($sqlData)");
            if (!$dbResult) {
                throw new \Exception($this->db->lastErrorMsg(), $this->db->lastErrorCode());
            }
        }
        
        return $image;
    }
    
    /**
     * Delete image entity from system
     * 
     * @param Image $image
     * @return \App\Model\ImageStorage
     */
    public function deleteImage(Image $image)
    {
        if(file_exists($image->filePath)){
            unlink($image->filePath);
        }
        $sourceUrl = $image->sourceUrl;
        $this->db->exec("DELETE FROM {$this->tableName} WHERE source_url = '{$sourceUrl}'");
        
        return $this;
    }
    
    /**
     * Get one image from storage by source url
     * 
     * @param type $sourceUrl
     * @return type
     * @throws \Exception
     */
    public function getImage($sourceUrl)
    {
        $result = $this->db->query("SELECT * FROM {$this->tableName} WHERE source_url = '{$sourceUrl}'");
        $dbRow = $result->fetchArray();
        if($dbRow){
            $check = $this->checkImage($dbRow);
            if($check){
                return $this->fillImage($dbRow);
            }
        }
        
        throw new \Exception('File not found');
    }
    
    /**
     * Get all images, stored in system
     * 
     * @return array
     */
    public function getCollection()
    {
        $collection = array();
        $result = $this->db->query("SELECT * FROM {$this->tableName}");
        while($dbRow = $result->fetchArray()){
            $check = $this->checkImage($dbRow);
            if($check){
                $collection[] = $this->fillImage($dbRow);
            } else {
                $sourceUrl = $dbRow['source_url'];
                $this->db->exec("DELETE FROM {$this->tableName} WHERE source_url = '{$sourceUrl}'");
            }
        }

        return $collection;
    }
    
    /**
     * Drop all database rows and files in storage
     */
    public function clearStorage()
    {
        $this->db->exec("DELETE FROM {$this->tableName}");
        $list = scandir($this->storage);
        foreach($list as $fileName){
            $fullFilePath = $this->storage . DIRECTORY_SEPARATOR . $fileName;
            if($fileName != '.'
                    && $fileName != '..'
                    && strpos($fileName. '.') !== 0 //Without hidden files
                    && is_file($fullFilePath)){ //Only regular files
                unlink($fullFilePath);
            }
        }
    }
    
    /**
     * Create a Imgage instance from db row data
     * 
     * @param array $dbRow
     * @return \App\Model\Image
     */
    protected function fillImage($dbRow)
    {
        $image = new Image($dbRow['source_url'] ,file_get_contents($dbRow['file_path']));
        $image->url = $dbRow['web_url'];
        $image->filePath = $dbRow['file_path'];
        $image->resized = true;
        $image->watermarked = true;
        
        return $image;
    }
    
    /**
     * Check is file of image stored on file system
     * 
     * @param array $dbRow
     * @return boolean
     */
    protected function checkImage($dbRow)
    {
        return (file_exists($dbRow['file_path']) && getimagesize($dbRow['file_path']));
    }
}