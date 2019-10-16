<?php

namespace ipl\Html\FormDecorator;

use ipl\Html\FormElement\BaseFormElement;

// TODO: FormElementDecoratorInterface?
interface DecoratorInterface
{
    /**
     * Set the form element to decorate
     *
     * @param BaseFormElement $formElement
     *
     * @return static
     */
    public function decorate(BaseFormElement $formElement);
}
