<?php

namespace App\Controllers;

use App\Services\CalculateShipments;
use App\Services\ContainerService;
use App\Services\IotService;
use App\Services\Mail;
use App\Services\PeopleService;
use App\Services\PlaceService;
use App\Services\RentService;
use App\Services\SQLService;
use App\Services\TemplateService;
use Exception;
use PDO;
use App\Model\PeopleModel;
use TypeError;

class ContainerController
{
    private PeopleService $peopleService;
    private PlaceService $placeService;
    private TemplateService $templateService;
    private containerService $containerService;
    private RentService  $rentService;
    private CalculateShipments $calculateShipments;
    private IotService $iotService;
    private array $typeMetrics = [
        'temperatures' => 2, // Датчики температури: похибка ±2°C
        'humidity' => 5, // Датчики вологості: похибка ±5%
        'vibrometers' => 0.1, // Датчики вібрації: похибка ±0.1 м/с²
        'inclines' => 1, // Датчики нахилу: похибка ±1° (кут нахилу)
        'openings' => 0, // Датчики відкриття: звичайно це "0" або "1", тому немає діапазону
        'gps' => 5, // GPS: похибка ±5 метрів
        'illuminated' => 10, // Освітленість: похибка ±10 люкс
        'gases' => 0.5 // Газові датчики: похибка ±0.5 ppm (parts per million)
    ];
    public function __construct()
    {

        $pdo = new SQLService();
        $this->iotService = new IotService($pdo->getPdo());
        $this->placeService = new PlaceService($pdo->getPdo());
        $this->peopleService = new PeopleService($pdo->getPdo());
        $this->containerService = new ContainerService($pdo->getPdo());
        $this->rentService = new RentService($pdo->getPdo());
        $this->calculateShipments = new CalculateShipments($pdo->getPdo());
        $this->templateService = new TemplateService();
    }
    private function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit();
    }
    public function getSign(): string
    {
        return $this->templateService->render('pages/signPage', ["signNavBar" => 1,]);
    }
    public function getHome(): string
    {
        return $this->templateService->render('pages/indexPage', ["homeNavBar" => 1,"footer" => 1]);
    }
    public function getServices(): string
    {
        return $this->templateService->render('pages/servicesPage', ["servicesNavBar" => 1,"footer" => 1]);
    }
    public function getContact(): string
    {
        return $this->templateService->render('pages/contactPage', ["contactNavBar" => 1,"footer" => 1]);
    }
    public function сontactMail(string $mail = null, string $name = null, string $message = null): void
    {
        $mailPush = new Mail();
        $mailPush->sendMail($mail,  $name, $message);
        $this->redirect("/index.php?action=contact");
    }
    private function supportProfile($personalData): string{
        if(!empty($personalData)){
            $personalData['country'] = $this->placeService->getOneCountryById($personalData['country_id'])[0]['name'];
            $personalData = PeopleModel::fromArray($personalData);

            $dataContainer = $this->containerService->getStatusAllByClient($personalData->id_people);
            //dump($dataContainer);
            usort($dataContainer, function ($a, $b) {
                return strtotime($a['status_update_time']) <=> strtotime($b['status_update_time']);
            });
            $dataContainerLast = end($dataContainer);
        }
        dump($dataContainer);

        if (!empty($personalData)){
//            dump($dataContainerLast);
            return $this->templateService->render('pages/profilePage',[
                'client' => $personalData,
                'container' => $dataContainer,
                'last' => $dataContainerLast
            ]);
        }
        else{
            return $this->templateService->render('pages/signPage', ["signNavBar" => 1]);
        }
    }
    public function getProfile(int $id_client)
    {
        $personalData = $this->peopleService->getOneClient($id_client)[0];
        return $this->supportProfile($personalData);
    }
    public function getProfileRequest(bool $sign,array $data): string
    {
        if ($sign){
            $this->signOut($data);
        }
        $personalData = $this->signIn($data['email'], $data['password']);
        return $this->supportProfile($personalData);
    }
    private function signIn(string $mail, string $password): array{
        $data = $this->peopleService->getOneClientByEmail($mail);
        return password_verify($password, $data[0]['password']) ? $data[0] : [];
    }
    private function signOut($data): void{
        $countryId = $this->placeService->getOneCountry($data['country']);
        $this->peopleService->insertClient($data['name'], $data['email'], $countryId[0]['country_id'], $data['password'], $data['surname'], $data['address'], $data['phone_number']);
    }
    public function getOrders($id,$data):string
    {
        $personalData = PeopleModel::fromArray($this->peopleService->getOneClient($id)[0]);
        return $this->templateService->render('pages/ordersPage',['client' =>$personalData]);
    }

    public function getSetting($id,$data):string
    {
        $personalData = $this->peopleService->getOneClient($id)[0];
        $personalData['country'] = $this->placeService->getOneCountryById($personalData['country_id'])[0]['name'];
        $personalData = PeopleModel::fromArray($personalData);
        return $this->templateService->render('pages/settingProfile',['client' =>$personalData]);
    }
    public function setting($id,array $data)
    {
        $countryId = $this->placeService->getOneCountry($data['country_id']);

        $this->peopleService->updateClient( $id,$data['name'], $data['email'], $countryId[0]['country_id'], $data['new_password'], $data['surname'], $data['address'], $data['phone_number'],null);
        echo $this->getProfile($id);
    }
    public function getRental($id,$data):string
    {
        $personalData = $this->peopleService->getOneClient($id)[0];
        $personalData['country'] = $this->placeService->getOneCountryById($personalData['country_id'])[0]['name'];
        $personalData = PeopleModel::fromArray($personalData);
        $allDataContainer = $this->containerService->getFreeStatusAllContainers();
        $routes = $this->placeService->getRoutesWithAllInformation();
        return $this->templateService->render('pages/rentalPage',[
            'client' =>$personalData,
            'containers' => $allDataContainer,
            'routes' => $routes
        ]);
    }
    public function getDetail($id,$data,$id_containers):string
    {
        $personalData = $this->peopleService->getOneClient($id)[0];
        $personalData['country'] = $this->placeService->getOneCountryById($personalData['country_id'])[0]['name'];
        $personalData = PeopleModel::fromArray($personalData);
        $dataContainer = $this->containerService->getStatusAllByClient($personalData->id_people);

        foreach ($dataContainer as $item) {
            if ($item['container_id'] === $id_containers){
                $dataContainer = $item;
                break;
            }
        }
        $this->insertThreeElement($id_containers, $dataContainer['rental_id'], $data["type"], $data["value"]);
        $typeSensor = $this->getSensors($id_containers,$dataContainer['rental_id']);
        //dump($typeSensor);
        return $this->templateService->render('pages/containerDetailPage',[
            'client' =>$personalData,
            'container' => $dataContainer,
            'sensor' => $typeSensor
        ]);
    }
    function randomFloat(float $min, float $max): float {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
    private function putMetrics(string $type, float $range, int $containerId, int $rentalId,array $value = null): void {
        if (!isset($this->typeMetrics[$type])) {
            throw new Exception("Ключ '$type' не знайдено в масиві.");
        }
        if (!isset($value)) {
            $value = $this->iotService->getLatestElementIoT($type, $containerId, $rentalId);
        }

        $randomValue = $this->randomFloat(($value['value'] - $range), ($value['value'] + $range));
        $this->iotService->insertTable($containerId, $rentalId, $type, $randomValue);
    }

    private function insertThreeElement(int $containerId, int $rentalId, string $type = null, float $value = null):void{
        if (is_array($value)) {
            throw new TypeError("Очікувалося значення типу float, отримано масив.");
        }

        for($i = 0; $i < 3; $i++){
            foreach ($this->typeMetrics as $key => $valueMetric){
                if (isset($value) && isset($type) && $key == $type){
                    $this->putMetrics($key, $valueMetric, $containerId, $rentalId,['value' => $value]);
                    $value = null;
                    $type = null;
                }
                else{
                    $this->putMetrics($key, $valueMetric, $containerId, $rentalId);
                }
            }
        }
    }
    private function getSensors(int $id_containers,int $rental_id): array
    {
        return [
            'temperatures' => $this->iotService->getElementIoTByRental('temperatures', $id_containers, $rental_id),
            'humidity' => $this->iotService->getElementIoTByRental('humidity', $id_containers, $rental_id),
            'vibrometers' => $this->iotService->getElementIoTByRental('vibrometers', $id_containers, $rental_id),
            'inclines' => $this->iotService->getElementIoTByRental('inclines', $id_containers, $rental_id),
            'openings' => $this->iotService->getElementIoTByRental('openings', $id_containers, $rental_id),
            'gps' => $this->iotService->getElementIoTByRental('gps', $id_containers, $rental_id),
            'illuminated' => $this->iotService->getElementIoTByRental('illuminated', $id_containers, $rental_id),
            'gases' => $this->iotService->getElementIoTByRental('gases', $id_containers, $rental_id)
        ];
    }
    public function detail(int $id,array $data,int $id_containers):string
    {
        $personalData = $this->peopleService->getOneClient($id)[0];
        $personalData['country'] = $this->placeService->getOneCountryById($personalData['country_id'])[0]['name'];
        $personalData = PeopleModel::fromArray($personalData);
        $dataContainer = $this->containerService->getStatusAllByClient($personalData->id_people);

        foreach ($dataContainer as $item) {
            if ($item['container_id'] === $id_containers){
                $dataContainer = $item;
                break;
            }
        }
        $this->insertThreeElement($id_containers, $dataContainer['rental_id'], $data["type"], $data["value"]);
        $typeSensor = $this->getSensors($id_containers,$dataContainer['rental_id']);
//        dump($typeSensor);
        return $this->templateService->render('pages/containerDetailPage',[
            'client' =>$personalData,
            'container' => $dataContainer,
            'sensor' => $typeSensor
        ]);
    }
    public function rental($id,$data):string
    {
        $this->containerService->insertStatus('rented',$data['container_id']);
        $this->rentService->insertRental($data["start_date"],$data["end_date"],'rent',$data['price'],'paid',$data['container_id'],$id,$data['cargo_details']);
        $rent = $this->rentService->getOneRentalByClientAndContainer($data['container_id'],$id);
        $this->calculateShipments->insertShipment($data["start_date"],$data["end_date"],'Pending',$rent['rental_id'],$data['route_id']);
        return $this->getProfile($id);
    }




//dump($data[0]);
//dump($password);
//dump($data[0]['password']);
//dump(password_verify($password, $data[0]['password'])?1:0);
    //////////////////////*******************
    public function getList(): string
    {
        $posts = $this->postService->getAll();
        return $this->templateService->render('posts/list', [
            'title' => 'Basic Blog',
            'posts' => $posts
        ]);
    }
    private function uploadPhoto(array $image, array &$data): void
    {
        if ($image) {
            $data['image_name'] = $this->postService->generateImageHash($image['image_name']['name']);
            $targetPath = __DIR__ . '/../../images/PhotoPost/' . $data['image_name'];
            if (!move_uploaded_file($image['image_name']['tmp_name'], $targetPath)) {
                throw new \Exception("Failed to upload image");
            }
        }
    }
    private function deletePhoto(array $image, string $id, bool $skip = false): void
    {
        $posts = $this->postService->getAll();
        foreach ($posts as $post) {
            if ($post->id === $id) {
                if ((isset($image['image_name']) && $image['image_name']['error'] === UPLOAD_ERR_OK) || $skip) {
                    $imagePath = realpath(__DIR__ . '/../../images/PhotoPost/' . $post->image_name);
                    if ($imagePath && file_exists($imagePath)) {
                        if (!unlink($imagePath)) {
                            error_log("Error deleting file: " . $imagePath);
                        }
                    }
                }
            }
        }
    }

    public function create(array $data, array $image)
    {
        try {
            $this->uploadPhoto($image, $data);
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }

        $post = PostModel::fromArray($data);
        $this->postService->create($post);
        $this->redirect('/index.php');
    }

    public function getCreate(): string
    {
        return $this->templateService->render('posts/create', [
            'title' => 'New Post',
            'navBar' => "NewPost"
        ]);
    }

    public function edit(string $id, array $data, array $image): void
    {
        try {
            if (isset($image['image_name']) && $image['image_name']['error'] === UPLOAD_ERR_OK) {
                $this->deletePhoto($image, $id);
                $this->uploadPhoto($image, $data);
            }

            $this->postService->update($id, $data);
            $this->redirect('/index.php');

        } catch (\Exception $e) {
            error_log("Exception in edit: " . $e->getMessage());
        }
    }

    public function getEdit($id): string
    {
        $post = $this->postService->getOne($id);

        return $this->templateService->render('posts/edit', [
            'title' => 'Edit Post',
            'navBar' => "NewPost",
            'post' => $post
        ]);
    }

    public function delete(string $id): void
    {
        try {
            $this->deletePhoto([], $id, true);
            $this->postService->delete($id);
            $this->redirect('/index.php');
        } catch (\Exception $e) {
            error_log("Error exception when deleted a post: " . $e->getMessage());
        }
    }

    public function getDelete($id): string
    {
        $post = $this->postService->getOne($id);
        return $this->templateService->render('posts/delete', [
            'title' => 'Delete Post',
            'navBar' => "NewPost",
            'post' => $post
        ]);
    }


}