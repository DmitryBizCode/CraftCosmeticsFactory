<?php
require 'vendor/autoload.php';

use App\Services\Legacy\ContainerService;
use App\Services\Common\Router;
use App\Services\Database\SQLService;
use App\Services\Common\TemplateService;

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$method = $_SERVER['REQUEST_METHOD'];
$data = $_POST;
$sign = $_GET['sign'] ?? null;
$image = $_FILES;
$router = new Router();
$id_containers = $_GET['id_containers'] ?? null;
try{
    $router->route($action, $id, $method, $data,$image,$sign,$id_containers);
}
catch(\Exception $e){
    echo $e->getMessage();
}

//$mail = new \App\Services\Mail();
//$mail->sendMail("dimapryme@gmail.com","Dmitry Beznosiuk", "hello its me charlie");
//$templateService = new TemplateService();
//echo $templateService->render('pages/rentalPage',[]);


//$SQLService = new SQLService();
//$data = new ContainerService($SQLService->getPdo());
//dump($data->getOneContainerByClient(3));