<?php

namespace CDW\Tests\Unit;

use CDW\Tests\CDWTestCase;

require_once CDW_PLUGIN_DIR . 'includes/cli/class-cdw-cli-command-catalog.php';

class CliCommandCatalogTest extends CDWTestCase {

    public function test_catalog_returns_well_formed_categories_and_commands(): void {
        $categories = \CDW_CLI_Command_Catalog::get_modular_categories();

        $this->assertIsArray( $categories );
        $this->assertNotEmpty( $categories );

        foreach ( $categories as $index => $category ) {
            $this->assertIsArray( $category, "Category at index {$index} must be an array" );
            $this->assertArrayHasKey( 'category', $category, "Category at index {$index} is missing 'category'" );
            $this->assertArrayHasKey( 'commands', $category, "Category at index {$index} is missing 'commands'" );
            $this->assertIsString( $category['category'], "Category name at index {$index} must be a string" );
            $this->assertNotSame( '', trim( $category['category'] ), "Category name at index {$index} must not be empty" );
            $this->assertIsArray( $category['commands'], "Commands for category '{$category['category']}' must be an array" );
            $this->assertNotEmpty( $category['commands'], "Commands for category '{$category['category']}' must not be empty" );

            foreach ( $category['commands'] as $command_index => $command ) {
                $this->assertIsArray( $command, "Command {$command_index} in '{$category['category']}' must be an array" );
                $this->assertArrayHasKey( 'name', $command, "Command {$command_index} in '{$category['category']}' is missing 'name'" );
                $this->assertArrayHasKey( 'description', $command, "Command {$command_index} in '{$category['category']}' is missing 'description'" );
                $this->assertIsString( $command['name'], "Command name {$command_index} in '{$category['category']}' must be a string" );
                $this->assertNotSame( '', trim( $command['name'] ), "Command name {$command_index} in '{$category['category']}' must not be empty" );
                $this->assertIsString( $command['description'], "Command description {$command_index} in '{$category['category']}' must be a string" );
                $this->assertNotSame( '', trim( $command['description'] ), "Command description {$command_index} in '{$category['category']}' must not be empty" );
            }
        }
    }
}
