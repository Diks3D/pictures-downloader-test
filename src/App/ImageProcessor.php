<?php

namespace App;

use App\Model\Image;

class ImageProcessor
{
    const IMAGE_RESIZE_BY_WIDTH = 'width';
    const IMAGE_RESIZE_BY_HEIGHT = 'height';
    
    protected $watermark = null;

    public function setWatermark($watermark = null)
    {
        $this->watermark = $watermark;
        
        return $this;
    }
    
    public function addWatermark(Image $image){
        $srcImage = imagecreatefromstring($image->rawData);
        $textcolor = imagecolorresolve ($srcImage, 0, 0, 255);
        
        $watermarked = imagestring($srcImage, 3, 5, 5, $this->watermark, $textcolor);
        
        if($watermarked){
            ob_start(); //Start buffer
            //Convert image resource to string
            switch ($image->type) {
                case IMAGETYPE_GIF:
                    imagegif($srcImage);
                    break;
                case IMAGETYPE_JPEG:
                    imagejpeg($srcImage, null, 100);
                    break;
                case IMAGETYPE_WBMP:
                    imagewbmp($srcImage);
                    break;
                case IMAGETYPE_PNG:
                default:
                    $image->ext = '.png';
                    imagepng($srcImage, null, 0);
            }

            $image->rawData = ob_get_contents(); // read from buffer
            ob_end_clean(); // delete buffer
            //Clear resources
            imagedestroy($srcImage);

            $image->watermarked = true;
        }
        
        return $image;
    }
    
    /**
     * Resize image on one of the sides
     * 
     * @param \App\Model\Image $image
     * @param int $size
     * @param string $byDirection
     * @return \App\Model\Image
     */
    public function resizeImage(Image $image, $size, $byDirection = self::IMAGE_RESIZE_BY_HEIGHT)
    {
        $dstWidth = $dstHeight = $size;
        if($byDirection == self::IMAGE_RESIZE_BY_HEIGHT){
            $dstWidth = round(($image->width * $size) / $image->height, 0, PHP_ROUND_HALF_DOWN);
        } else {
            $dstHeight = round(($image->height * $size) / $image->width, 0, PHP_ROUND_HALF_DOWN);
        }
        
        $srcImage = imagecreatefromstring($image->rawData);
        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        
        $resized = imagecopyresized($dstImage, $srcImage, 0, 0, 0, 0, $dstWidth, $dstHeight, $image->width, $image->height);
        
        if($resized){
            ob_start(); //Start buffer
            //Convert image resource to string
            switch ($image->type) {
                case IMAGETYPE_GIF:
                    imagegif($dstImage);
                    break;
                case IMAGETYPE_JPEG:
                    imagejpeg($dstImage, null, 100);
                    break;
                case IMAGETYPE_WBMP:
                    imagewbmp($dstImage);
                    break;
                case IMAGETYPE_PNG:
                default:
                    $image->ext = '.png';
                    imagepng($dstImage, null, 0);
            }

            $image->rawData = ob_get_contents(); // read from buffer
            ob_end_clean(); // delete buffer
            //Clear resources
            imagedestroy($srcImage);
            imagedestroy($dstImage);

            $image->width = $dstWidth;
            $image->height = $dstHeight;
            $image->resized = true;
        }

        return $image;
    }
}
