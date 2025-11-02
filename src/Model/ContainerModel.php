<?php

namespace App\Model;

class ContainerModel
{
    public string $name;
    public float $width;
    public float $height;
    public float $length;
    public int $container_id;
    public bool $iot;
    public int $old;
    public function __construct(int $container_id,string $name, float $width, float $height, float $length,bool $iot,int $old){
        $this->container_id = $container_id;
        $this->name = $name;
        $this->width = $width;
        $this->height = $height;
        $this->length = $length;
        $this->iot = $iot;
        $this->old = $old;
    }
    public function toArray(): array{
        return [
            'container_id' => $this->container_id,
            'name' => $this->name,
            'width' => $this->width,
            'height' => $this->height,
            'length' => $this->length,
            'iot' => $this->iot,
            'old' => $this->old
        ];
    }
    public static function fromArray($data): self {
        return new self(
            $data['container_id'] ?? '',
            $data['name'] ?? '',
            $data['width'] ?? '',
            $data['height'] ?? '',
            $data['length'] ?? '',
            $data['iot'] ?? false,
            $data['old'] ?? ''
        );
    }
    public static function emptyContainerModel(): ContainerModel {
        return self::fromArray([]);
    }

}