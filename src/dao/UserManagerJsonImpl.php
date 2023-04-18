<?php

namespace App\Cli\dao;

use App\Cli\dto\UserDto;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;





class UserManagerJsonImpl implements UserManager
{
    private string $users_filename = 'users.json';
    private array $users = array();


    public function __construct()
    {
        $this->loadUsersFromFile();
    }


    /**
     * Returns the user if found by user ID or null if not found.
     * @param string $userId
     * @return UserDto
     */
    public function getUser(string $userId): UserDto
    {
        $this->loadUsersFromFile();
        return $this->users[$userId];
    }

    /**
     * Returns array of users indexed by userId.
     * @return array
     */
    public function getAllUsers(): array
    {
        $this->loadUsersFromFile();
        return $this->users;
    }

    /**
     * Returns array of users indexed by the user's index in the array.
     * @return array
     */
    public function getAllUsersByIndex(): array
    {
        $this->loadUsersFromFile();
        return array_values($this->users);
    }

    /**
     * @throws DuplicateKeyException
     * @throws ExceptionInterface
     */
    public function addUser(UserDto $user)
    {
        $this->loadUsersFromFile();
        if (!array_key_exists($user->getUserId(), $this->users)) {
            $this->users[$user->getUserId()] = $user;
        } else {
            throw new DuplicateKeyException("ERROR: Duplicate user ID. "
                . "Unable to add user.");
        }

        $this->saveUsersToFile();
    }


    #######################
    # MEMOIZATION METHODS #
    #######################

    /**
     * Loads user objects from the user json file into the users array.
     * @return void
     */
    private function loadUsersFromFile(): void
    {
        $finder = new Finder();
        $finder->files()->in('src/data')->name($this->users_filename);

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        # if file not found, throws FileNotFoundException
        if ($finder->hasResults()) {
            // iterate through Finder object to retrieve the file
            foreach ($finder as $file) {
                if ($file->getFilename() == $this->users_filename) {
                    $fileContents = $file->getContents();
                    $users_json_array = json_decode($fileContents, true);

                    #reset $this->users array--so it is in a known state each time it gets loaded
                    $this->users = [];
                    foreach ($users_json_array as $json_obj) {
                        $json_obj = json_encode($json_obj, true);
                        $user_obj = $serializer->deserialize(
                            $json_obj, UserDto::class,
                            JsonEncoder::FORMAT, []);
                        $this->users[$user_obj->getUserId()] = $user_obj;
                    }
                }
            }
        } else {
            throw new FileNotFoundException("ERROR: " . $this->users_filename
                . " not found.");
        }
    }

    /**
     * Writes users to the users json file.
     * @return void
     * @throws ExceptionInterface
     */
    private function saveUsersToFile(): void
    {
        $finder = new Finder();
        $finder->files()->in('src/data')->name($this->users_filename);

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        # if file not found, throws FileNotFoundException
        if ($finder->hasResults()) {
            // iterate through Finder object to retrieve the file
            foreach ($finder as $file) {
                if ($file->getFilename() == $this->users_filename) {

                    # normalize user objects (convert obj to array)
                    $users_normalized_array = [];
                    foreach ($this->users as $user_obj) {
                        $users_normalized_array[] = $serializer->normalize($user_obj);
                    }

                    file_put_contents('src/data/' . $file->getFilename(),
                        $serializer->serialize($users_normalized_array,
                            JsonEncoder::FORMAT,
                            [JsonEncode::OPTIONS => JSON_PRETTY_PRINT]));
                }
            }
        } else {
            throw new FileNotFoundException("ERROR: "
                    . $this->users_filename . " not found.");
        }
    }

}
