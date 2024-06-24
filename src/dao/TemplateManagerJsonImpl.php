<?php

namespace App\Cli\dao;

use App\Cli\dto\TemplateDto;
use Exception;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class TemplateManagerJsonImpl implements TemplateManager
{
    private string $templates_filename = 'templates.json';
    private array $templates = array();

    public function getTemplate(int $templateId): TemplateDto
    {
        $this->loadTemplatesFromFile();
        return $this->templates[$templateId];
    }

    public function getAllTemplates(): array
    {
        $this->loadTemplatesFromFile();
        return $this->templates;
    }

    public function getAllTemplatesByIndex(): array
    {
        $this->loadTemplatesFromFile();
        return array_values($this->templates);
    }

    /**
     * @throws DuplicateKeyException
     * @throws ExceptionInterface
     */
    public function addTemplate(TemplateDto $template)
    {
        $this->loadTemplatesFromFile();

        #if no id provided, set the id
        if ($template->getId() == 0) {

            #find the current highest id value
            $maxId = 0;
            foreach ($this->templates as $currentTemplate) {
                if ($currentTemplate->getId() > $maxId) {
                    $maxId = $currentTemplate->getId();
                }
            }

            $template->setId($maxId +1);
        }

        if (!array_key_exists($template->getId(), $this->templates)) {
            $template_pair = array($template->getId() => $template);
            $this->templates = array_merge($this->templates, $template_pair);
        } else {
            throw new DuplicateKeyException("ERROR: Duplicate template ID. "
                . "Unable to add template.");
        }

        $this->saveTemplatesToFile();
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function updateTemplate(TemplateDto $updatedTemplate)
    {
        $this->loadTemplatesFromFile();

        if (array_key_exists($updatedTemplate->getId(), $this->templates)) {
            $this->templates[$updatedTemplate->getId()] = $updatedTemplate;
        } else {
            throw new Exception("ERROR: no template to update.");
        }

        $this->saveTemplatesToFile();
    }

    /**
     * @throws Exception
     * @throws ExceptionInterface
     */
    public function deleteTemplate(int $templateId)
    {
        $this->loadTemplatesFromFile();

        if (array_key_exists($templateId, $this->templates)) {
            unset($this->templates[$templateId]);
        } else {
            throw new Exception("ERROR: no template to delete.");
        }

        $this->saveTemplatesToFile();
    }


    #######################
    # MEMOIZATION METHODS #
    #######################

    /**
     * Loads template objects from the template json file into the templates array.
     * @return void
     */
    private function loadTemplatesFromFile(): void
    {
        $finder = new Finder();
        $finder->files()->in('src/data')->name($this->templates_filename);

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        # if file not found, throws FileNotFoundException
        if ($finder->hasResults()) {
            # iterate through Finder object to retrieve the file
            foreach ($finder as $file) {

                #TODO clean this up. Switch to serializer, not json_decode.
                if ($file->getFilename() == $this->templates_filename) {
                    $fileContents = $file->getContents();
                    $templates_json_array = json_decode($fileContents, true);

                    #reset $this->templates array--so it is in a known state everytime it gets loaded
                    $this->templates = [];
                    foreach ($templates_json_array as $json_obj) {
                        $json_obj = json_encode($json_obj, true);
                        $template_obj = $serializer->deserialize(
                            $json_obj, TemplateDto::class,
                            JsonEncoder::FORMAT, []);

                        $this->templates[$template_obj->getId()] = $template_obj;
                    }
                }
            }
        } else {
            throw new FileNotFoundException("ERROR: " . $this->templates_filename
                . " not found.");
        }
    }

    /**
     * Writes templates to the templates json file.
     * @return void
     * @throws ExceptionInterface
     */
    private function saveTemplatesToFile(): void
    {
        $finder = new Finder();
        $finder->files()->in('src/data')->name($this->templates_filename);

        $encoders = [new JsonEncoder()];
        $normalizers = [new ObjectNormalizer()];
        $serializer = new Serializer($normalizers, $encoders);

        # if file not found, throws FileNotFoundException
        if ($finder->hasResults()) {

            # iterate through Finder object to retrieve the file
            foreach ($finder as $file) {
                if ($file->getFilename() == $this->templates_filename) {

                    # normalize templates (convert obj to array)
                    $templates_normalized_array = [];
                    foreach ($this->templates as $template_obj) {
                        $templates_normalized_array[] = $serializer->normalize($template_obj);
                    }

                    file_put_contents('src/data/' . $file->getFilename(),
                        $serializer->serialize($templates_normalized_array,
                            JsonEncoder::FORMAT,
                            [JsonEncode::OPTIONS => JSON_PRETTY_PRINT]));
                }
            }
        } else {
            throw new FileNotFoundException("ERROR: " . $this->templates_filename
                . " not found.");
        }
    }
}

