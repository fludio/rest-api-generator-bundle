<?php

namespace BiteCodes\RestApiGeneratorBundle\Annotation;

use BiteCodes\DoctrineFilter\FilterBuilder;
use Doctrine\Common\Inflector\Inflector;
use Doctrine\ORM\EntityManager;
use BiteCodes\DoctrineFilter\FilterInterface;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\BatchDelete;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\BatchUpdate;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Create;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Delete;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Index;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Show;
use BiteCodes\RestApiGeneratorBundle\Api\Actions\Update;
use BiteCodes\RestApiGeneratorBundle\Form\DynamicFormSubscriber;
use BiteCodes\RestApiGeneratorBundle\Form\DynamicFormType;
use BiteCodes\RestApiGeneratorBundle\Api\Resource\ApiManager;
use BiteCodes\RestApiGeneratorBundle\Api\Resource\ApiResource;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nelmio\ApiDocBundle\Extractor\HandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\Router;

class GenerateApiDocHandler implements HandlerInterface
{
    /**
     * @var \BiteCodes\RestApiGeneratorBundle\Api\Resource\ApiManager
     */
    private $manager;
    /**
     * @var EntityManager
     */
    private $em;
    /**
     * @var Router
     */
    private $router;
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ApiManager $manager, EntityManager $em, Router $router, ContainerInterface $container)
    {
        $this->manager = $manager;
        $this->em = $em;
        $this->router = $router;
        $this->container = $container;
    }

    /**
     * @param ApiDoc $annotation
     * @param array $annotations
     * @param Route $route
     * @param \ReflectionMethod $method
     */
    public function handle(ApiDoc $annotation, array $annotations, Route $route, \ReflectionMethod $method)
    {
        if (!$resource = $this->getResource($route)) {
            return;
        }

        $context = new RequestContext('', $resource->getActions()->getActionForRoute($route)->getMethods()[0]);
        $this->router->setContext($context);

        $routeName = $this->router->match($route->getPath())['_route'];
        $section = $this->getSection($routeName, $resource);

        foreach ($annotations as $annot) {
            if ($annot instanceof GenerateApiDoc) {
                $annotation->setSection($section);
                if ($this->returnsEntity($route)) {
                    $this->setOutput($annotation, $resource);
                }
                if ($this->expectsInput($route)) {
                    if ($resource->getFormTypeClass() == DynamicFormType::class) {
                        $entityClass = $resource->getEntityClass();
                        $handler = new DynamicFormSubscriber($this->em, new $entityClass);
                        foreach ($handler->getFields() as $field) {
                            $annotation->addParameter($field, ['dataType' => 'string', 'required' => false]);
                        }
                    } else {
                        $this->setInput($annotation, $resource);
                    }
                }
                if ($roles = $route->getDefault('_roles')) {
                    $annotation->setAuthentication(true);
                    $annotation->setAuthenticationRoles($roles);
                }
                $annotation->setDescription($this->getDescription($resource, $route));
                $annotation->setDocumentation($this->getDescription($resource, $route));

                if ($resource->getActions()->getActionForRoute($route) instanceof Index) {
                    $this->addFilter($annotation, $resource);
                    $this->addPagination($annotation, $resource);
                }
            }
        }
    }

    /**
     * @param Route $route
     * @return ApiResource|null
     */
    private function getResource(Route $route)
    {
        if (!$entity = $route->getDefault('_entity')) {
            return;
        }

        return $this->manager->getResourceForEntity($entity);
    }

    /**
     * @param ApiDoc $annotation
     * @param ApiResource $resource
     */
    private function setOutput(ApiDoc $annotation, ApiResource $resource)
    {
        $refl = new \ReflectionClass($annotation);

        $prop = $refl->getProperty('output');

        $prop->setAccessible(true);
        $prop->setValue($annotation, $resource->getEntityClass());
        $prop->setAccessible(false);
    }

    /**
     * @param ApiDoc $annotation
     * @param ApiResource $resource
     */
    private function setInput(ApiDoc $annotation, ApiResource $resource)
    {
        $refl = new \ReflectionClass($annotation);

        $prop = $refl->getProperty('input');

        $prop->setAccessible(true);
        $prop->setValue($annotation, [
            'class' => $resource->getFormTypeClass(),
            'name' => ''
        ]);
        $prop->setAccessible(false);
    }

    /**
     * @param Route $route
     * @return bool
     */
    private function returnsEntity(Route $route)
    {
        $methods = $route->getMethods();

        if (in_array('GET', $methods) ||
            in_array('POST', $methods) ||
            in_array('PUT', $methods) ||
            in_array('PATCH', $methods)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param Route $route
     * @return bool
     */
    private function expectsInput(Route $route)
    {
        $methods = $route->getMethods();

        if (in_array('POST', $methods) ||
            in_array('PUT', $methods) ||
            in_array('PATCH', $methods)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param ApiResource $resource
     * @param Route $route
     * @return string
     */
    private function getDescription(ApiResource $resource, Route $route)
    {
        $description = '';

        $name = $resource->getName();

        $action = $resource->getActions()->getActionForRoute($route);

        switch (get_class($action)) {
            case Index::class:
                $description = 'List all ' . Inflector::pluralize($name);
                break;
            case Show::class:
                $description = 'Get a single ' . Inflector::singularize($name);
                break;
            case Create::class:
                $description = 'Create a new ' . Inflector::singularize($name);
                break;
            case Update::class:
                $description = 'Update a ' . Inflector::singularize($name);
                break;
            case BatchUpdate::class:
                $description = 'Update multiple ' . Inflector::pluralize($name);
                break;
            case Delete::class:
                $description = 'Delete a ' . Inflector::singularize($name);
                break;
            case BatchDelete::class:
                $description = 'Delete multiple ' . Inflector::pluralize($name);
                break;
            default:
                @trigger_error('Action was not defined');
                break;
        }

        return $description;
    }

    /**
     * @param ApiDoc $annotation
     * @param ApiResource $resource
     */
    protected function addFilter(ApiDoc $annotation, ApiResource $resource)
    {
        $filterClass = $resource->getFilterClass();

        if (class_exists($filterClass)) {
            $filter = new $filterClass;
        } elseif ($this->container->has($filterClass)) {
            $filter = $this->container->get($filterClass);
        } else {
            $filter = null;
        }

        /** @var FilterInterface $filter */
        if ($filter) {
            $builder = $this->container->has('bitecodes.doctrine_filter_builder')
                ? $this->container->get('bitecodes.doctrine_filter_builder')
                : new FilterBuilder();
            $filter->buildFilter($builder);
            foreach ($builder->getFilters() as $filterElement) {
                $name = $filterElement->getName();
                $options = $filterElement->getOptions();
                $description = !empty($options['description']) ? $options['description'] : '';
                $requirement = !empty($options['requirement']) ? $options['requirement'] : '';
                $annotation->addFilter($name, compact('description', 'requirement'));
            }
        }
    }

    /**
     * @param ApiDoc $annotation
     * @param ApiResource $resource
     */
    protected function addPagination(ApiDoc $annotation, ApiResource $resource)
    {
        if ($resource->hasPagination()) {
            $annotation->addFilter('page', [
                'description' => 'Page'
            ]);
        }
    }

    /**
     * @param $routeName
     * @param ApiResource $resource
     * @return string
     */
    private function getSection($routeName, ApiResource $resource)
    {
        if($resource->getSection()) {
            return $resource->getSection();
        }

        $prefixPos = strlen($resource->getBundlePrefix() . '.');
        $nextDot = strpos($routeName, '.', $prefixPos);

        $resourceName = substr($routeName, $prefixPos, $nextDot - $prefixPos);
        $parentResource = $this->manager->getResource($resourceName);

        return ucwords($parentResource->getName());
    }
}
