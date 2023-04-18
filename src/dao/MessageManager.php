<?php

namespace App\Cli\dao;

use App\Cli\dto\MessageDto;

interface MessageManager
{
    public function getMessage(int $messageId): MessageDto;

    public function getAllMessages(): array;

    public function addMessage(MessageDto $message);

}
