<?php

namespace ipl\Web\Compat;

use ipl\Html\Attributes;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Contract\FormElement;
use ipl\Html\Contract\FormElementDecorator;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\Html;

/**
 * Compat form element decorator based on div elements
 */
class CompatDecorator extends BaseHtmlElement implements FormElementDecorator
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
            $class = 'control-group form-controls';

            $formElement->getAttributes()->add(['class' => 'btn-primary']);
        } else {
            $class = 'control-group';
        }

        $decorator->getAttributes()->add('class', $class);

        $formElement->prependWrapper($decorator);

        return $decorator;
    }

    protected function assembleDescription()
    {
        $description = $this->formElement->getDescription();

        if ($description !== null) {
            $style = 'color: #7F7F7F; margin-bottom: 0; margin-left: 15em; margin-top: 0.25em; padding-left: 0.5em;'
                . ' width: 100%;';

            return Html::tag('p', ['class' => 'form-element-description', 'style' => $style], $description);
        }

        return null;
    }

    protected function assembleErrors()
    {
        $errors = [];

        foreach ($this->formElement->getMessages() as $message) {
            $errors[] = Html::tag('li', $message);
        }

        if (! empty($errors)) {
            return Html::tag('ul', ['class' => 'errors'], $errors);
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

            return Html::tag('div', ['class' => 'control-label-group'], Html::tag('label', $attributes, $label));
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
