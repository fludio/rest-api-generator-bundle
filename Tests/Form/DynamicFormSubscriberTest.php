<?php

namespace BiteCodes\RestApiGeneratorBundle\Tests\Form;

use BiteCodes\RestApiGeneratorBundle\Form\DynamicFormSubscriber;
use BiteCodes\RestApiGeneratorBundle\Tests\Dummy\TestCase;
use BiteCodes\RestApiGeneratorBundle\Tests\Dummy\TestEntity\Post;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\HttpKernel\Kernel;

class DynamicFormSubscriberTest extends TestCase
{
    /**
     * @var DynamicFormSubscriber
     */
    protected $subscriber;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $event;
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $form;

    public function setUp()
    {
        parent::setUp();

        $post = new Post();

        $this->subscriber = new DynamicFormSubscriber($this->em, $post);

        $this->event = $this->getMockBuilder(FormEvent::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->form = $this->getMockBuilder(Form::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->event->expects($this->any())
            ->method('getForm')
            ->willReturn($this->form);
    }

    /** @test */
    public function it_adds_fields_to_a_form()
    {
        $this->form->expects($this->at(0))
            ->method('add')
            ->with('title')
            ->willReturnSelf();

        $this->form->expects($this->at(1))
            ->method('add')
            ->with('content')
            ->willReturnSelf();

        $this->form->expects($this->at(2))
            ->method('add')
            ->with('photo')
            ->willReturnSelf();

        $this->subscriber->onPreSubmit($this->event);
    }

    /** @test */
    public function it_adds_datetime_to_fields()
    {
        if (Kernel::MAJOR_VERSION == 2 && Kernel::MINOR_VERSION == 7) {
            $dateTime = 'datetime';
        } else {
            $dateTime = DateTimeType::class;
        }

        $this->form->expects($this->at(3))
            ->method('add')
            ->with('createdAt', $dateTime, ['widget' => 'single_text'])
            ->willReturnSelf();

        $this->subscriber->onPreSubmit($this->event);
    }
}
