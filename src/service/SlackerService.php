<?php

namespace App\Cli\service;

use App\Cli\dto\MessageDto;
use App\Cli\dto\TemplateDto;
use App\Cli\dto\UserDto;

interface SlackerService
{
    function getUser(string $userId): UserDto;

    function getAllUsers(): array;

    function getAllUsersByIndex(): array;

    function addUser(UserDto $user): void;

    function getTemplate(int $templateId): TemplateDto;

    function getAllTemplates(): array;

    function getAllTemplatesByIndex(): array;

    function addTemplate(TemplateDto $template): void;

    function updateTemplate(TemplateDto $updatedTemplate): void;

    function deleteTemplate(int $templateId): void;

    function getMessage(int $messageId): MessageDto;

    function getAllMessages(): array;

    function addMessage(MessageDto $message): void;

    function sendMessage(TemplateDto $template, UserDto $user,
                        MessageDto $message): void;

}
