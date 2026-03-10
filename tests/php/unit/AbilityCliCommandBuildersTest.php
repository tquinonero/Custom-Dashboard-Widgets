<?php

namespace CDW\Tests\Unit;

use Brain\Monkey\Functions;
use CDW\Tests\CDWTestCase;

require_once CDW_PLUGIN_DIR . 'includes/abilities/builders/class-cdw-ability-cli-command-builders.php';

class AbilityCliCommandBuildersTest extends CDWTestCase {

    protected function setUp(): void {
        parent::setUp();

        // Keep tests deterministic without depending on WP internals.
        Functions\when( 'sanitize_text_field' )->returnArg();
    }

    public function test_build_returns_empty_string_for_unknown_ability(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build( 'cdw/unknown-ability', array() );

        $this->assertSame( '', $command );
    }

    public function test_build_plugin_status_sanitizes_slug_whitespace(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/plugin-status',
            array( 'slug' => '  aki smet  ' )
        );

        $this->assertSame( 'plugin status akismet', $command );
    }

    public function test_build_search_replace_uses_dry_run_flag_when_enabled(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/search-replace',
            array(
                'search'  => ' old value ',
                'replace' => 'new value',
                'dry_run' => true,
            )
        );

        $this->assertSame( 'search-replace oldvalue newvalue --dry-run', $command );
    }

    public function test_build_task_create_prefers_assignee_login_over_assignee_id(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/task-create',
            array(
                'name'           => 'Publish post',
                'assignee_login' => ' editor user ',
                'assignee_id'    => 77,
            )
        );

        $this->assertSame( 'task create Publish post --assignee_login=editoruser', $command );
    }

    public function test_build_task_list_without_user_id_returns_plain_command(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/task-list',
            array()
        );

        $this->assertSame( 'task list', $command );
    }

    public function test_build_post_create_keeps_title_as_text_field_output(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/post-create',
            array( 'title' => 'Hello World' )
        );

        $this->assertSame( 'post create Hello World', $command );
    }

    public function test_build_block_patterns_list_handles_optional_category(): void {
        $with_category = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/block-patterns-list',
            array( 'category' => 'text and media' )
        );
        $without_category = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/block-patterns-list',
            array()
        );

        $this->assertSame( 'block-patterns list textandmedia', $with_category );
        $this->assertSame( 'block-patterns list', $without_category );
    }

    public function test_build_skill_get_appends_optional_file_when_present(): void {
        $command = \CDW_Ability_CLI_Command_Builders::build(
            'cdw/skill-get',
            array(
                'plugin_slug' => 'green light',
                'skill_name'  => 'gutenberg design',
                'file'        => 'instructions/attributes.md',
            )
        );

        $this->assertSame( 'skill get greenlight gutenbergdesign instructions/attributes.md', $command );
    }

    /**
     * @dataProvider provide_full_builder_matrix_cases
     */
    public function test_full_builder_matrix_produces_expected_commands( string $ability, array $input, string $expected ): void {
        $actual = \CDW_Ability_CLI_Command_Builders::build( $ability, $input );

        $this->assertSame( $expected, $actual, "Unexpected command for ability {$ability}" );
    }

    public function test_full_builder_matrix_covers_all_registered_abilities(): void {
        $reflection = new \ReflectionClass( \CDW_Ability_CLI_Command_Builders::class );
        $method     = $reflection->getMethod( 'get_builders' );
        $method->setAccessible( true );

        $registered_builders = array_keys( $method->invoke( null ) );
        $covered_builders    = array_map(
            static function( array $case ): string {
                return $case[0];
            },
            $this->provide_full_builder_matrix_cases()
        );

        sort( $registered_builders );
        sort( $covered_builders );

        $this->assertSame( $registered_builders, $covered_builders );
    }

    public function provide_full_builder_matrix_cases(): array {
        return array(
            'plugin-status' => array(
                'cdw/plugin-status',
                array( 'slug' => 'akismet' ),
                'plugin status akismet',
            ),
            'plugin-activate' => array(
                'cdw/plugin-activate',
                array( 'slug' => 'akismet' ),
                'plugin activate akismet',
            ),
            'plugin-deactivate' => array(
                'cdw/plugin-deactivate',
                array( 'slug' => 'akismet' ),
                'plugin deactivate akismet',
            ),
            'plugin-install' => array(
                'cdw/plugin-install',
                array( 'slug' => 'akismet' ),
                'plugin install akismet --force',
            ),
            'plugin-update' => array(
                'cdw/plugin-update',
                array( 'slug' => 'akismet' ),
                'plugin update akismet --force',
            ),
            'plugin-delete' => array(
                'cdw/plugin-delete',
                array( 'slug' => 'akismet' ),
                'plugin delete akismet --force',
            ),
            'theme-activate' => array(
                'cdw/theme-activate',
                array( 'slug' => 'twentytwentyfour' ),
                'theme activate twentytwentyfour',
            ),
            'theme-install' => array(
                'cdw/theme-install',
                array( 'slug' => 'twentytwentyfour' ),
                'theme install twentytwentyfour --force',
            ),
            'theme-update' => array(
                'cdw/theme-update',
                array( 'slug' => 'twentytwentyfour' ),
                'theme update twentytwentyfour --force',
            ),
            'theme-status' => array(
                'cdw/theme-status',
                array( 'slug' => 'twentytwentyfour' ),
                'theme status twentytwentyfour',
            ),
            'user-create' => array(
                'cdw/user-create',
                array(
                    'username' => 'john doe',
                    'email'    => 'john@example.com',
                    'role'     => 'author',
                ),
                'user create johndoe john@example.com author',
            ),
            'user-delete' => array(
                'cdw/user-delete',
                array( 'user_id' => 15 ),
                'user delete 15 --force',
            ),
            'user-get' => array(
                'cdw/user-get',
                array( 'identifier' => 'john doe' ),
                'user get johndoe',
            ),
            'option-get' => array(
                'cdw/option-get',
                array( 'name' => 'blog name' ),
                'option get blogname',
            ),
            'option-set' => array(
                'cdw/option-set',
                array(
                    'name'  => 'blogname',
                    'value' => 'my value',
                ),
                'option set blogname myvalue',
            ),
            'search-replace' => array(
                'cdw/search-replace',
                array(
                    'search'  => 'http://old.site',
                    'replace' => 'https://new.site',
                    'dry_run' => false,
                ),
                'search-replace http://old.site https://new.site --force',
            ),
            'post-get' => array(
                'cdw/post-get',
                array( 'post_id' => 44 ),
                'post get 44',
            ),
            'post-create' => array(
                'cdw/post-create',
                array( 'title' => 'My New Post' ),
                'post create My New Post',
            ),
            'page-create' => array(
                'cdw/page-create',
                array( 'title' => 'Landing Page' ),
                'page create Landing Page',
            ),
            'task-list' => array(
                'cdw/task-list',
                array( 'user_id' => 9 ),
                'task list --user_id=9',
            ),
            'task-create' => array(
                'cdw/task-create',
                array(
                    'name'        => 'Publish Roadmap',
                    'assignee_id' => 7,
                ),
                'task create Publish Roadmap --assignee_id=7',
            ),
            'task-delete' => array(
                'cdw/task-delete',
                array( 'user_id' => 9 ),
                'task delete --user_id=9',
            ),
            'comment-list' => array(
                'cdw/comment-list',
                array( 'status' => 'approved' ),
                'comment list approved',
            ),
            'comment-approve' => array(
                'cdw/comment-approve',
                array( 'id' => 22 ),
                'comment approve 22',
            ),
            'comment-spam' => array(
                'cdw/comment-spam',
                array( 'id' => 22 ),
                'comment spam 22',
            ),
            'comment-delete' => array(
                'cdw/comment-delete',
                array( 'id' => 22 ),
                'comment delete 22 --force',
            ),
            'post-list' => array(
                'cdw/post-list',
                array( 'type' => 'page' ),
                'post list page',
            ),
            'post-count' => array(
                'cdw/post-count',
                array( 'type' => 'post' ),
                'post count post',
            ),
            'post-status' => array(
                'cdw/post-status',
                array(
                    'post_id' => 99,
                    'status'  => 'publish',
                ),
                'post status 99 publish',
            ),
            'post-delete' => array(
                'cdw/post-delete',
                array( 'post_id' => 99 ),
                'post delete 99 --force',
            ),
            'user-role' => array(
                'cdw/user-role',
                array(
                    'identifier' => 'john doe',
                    'role'       => 'editor',
                ),
                'user role johndoe editor',
            ),
            'option-delete' => array(
                'cdw/option-delete',
                array( 'name' => 'blogdescription' ),
                'option delete blogdescription',
            ),
            'theme-delete' => array(
                'cdw/theme-delete',
                array( 'slug' => 'twentytwentyfour' ),
                'theme delete twentytwentyfour --force',
            ),
            'transient-delete' => array(
                'cdw/transient-delete',
                array( 'name' => 'my transient key' ),
                'transient delete mytransientkey',
            ),
            'cron-run' => array(
                'cdw/cron-run',
                array( 'hook' => 'my_custom_hook' ),
                'cron run my_custom_hook',
            ),
            'media-list' => array(
                'cdw/media-list',
                array( 'count' => 5 ),
                'media list 5',
            ),
            'block-patterns-list' => array(
                'cdw/block-patterns-list',
                array( 'category' => 'text media' ),
                'block-patterns list textmedia',
            ),
            'skill-get' => array(
                'cdw/skill-get',
                array(
                    'plugin_slug' => 'my plugin',
                    'skill_name'  => 'my skill',
                    'file'        => 'SKILL.md',
                ),
                'skill get myplugin myskill SKILL.md',
            ),
        );
    }
}
