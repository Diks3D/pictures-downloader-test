<?php

namespace App;

use Zend\Http\Client;

class ImageDownloader
{
    protected $httpClient;
    
    public function __construct($options = array()) {
        $httpOptions = array_merge(array(
            'maxredirects' => 0,
            'timeout' => 30
                ), $options);

        $client = new Client();
        $client->setOptions($httpOptions);

        $this->httpClient = $client;
    }
    
    public function getImageData($url)
    {
        $this->httpClient->setUri($url);
        $response = $this->httpClient->send();
        
        if($response->isNotFound()){
            throw new ImageDownloaderException('Image not found from server', $response->getStatusCode());
        }
        
        return $response->getBody();
    }
}

class ImageDownloaderException extends \Exception
{
    
}