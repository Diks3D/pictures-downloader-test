<?php

namespace App\Model;

class Image {
    public $type;
    public $width;
    public $height;
    public $resized = false;
    public $watermarked = false;
    public $filePath;
    public $sourceUrl = null;
    public $url;
    public $ext;
    public $mime;
    public $rawData = null;
    
    public function __construct($sourceUrl, $rawImageData)
    {
       $this->sourceUrl = $sourceUrl;
       $imageInfo = getimagesizefromstring($rawImageData);
       $this->width = $imageInfo[0];
       $this->height = $imageInfo[1];
       $this->type = $imageInfo[2];
       $this->mime = $imageInfo['mime'];
       $this->ext = image_type_to_extension($imageInfo[2]);
       $this->rawData = $rawImageData;
    }
    
    /**
     * Return only public info about image as array
     * 
     * @return array
     */
    public function getPublicInfo(){
        return array(
            'width' => $this->width,
            'height' => $this->height,
            'url' => $this->url,
        );
    }
    
    public function __toString() {
        return json_encode($this->getPublicInfo());
    }
}
