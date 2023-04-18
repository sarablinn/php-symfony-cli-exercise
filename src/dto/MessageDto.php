<?php

namespace App\Cli\dto;

use DateTime;
use DateTimeInterface;

class MessageDto
{
    private int $id = 0;
    private string $message;
    private DateTime $date;


    public function __construct()
    {
        $this->date = new DateTime('now');
        $this->date->format(DateTimeInterface::RFC2822);
        $this->message = "";
    }


    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId(int $id): void
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    /**
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        return $this->date;
    }

    /**
     * @param DateTime $date
     */
    public function setDate(DateTime $date): void
    {
        $this->date = $date;
    }

}
