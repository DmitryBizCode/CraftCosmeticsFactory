<?php

namespace App\Services;
use App\Model\PeopleModel;
use Exception;
use PDO;

///Client,Owner
class PeopleService
{
    private PDO $pdo;
    private SqlSupportServiceTemplate $sqlSupportService;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->sqlSupportService = new SqlSupportServiceTemplate($pdo);
    }
    public function deleteClient(string $clientId): void
    {
        $this->sqlSupportService::delete('clients', 'client_id', $clientId);
    }
    public function insertClient(string $name, string $email,int $countryId,string $password, string $surname = null, string $address = null, string $phoneNumber = null, string $photo = null): void
    {
        $password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->pdo->prepare("INSERT INTO clients (name, email, country_id, password, surname, address, phone_number, photo) VALUES (:name, :email,:country_id, :password, :surname, :address, :phone_number, :photo)");
        $stmt->execute(['name' => $name, 'email' => $email, 'country_id' => $countryId, 'password' => $password, 'surname' => $surname, 'address' => $address, 'phone_number' => $phoneNumber, 'photo' => $photo]);
    }

    public function updateClient(int $id,string $name, string $email,int $countryId,string $password, string $surname = null, string $address = null, string $phoneNumber = null, string $photo = null): void
    {
        if($password != null){
            $password = password_hash($password, PASSWORD_DEFAULT);
        }
        else{
            $password = $this->getOneClient($id)[0]['password'];
        }
        $stmt = $this->pdo->prepare("UPDATE clients SET name = :name,surname = :surname,email = :email,password = :password,address = :address,phone_number = :phone_number,photo = :photo,country_id = :country_id WHERE client_id = :id");
        $stmt->execute(['name' => $name, 'email' => $email, 'country_id' => $countryId, 'password' => $password, 'surname' => $surname, 'address' => $address, 'phone_number' => $phoneNumber, 'photo' => $photo, 'id' => $id]);
    }
    public function getOneClient(int $clientId): array
    {
        return $this->sqlSupportService::getById('clients', 'client_id', $clientId);
    }
    public function getOneClientByEmail(string $mail): array
    {
        return $this->sqlSupportService::getById('clients', 'email', $mail);
    }
    public function getInformationForClient(int $clientId): array{
        ////join
        return[];
    }
    public function getAllClients(): array
    {
        return $this->sqlSupportService::getAll('clients');
    }
    public function getAllClientByCountryId($countryId): array
    {
        return $this->sqlSupportService::getById('clients', 'country_id', $countryId);
    }

    ///owners
    public function insertOwner(string $name, string $email, string $phoneNumber ): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO owners (name, email,phone_number) VALUES (:name, :email,:phone_number)");
        $stmt->execute(['name' => $name, 'email' => $email, 'phone_number' => $phoneNumber]);
    }
    public function getOneOwner($ownerId): array
    {
        return $this->sqlSupportService::getById('owners', 'owner_id', $ownerId);
    }
    public function getAllOwners(): array
    {
        return $this->sqlSupportService::getAll('owners');
    }
    public function deleteOwner(string $ownerId): void
    {
        $this->sqlSupportService::delete('owners', 'owner_id', $ownerId);
    }
}