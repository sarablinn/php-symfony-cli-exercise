<?php

namespace App\Cli\dao;

use App\Cli\dto\MessageDto;
use DateTimeInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorFromClassMetadata;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AnnotationLoader;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class MessageManagerJsonImpl implements MessageManager
{
    private string $messages_filename = 'messages.json';
    private array $messages = [];

    public function getMessage(int $messageId): MessageDto
    {
        $this->loadMessagesFromFile();
        return $this->messages[$messageId];
    }

    public function getAllMessages(): array
    {
        $this->loadMessagesFromFile();
        return $this->messages;
    }

    /**
     * @throws DuplicateKeyException
     */
    public function addMessage(MessageDto $message)
    {
        $this->loadMessagesFromFile();

        #if no id provided, set id to the current highest id +1
        if ($message->getId() == 0) {
            #find the current highest id value
            $maxId = 0;
            foreach ($this->messages as $currentMessage) {
                if ($currentMessage->getId() > $maxId) {
                    $maxId = $currentMessage->getId();
                }
            }

            $message->setId($maxId +1);
        }

        if (!array_key_exists($message->getId(), $this->messages)) {
            $this->messages[$message->getId()] = $message;
        } else {
            throw new DuplicateKeyException("ERROR: Duplicate message ID. "
                . "Unable to add message.");
        }

        $this->saveMessagesToFile();
    }



    #######################
    # MEMOIZATION METHODS #
    #######################

    #TODO set serializer/normalizers to class variables

    /**
     * Loads message objects from the messages json file into the messages array.
     * @return void
     */
    private function loadMessagesFromFile(): void
    {
        $finder = new Finder();
        $finder->files()->in('src/data')->name($this->messages_filename);

        $classMetadataFactory
            = new ClassMetadataFactory(new AnnotationLoader());
        $classDiscriminator
            = new ClassDiscriminatorFromClassMetadata($classMetadataFactory);
        $objectNormalizer
            = new ObjectNormalizer(
                $classMetadataFactory,
                null,
                null,
                new PhpDocExtractor(),
                $classDiscriminator
        );

        $encoders = [new JsonEncoder()];
        $normalizers = [$objectNormalizer, new DateTimeNormalizer(
                ['datetime_formatter' => DateTimeInterface::RFC2822])];
        $serializer = new Serializer($normalizers, $encoders);

        # if file not found, throws FileNotFoundException
        if ($finder->hasResults()) {
            // iterate through Finder object to retrieve the file
            foreach ($finder as $file) {
                if ($file->getFilename() == $this->messages_filename) {
                    $fileContents = $file->getContents();
                    $messages_json_array = json_decode($fileContents, true);

                    #reset $this->messages array--so it is in a known state everytime it gets loaded
                    $this->messages = [];
                    foreach ($messages_json_array as $json_obj) {
                        $json_obj = json_encode($json_obj, true);
                        $message_obj = $serializer->deserialize(
                            $json_obj, MessageDto::class,
                            JsonEncoder::FORMAT, []);
                        $this->messages[$message_obj->getId()] = $message_obj;
                    }
                }
            }
        } else {
            throw new FileNotFoundException("ERROR: " . $this->messages_filename
                . " not found.");
        }
    }

    /**
     * Writes messages to the messages json file.
     * @return void
     * @throws ExceptionInterface
     */
    private function saveMessagesToFile(): void
    {
        $finder = new Finder();
        $finder->files()->in('src/data')->name($this->messages_filename);

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer(),
            new DateTimeNormalizer(['datetime_formatter' => DateTimeInterface::RFC2822])];
        $serializer = new Serializer($normalizers, $encoders);

        # if file not found, throws FileNotFoundException
        if ($finder->hasResults()) {
            // iterate through Finder object to retrieve the file
            foreach ($finder as $file) {
                if ($file->getFilename() == $this->messages_filename) {

                    # convert the user objects to json objects
                    $messages_normalized_array = [];
                    foreach ($this->messages as $message_obj) {
                        $messages_normalized_array[] = ($serializer->normalize($message_obj));
                    }

                    file_put_contents('src/data/' . $file->getFilename(),
                        $serializer->serialize($messages_normalized_array,
                            JsonEncoder::FORMAT,
                            [JsonEncode::OPTIONS => JSON_PRETTY_PRINT]));
                }
            }
        } else {
            throw new FileNotFoundException("ERROR: " . $this->messages_filename
                . " not found.");
        }
    }
}
