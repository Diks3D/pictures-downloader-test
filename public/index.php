<?php
/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

define('ROOT_PATH', realpath(__DIR__ . '/..'));

// Setup autoloading
require 'init_autoloader.php';
$loader = new Zend\Loader\StandardAutoloader();
$loader->registerNamespace('App', ROOT_PATH . '/src/App');
$loader->register();

$db = new SQLite3(ROOT_PATH . DIRECTORY_SEPARATOR . 'storage.sqlite');
$imageStorage = new \App\Model\ImageStorage($db, 'public/gallery', '/gallery');

$request = new Zend\Http\PhpEnvironment\Request();
$response = new Zend\Http\PhpEnvironment\Response();

if ($request->isXmlHttpRequest()) {
    $response->getHeaders()->addHeaders(array('Content-Type' => 'application/json'));
    
    $imageDownloader = new \App\ImageDownloader();
    $imageProcessor = new \App\ImageProcessor();
    $imageProcessor->setWatermark('Diks3D');
    
    if($request->isPost()){
        //Check and get upload file
        $files = $request->getFiles('files', false);
        if (!$files || count($files) == 0 || $files[0]['error'] !== 0 || $files[0]['type'] !== 'text/plain') {
            $response->setStatusCode(400);
            $response->setContent(json_encode(array('message' => 'Invalid uploaded file')));
            return $response->send();
        }

        //getsource urls fron uploaded file
        $uploadedFilename = $files[0]['tmp_name'];
        $fp = fopen($uploadedFilename, 'r');
        $urls = array();
        while (!feof($fp)) {
            $urls[] = trim(fgets($fp));
        }
        fclose($fp);
        
        //Remove doubles
        $urls = $imageStorage->removeDoubles($urls);

        //Processed and save valid images
        foreach ($urls as $url) {
            //Get image from remote server
            $imageRawData = $imageDownloader->getImageData($url);
            $image = new \App\Model\Image($url, $imageRawData);

            //Resize image for config settings
            $imageProcessor->resizeImage($image, 200, \App\ImageProcessor::IMAGE_RESIZE_BY_HEIGHT);

            //Add watermark
            $imageProcessor->addWatermark($image);

            //Save image in local storage
            $fileName = $imageStorage->urlToFileName($url);
            $imageStorage->saveImage($image, $fileName);
            $images[] = $image;
        }
    }

    $images = array();
    $collection = $imageStorage->getCollection();
    foreach ($collection as $image) {
        $images[] = $image->getPublicInfo();
    }

    $response->setStatusCode(200);
    $response->setContent(json_encode(array('images' => $images)));
} else {
    $images = $imageStorage->getCollection();
    
    $twigLoader = new \Twig_Loader_Filesystem(array(ROOT_PATH . '/src/view'));
    $twig = new \Twig_Environment($twigLoader, array(
        'cache' => false //ROOT_PATH . '/cache/twig',
    ));
    
    $response->setContent($twig->render('index.html.twig', array('images' => $images)));
}

return $response->send();