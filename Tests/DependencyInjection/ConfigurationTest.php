<?php

namespace BiteCodes\RestApiGeneratorBundle\Tests\DependencyInjection;

use BiteCodes\RestApiGeneratorBundle\Api\Resource\ApiManager;
use BiteCodes\RestApiGeneratorBundle\Api\Resource\ApiResource;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Index;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Show;
use BiteCodes\RestApiGeneratorBundle\DependencyInjection\Configuration;
use BiteCodes\RestApiGeneratorBundle\Form\DynamicFormType;
use BiteCodes\RestApiGeneratorBundle\Tests\Dummy\app\AppKernel;
use BiteCodes\RestApiGeneratorBundle\Tests\Dummy\TestEntity\Post;
use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use Symfony\Component\HttpKernel\Kernel;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration()
    {
        return new Configuration();
    }

    /** @test */
    public function assert_default_config()
    {
        $this->assertProcessedConfigurationEquals(
            [
                [
                    'resources' => [
                        Post::class => []
                    ]
                ]
            ],
            [
                'resources' => [
                    Post::class => [
                        'only' => ['index', 'show', 'create', 'update', 'batch_update', 'delete', 'batch_delete'],
                        'except' => [],
                        'identifier' => 'id',
                        'filter' => null,
                        'form_type' => DynamicFormType::class,
                        'paginate' => false
                    ]
                ]
            ], 'resources');
    }

    /** @test */
    public function node_only_accepts_7_actions()
    {
        $this->assertConfigurationIsValid([
            [
                'resources' => [
                    Post::class => [
                        'only' => ['index', 'show', 'create', 'update', 'batch_update', 'delete', 'batch_delete']
                    ]
                ]
            ]
        ], 'resources');
    }

    /** @test */
    public function node_only_raises_exception_for_other_values()
    {
        $this->assertConfigurationIsInvalid([
            [
                'resources' => [
                    Post::class => [
                        'only' => ['list']
                    ]
                ]
            ]
        ], 'resources');
    }

    /** @test */
    public function node_except_accepts_7_actions()
    {
        $this->assertConfigurationIsValid([
            [
                'resources' => [
                    Post::class => [
                        'except' => ['index', 'show', 'create', 'update', 'batch_update', 'delete', 'batch_delete']
                    ]
                ]
            ]
        ], 'resources');
    }

    /** @test */
    public function node_except_raises_exception_for_other_values()
    {
        $this->assertConfigurationIsInvalid([
            [
                'resources' => [
                    Post::class => [
                        'except' => ['list']
                    ]
                ]
            ]
        ], 'resources');
    }

    /** @test */
    public function node_resource_name_can_not_be_empty()
    {
        $this->assertConfigurationIsInvalid([
            [
                'resources' => [
                    Post::class => [
                        'resource_name' => ''
                    ]
                ]
            ]
        ], 'resources');
    }

    /** @test */
    public function node_filter_can_not_be_empty()
    {
        $this->assertConfigurationIsInvalid([
            [
                'resources' => [
                    Post::class => [
                        'filter' => ''
                    ]
                ]
            ]
        ], 'resources');
    }

    /** @test */
    public function node_paginate_has_to_be_a_boolean_value()
    {
        $this->assertConfigurationIsInvalid([
            [
                'resources' => [
                    Post::class => [
                        'paginate' => 'yes'
                    ]
                ]
            ]
        ], 'resources');
    }
}
