<?php

namespace App\Cli\dao;

use App\Cli\dto\UserDto;

interface UserManager
{
    public function getUser(String $userId): UserDto;

    public function getAllUsers(): array;

    public function getAllUsersByIndex(): array;

    public function addUser(UserDto $user);

}