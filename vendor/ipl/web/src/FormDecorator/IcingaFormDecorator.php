<?php

namespace ipl\Web\FormDecorator;

use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\FormDecorator\DivDecorator;
use ipl\Html\FormElement\CheckboxElement;
use ipl\Html\HtmlElement;
use ipl\Web\Widget\Icon;

class IcingaFormDecorator extends DivDecorator
{
    const SUBMIT_ELEMENT_CLASS = 'form-controls';
    const INPUT_ELEMENT_CLASS = 'control-group';
    const ERROR_CLASS = 'errors';

    protected function assembleElement()
    {
        if ($this->formElement instanceof FormSubmitElement) {
            $this->formElement->getAttributes()->add('class', 'btn-primary');
        }

        $element = parent::assembleElement();

        if ($element instanceof CheckboxElement) {
            return $this->createCheckbox($element);
        }

        return $element;
    }

    protected function createCheckbox(CheckboxElement $checkbox)
    {
        if (! $checkbox->getAttributes()->has('id')) {
            $checkbox->setAttribute('id', base64_encode(random_bytes(8)));
        }

        $checkbox->getAttributes()->add('class', 'sr-only');

        $classes = ['toggle-switch'];
        if ($checkbox->getAttributes()->get('disabled')->getValue()) {
            $classes[] = 'disabled';
        }

        return [
            $checkbox,
            new HtmlElement('label', [
                'class' => $classes,
                'for'   => $checkbox->getAttributes()->get('id')->getValue()
            ], new HtmlElement('span', ['class' => 'toggle-slider']))
        ];
    }

    protected function assembleLabel()
    {
        $label = parent::assembleLabel();
        if ($label !== null) {
            $label->addWrapper(new HtmlElement('div', ['class' => 'control-label-group']));
        }

        return $label;
    }

    protected function assembleDescription()
    {
        if (($description = $this->formElement->getDescription()) !== null) {
            $iconAttributes = [
                'class'         => 'control-info',
                'role'          => 'image',
                'title'         => $description
            ];

            $describedBy = null;
            if ($this->formElement->getAttributes()->has('id')) {
                $iconAttributes['aria-hidden'] = 'true';

                $descriptionId = 'desc_' . $this->formElement->getAttributes()->get('id')->getValue();
                $describedBy = new HtmlElement('span', [
                    'id'    => $descriptionId,
                    'class' => 'sr-only'
                ], $description);

                $this->formElement->getAttributes()->set('aria-describedby', $descriptionId);
            }

            return [
                new Icon('info-circle', $iconAttributes),
                $describedBy
            ];
        }
    }
}
