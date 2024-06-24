<?php

namespace App\Cli\dao;

use App\Cli\dto\TemplateDto;

interface TemplateManager
{

    public function getTemplate(int $templateId): TemplateDto;

    public function getAllTemplates(): array;

    public function getAllTemplatesByIndex(): array;

    public function addTemplate(TemplateDto $template);

    public function updateTemplate(TemplateDto $updatedTemplate);

    public function deleteTemplate(int $templateId);

}
