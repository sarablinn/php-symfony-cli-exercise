<?php

namespace App\Cli\service;

use App\Cli\dao\DuplicateKeyException;
use App\Cli\dao\MessageManager;
use App\Cli\dao\MessageManagerJsonImpl;
use App\Cli\dao\TemplateManager;
use App\Cli\dao\TemplateManagerJsonImpl;
use App\Cli\dao\UserManager;
use App\Cli\dao\UserManagerJsonImpl;
use App\Cli\dto\MessageDto;
use App\Cli\dto\TemplateDto;
use App\Cli\dto\UserDto;
use DateTime;
use DateTimeInterface;
use ErrorException;
use Symfony\Component\Process\Process;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SlackerServiceJsonImpl implements SlackerService
{
    private MessageManager $messageManager;
    private TemplateManager $templateManager;
    private UserManager $userManager;

    public function __construct()
    {
        $this->messageManager = new MessageManagerJsonImpl();
        $this->templateManager = new TemplateManagerJsonImpl();
        $this->userManager = new UserManagerJsonImpl();
    }


    public function getAllUsers(): array
    {
        return $this->userManager->getAllUsers();
    }

    public function getAllUsersByIndex(): array
    {
        return $this->userManager->getAllUsersByIndex();
    }

    function getUser(string $userId): UserDto
    {
        return $this->userManager->getUser($userId);
    }

    /**
     * @throws DuplicateKeyException
     * @throws ExceptionInterface
     */
    function addUser(UserDto $user): void
    {
        $this->userManager->addUser($user);
    }

    function getTemplate(int $templateId): TemplateDto
    {
        return $this->templateManager->getTemplate($templateId);
    }

    function getAllTemplates(): array
    {
        return $this->templateManager->getAllTemplates();
    }

    function getAllTemplatesByIndex(): array
    {
        return $this->templateManager->getAllTemplatesByIndex();
    }

    /**
     * @throws ExceptionInterface
     * @throws DuplicateKeyException
     */
    function addTemplate(TemplateDto $template): void
    {
        $this->templateManager->addTemplate($template);
    }

    /**
     * @throws ExceptionInterface
     */
    function updateTemplate(TemplateDto $updatedTemplate): void
    {
        $this->templateManager->updateTemplate($updatedTemplate);
    }

    /**
     * @throws ExceptionInterface
     */
    function deleteTemplate(int $templateId): void
    {
        $this->templateManager->deleteTemplate($templateId);
    }

    function getMessage(int $messageId): MessageDto
    {
        return $this->messageManager->getMessage($messageId);
    }

    function getAllMessages(): array
    {
        return $this->messageManager->getAllMessages();
    }

    /**
     * @throws DuplicateKeyException
     */
    function addMessage(MessageDto $message): void
    {
        if ($message->getDate() == null) {
            $date = new DateTime();
            $date->format(DateTimeInterface::RFC2822);
            $message->setDate($date);
        }
        $this->messageManager->addMessage($message);
    }

    /**
     * @param TemplateDto $template
     * @param UserDto $user
     * @param MessageDto $message
     * @param string $channel
     * @param string $icon_emoji_name
     * @return void
     * @throws DuplicateKeyException
     * @throws ErrorException
     */
    public function sendMessage(TemplateDto $template, UserDto $user,
                                MessageDto $message, string $channel = 'testing_slackbot',
                                string $icon_emoji_name = ':ghost:'): void
    {
        $msg_date = new DateTime('now');
        $msg_date->format(DateTimeInterface::RFC2822);
        $message->setDate($msg_date);

        # set error handler to throw exceptions when there are warnings or notices
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new ErrorException($message, $severity, $severity, $file, $line);
        });

        $send_message_process
            = Process::fromShellCommandline(
                'curl -X POST -H \'Content-Type: application/json\' '
                . '-d \'{"channel": "#' . $channel
                . '", "username": "' . $user->getDisplayName() . '", '
                . '"text": "'. $message->getMessage() . '", '
                . '"icon_emoji": ":' . $icon_emoji_name . ':"}\' '
                #. 'https://hooks.slack.com/services/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');
                . getenv('WEBHOOK_BOT'));
        $send_message_process->run();

        restore_error_handler();

        $this->messageManager->addMessage($message);
    }

}
