<?php

declare(strict_types=1);

namespace Micro\TraeffikGenericBundle\EventListener;

use Doctrine\DBAL\Schema\Column;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class EnumTypeListener
{
    public function postGenerateSchema(GenerateSchemaEventArgs $eventArgs): void
    {
        foreach ($eventArgs->getSchema()->getTables() as $table) {
            foreach ($table->getColumns() as $column) {
                if (array_key_exists('enumType', $column->getPlatformOptions())) {
                    $this->changeStringToEnum($column);
                }
            }
        }
    }

    private function changeStringToEnum(Column $column): void
    {
        /** @var class-string */
        $enum = $column->getPlatformOptions()['enumType'];

        $enumCases = array_map(fn ($case) => "'$case->value'", $enum::cases());
        $column->setColumnDefinition(sprintf('ENUM(%s)', implode(',', $enumCases)));
    }
}
