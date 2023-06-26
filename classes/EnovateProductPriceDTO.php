<?php

class EnovateProductPriceDTO {

    private $id;
    private $code;
    private $price;

    public function getId(): int
    {
        return $this->id;
    }

    public function setID(int $id): void
    {
        $this->id = $id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }



}