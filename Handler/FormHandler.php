<?php

namespace Fludio\RestApiGeneratorBundle\Handler;

use Doctrine\Common\Persistence\ObjectManager;
use Fludio\RestApiGeneratorBundle\Exception\ApiProblem;
use Fludio\RestApiGeneratorBundle\Exception\ApiProblemException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

class FormHandler
{
    private $om;
    private $formFactory;
    private $formType;

    public function __construct(
        ObjectManager $objectManager,
        FormFactoryInterface $formFactory,
        $formType
    )
    {
        $this->om = $objectManager;
        $this->formFactory = $formFactory;
        $this->formType = $formType;
    }

    public function processForm($object, array $parameters, $method)
    {
        $form = $this->formFactory->create($this->formType, $object, array(
            'method' => $method,
            'csrf_protection' => false,
            'object' => $object
        ));

        $form->submit($parameters, 'PATCH' !== $method);

        if (!$form->isValid()) {
            $this->handleValidationError($form);
        }

        $data = $form->getData();
        $this->om->persist($data);
        $this->om->flush();

        return $data;
    }

    public function delete($object)
    {
        $this->om->remove($object);
        $this->om->flush();

        return true;
    }

    public function batchDelete($ids)
    {
        foreach ($ids as $id) {
            $entity = $this->om->find($id);
            $this->om->remove($entity);
        }

        $this->om->flush();
    }

    public function getFormTypeClass()
    {
        return $this->formType;
    }

    /**
     * @param $form
     */
    protected function handleValidationError($form)
    {
        $problem = new ApiProblem(
            422,
            ApiProblem::TYPE_VALIDATION_ERROR
        );
        $problem->set('errors', $this->getErrorsFromForm($form));
        throw new ApiProblemException($problem);
    }

    /**
     * @param FormInterface $form
     * @return array
     */
    protected function getErrorsFromForm(FormInterface $form)
    {
        $errors = array();
        foreach ($form->getErrors() as $error) {
            $errors[] = $error->getMessage();
        }
        foreach ($form->all() as $childForm) {
            if ($childForm instanceof FormInterface) {
                if ($childErrors = $this->getErrorsFromForm($childForm)) {
                    $errors[$childForm->getName()] = $childErrors;
                }
            }
        }
        return $errors;
    }
}