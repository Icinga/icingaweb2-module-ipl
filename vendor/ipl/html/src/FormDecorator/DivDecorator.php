<?php

namespace ipl\Html\FormDecorator;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Contract\FormElement;
use ipl\Html\Contract\FormElementDecorator;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\Html;

/**
 * Form element decorator based on div elements
 */
class DivDecorator extends BaseHtmlElement implements FormElementDecorator
{
    /** @var FormElement The decorated form element */
    protected $formElement;

    /** @var bool Whether the form element has been added already */
    protected $formElementAdded = false;

    protected $tag = 'div';

    public function decorate(FormElement $formElement)
    {
        $decorator = clone $this;

        $decorator->formElement = $formElement;

        if ($formElement instanceof FormSubmitElement) {
            $class = 'form-control';
        } else {
            $class = 'form-element';
        }

        $decorator->getAttributes()->add('class', $class);

        $formElement->prependWrapper($decorator);
    }

    protected function assembleDescription()
    {
        $description = $this->formElement->getDescription();

        if ($description !== null) {
            return Html::tag('p', ['class' => 'form-element-description'], $description);
        }

        return null;
    }

    protected function assembleErrors()
    {
        $errors = [];

        foreach ($this->formElement->getMessages() as $message) {
            $errors[] = Html::tag('p', ['class' => 'form-element-error'], $message);
        }

        if (! empty($errors)) {
            return $errors;
        }

        return null;
    }

    protected function assembleLabel()
    {
        $label = $this->formElement->getLabel();

        if ($label !== null) {
            $attributes = null;
            $elementAttributes = $this->formElement->getAttributes();

            if (isset($elementAttributes['id'])) {
                $attributes = new Attributes(['for' => $elementAttributes['id']]);
            }

            return Html::tag('label', $attributes, $label);
        }

        return null;
    }

    public function add($content)
    {
        if ($content === $this->formElement) {
            // Our wrapper implementation automatically adds the wrapped element but we already did this in assemble
            if ($this->formElementAdded) {
                return $this;
            }

            $this->formElementAdded = true;
        }

        parent::add($content);

        return $this;
    }

    protected function assemble()
    {
        if ($this->formElement->hasBeenValidated() && ! $this->formElement->isValid()) {
            $this->getAttributes()->add('class', 'has-error');
        }

        $this->add(array_filter([
            $this->assembleLabel(),
            $this->formElement,
            $this->assembleDescription(),
            $this->assembleErrors()
        ]));
    }
}
