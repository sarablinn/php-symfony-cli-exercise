<?php
namespace App\Cli;

use App\Cli\dao\DuplicateKeyException;
use App\Cli\dto\MessageDto;
use App\Cli\dto\TemplateDto;
use App\Cli\dto\UserDto;
use App\Cli\service\SlackerService;
use App\Cli\service\SlackerServiceJsonImpl;
use DateTimeInterface;
use ErrorException;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use TypeError;

class Slacker extends Command
{
    protected static $defaultName = 'slack';


    private SlackerService $slackerService;

    public function __construct()
    {
        $this->slackerService = new SlackerServiceJsonImpl();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription("Sends Slack messages through the terminal.");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $keepGoing = true;
        while ($keepGoing == true)
        {
            try {
                $selection = $this->displayMainMenuAndGetSelection($input, $output);
                switch ($selection) {
                    case 1:
                        $this->sendMessage($input, $output);
                        break;
                    case 2:
                        $this->listTemplates($output);
                        break;
                    case 3:
                        $this->addTemplate($input, $output);
                        break;
                    case 4:
                        $this->updateTemplate($input, $output);
                        break;
                    case 5:
                        $this->deleteTemplate($input, $output);
                        break;
                    case 6:
                        $this->listUsers($output);
                        break;
                    case 7:
                        $this->addUser($input, $output);
                        break;
                    case 8:
                        $this->listSentMessages($output);
                        break;
                    case 9:
                        $keepGoing = false;
                        break;
                    default:
                        $output->writeln('<error>Error: Please input a valid selection.</error>');
                }
            } catch (DuplicateKeyException $e) {
                $output->writeln("<error>" . $e->getMessage() . "</error>");
            } catch (ExceptionInterface $e) {
                #ignore
            }

        }

        return Command::SUCCESS;
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    public function displayMainMenuAndGetSelection(InputInterface $input,
                                                   OutputInterface $output): int
    {
        $output->writeln("\nWhat would you like to do?"
            . "\n[<info>1</info>] Send a message"
            . "\n[<info>2</info>] List templates"
            . "\n[<info>3</info>] Add a template"
            . "\n[<info>4</info>] Update a template"
            . "\n[<info>5</info>] Delete a template"
            . "\n[<info>6</info>] List users"
            . "\n[<info>7</info>] Add a user"
            . "\n[<info>8</info>] Show sent messages"
            . "\n[<info>9</info>] Exit");

        $helper = $this->getHelper('question');
        $question = new Question('<question>Selection: </question>', '9');

        return intval($helper->ask($input, $output, $question));
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param int $minOption
     * @param int $maxOption
     * @param $defaultOption
     * @param string $prompt
     * @return int
     */
    private function getSelection(InputInterface $input,
                                  OutputInterface $output,
                                  int $minOption, int $maxOption, $defaultOption,
                                  string $prompt): int
    {
        $helper = $this->getHelper('question');
        $question = new Question("\n<question>" . $prompt . "</question>", $defaultOption);

        $response = $minOption-1;
        while ($response < $minOption || $response > $maxOption) {
            try {
                $response = $helper->ask($input, $output, $question);
                if ($response < $minOption || $response > $maxOption) {
                    throw new Exception();
                }
            } catch (TypeError | Exception $e) {
                $output->writeln("<error>ERROR: please input a number between "
                    . $minOption . " and " . $maxOption . ".</error>");
            }
        }

        return intval($response);
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws DuplicateKeyException
     */
    private function sendMessage(InputInterface  $input,
                                 OutputInterface $output): void
    {
        #TODO change this to a while loop that lists only 5 options at a time and prompts user to enter 'm' for more.
        $templates = $this->slackerService->getAllTemplatesByIndex();
        $this->listTemplates($output);
        $min_template_id = $templates[0]->getId();
        $max_template_id = end($templates)->getId();
        $template_selection = $this->getSelection($input, $output,
            $min_template_id, $max_template_id, 1,
            "<question>Choose a template: </question>");

        $users = $this->slackerService->getAllUsersByIndex();
        $this->listUsers($output);
        $user_selection = $this->getSelection($input, $output,
            1, count($users), 1,
            "<question>Choose a user to send message from: </question>");

        $template_selection = $this->slackerService->getTemplate($template_selection);
        $user_selection = $this->slackerService->getUser($users[$user_selection-1]->getUserId());

        # loop to prompt user to fill in any variables the template has
        $template_split = explode(" ", $template_selection->getMessage());

        $output->writeln("\n<question>Fill in the blanks for the template: </question>");
        $completed_template_msg = [];
        foreach ($template_split as $word) {
            $word_replace = $word;
            if (preg_match('/^{.*[},.?!`*\"\':;]$/', $word) == 1) {
                $helper = $this->getHelper('question');
                $question = new Question("<question>" . $word
                    . ": </question>", "--");
                $response = $helper->ask($input, $output, $question);
                $word_replace = substr_replace($word, $response, 0);

                if (preg_match('/[,.?!`*\"\':;]$/', $word) == 1) {
                    $word_replace .= substr($word, -1);
                }
            }
            $completed_template_msg[] = $word_replace;
        }

        # convert the completed template array to a string
        $completed_msg_str = implode(" ", $completed_template_msg);

        $completed_message = new MessageDto();
        $completed_message->setMessage($completed_msg_str);

        # prompt user to input the desired slack channel to send the message to
        $helper = $this->getHelper('question');
        $question = new Question(
            "\n<question>Enter the slack channel name to send message to </question>"
            . "\n<comment>(Exclude '#'. Leave empty and hit ENTER to use default): </comment>",
            "testing_slackbot");
        $channel_response = $helper->ask($input, $output, $question);

        # prompt user to input the desired emoji to use as the icon
        $helper = $this->getHelper('question');
        $question = new Question("\n<question>Enter an emoji name </question>"
            . "\n<comment>(Exclude ': :'. Leave empty and hit ENTER to use default): </comment>",
            "ghost");
        $emoji_response = $helper->ask($input, $output, $question);

        # prompt user to confirm message send
        $output->writeln("\n" . $completed_msg_str);
        $helper = $this->getHelper('question');
        $question = new Question("<question>Are you sure you want to send the message above from "
                . $user_selection->getDisplayName() . "? (yes/no): </question>", "no");
        $confirmation_response = $helper->ask($input, $output, $question);

        try {
            if (strcasecmp($confirmation_response, 'yes') === 0) {
                $this->slackerService->sendMessage($template_selection,
                    $user_selection,
                    $completed_message,
                    $channel_response,
                    $emoji_response);
                $output->writeln("\n<info>MESSAGE SENT.</info>");
            } else {
                $output->writeln("\n<error>MESSAGE SEND CANCELLED.</error>");
            }
        } catch (ErrorException | DuplicateKeyException $e) {
            $output->writeln("\n<error>" . "UNABLE TO SEND MESSAGE: "
                . $e->getMessage() . "</error>");
        }
    }


    /**
     * @param OutputInterface $output
     * @return void
     */
    private function listSentMessages(OutputInterface $output): void
    {
        $messages = $this->slackerService->getAllMessages();
        $table = new Table($output);

        $table
            ->setHeaders(['Date', 'Message']);
        foreach ($messages as $message) {
            $table->addRow([$message->getDate()->format(DateTimeInterface::RFC2822),
                    $message->getMessage()]);
        }

        $table->render();
    }


    /**
     * @param OutputInterface $output
     * @return void
     */
    private function listUsers(OutputInterface $output): void
    {
        $output->writeln("\n<info>------- USERS -------</info>");

        $users = $this->slackerService->getAllUsersByIndex();
        $index = 1;
        foreach ($users as $user) {
            $output->writeln("[<info>" . $index . "</info>]"
                . " " . $user->getDisplayName());
            $index++;
        }
        $output->writeln(" ");
    }


    /**
     * @param OutputInterface $output
     * @return void
     */
    private function listTemplates(OutputInterface $output): void
    {
        $output->writeln("\n<info>----- TEMPLATES -----</info>");

        $templates = $this->slackerService->getAllTemplates();
        $output->writeln(" ");

        foreach ($templates as $template) {
            $output->writeln("[<info>" . $template->getId() . "</info>]"
                        . " " . $template->getMessage());
        }
        $output->writeln(" ");
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws DuplicateKeyException
     * @throws ExceptionInterface
     */
    private function addTemplate(InputInterface  $input,
                                 OutputInterface $output): void
    {
        $output->writeln("<info>\nAdd A Template: </info>");

        $helper = $this->getHelper('question');
        $template_response
            = new Question("\n<question>Enter a new template and press enter to submit: </question>"
            . "\n<comment>(surround placeholder variables with brackets { })</comment>\n", null);
        $template_response = $helper->ask($input, $output, $template_response);

        #TODO List all variables

        if ($template_response != null) {
            $template = new TemplateDto();
            $template->setMessage($template_response);
            $this->slackerService->addTemplate($template);

            $output->writeln("<info>\nNEW TEMPLATE ADDED.</info>");
        }
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ExceptionInterface
     */
    private function updateTemplate(InputInterface  $input,
                                    OutputInterface $output): void
    {
        $output->writeln("\n<info>Update A Template: </info>");
        $templates = $this->slackerService->getAllTemplates();

        # print templates to console
        $output->writeln(" ");
        foreach ($templates as $template) {
            $output->writeln("[<info>" . $template->getId() . "</info>]"
                . " " . $template->getMessage());
        }
        $output->writeln(" ");

        $selection = $this->getSelection($input, $output,
            1, count($templates),0,
            "<question>Which template would you like to update?: </question>");

        $helper = $this->getHelper('question');
        $update_prompt
                = new Question("<question>Enter the updated template and "
                        . "press ENTER to save: </question>", null);
        $update_response = $helper->ask($input, $output, $update_prompt);

        if (str_replace(' ', '', $update_response) != '') {
            $updated_template = new TemplateDto();
            $updated_template->setId($selection);
            $updated_template->setMessage($update_response);
            $this->slackerService->updateTemplate($updated_template);

            $output->writeln("\n<info>TEMPLATE UPDATED.</info>");
        }
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws ExceptionInterface
     */
    private function deleteTemplate(InputInterface  $input,
                                    OutputInterface $output): void
    {
        $output->writeln("\n<info>Delete A Template: </info>");
        $templates = $this->slackerService->getAllTemplates();

        # print templates to console
        $output->writeln(" ");
        foreach ($templates as $template) {
            $output->writeln("[<info>" . $template->getId() . "</info>]"
                . " " . $template->getMessage());
        }
        $output->writeln(" ");

        $selection = $this->getSelection($input, $output,
            1, count($templates),0,
            "<question>Which template would you like to delete?: </question>");

        $helper = $this->getHelper('question');
        $confirmation_question = new Question("<question>Are you sure? (yes/no) </question>", "no");
        $confirmation_response = $helper->ask($input, $output, $confirmation_question);

        if (strcasecmp($confirmation_response, "yes") === 0) {
            $this->slackerService->deleteTemplate($selection);
            $output->writeln("\n<info>TEMPLATE DELETED.</info>");
        }
    }


    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    private function addUser(InputInterface $input,
                             OutputInterface $output): void
    {
        $output->writeln("\n<info>Add A User: </info>");

        $helper = $this->getHelper('question');

        # USER NAME
        $question = new Question("<question>Enter the user's name: </question>");
        $name_response = $helper->ask($input, $output, $question);
        while ($name_response == '') {
            $name_response = $helper->ask($input, $output, $question);
        }

        # USER ID
        $question = new Question("<question>Enter the user's ID: </question>");
        $id_response = $helper->ask($input, $output, $question);
        while ($id_response == '') {
            $id_response = $helper->ask($input, $output, $question);
        }

        # USER USERNAME
        $question = new Question("<question>Enter the user's username: </question>");
        $username_response = $helper->ask($input, $output, $question);
        while ($username_response == '') {
            $username_response = $helper->ask($input, $output, $question);
        }

        # USER DISPLAY NAME
        $question = new Question("<question>Enter the user's display name: </question>");
        $display_name_response = $helper->ask($input, $output, $question);
        while ($display_name_response == '') {
            $display_name_response = $helper->ask($input, $output, $question);
        }

        $user = new UserDto();
        $user->setName($name_response);
        $user->setUserId($id_response);
        $user->setUsername($username_response);
        $user->setDisplayName($display_name_response);

        try {
            $this->slackerService->addUser($user);
        } catch (DuplicateKeyException $e) {
            $output->writeln("<error>" . $e->getMessage() . "</error>");

            $question = new Question("<question>Enter the user's ID: </question>");
            $id_response = $helper->ask($input, $output, $question);
            while ($id_response == '') {
                $id_response = $helper->ask($input, $output, $question);
            }
        } catch (ExceptionInterface $e) {
            // ignore
        }

        $output->writeln("\n<info>USER ADDED.</info>");
    }

}
