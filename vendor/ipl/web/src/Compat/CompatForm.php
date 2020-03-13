<?php

namespace ipl\Web\Compat;

use ipl\Html\Form;

class CompatForm extends Form
{
    protected $defaultAttributes = ['class' => 'icinga-form icinga-controls'];

    public function hasDefaultElementDecorator()
    {
        if (parent::hasDefaultElementDecorator()) {
            return true;
        }

        $this->setDefaultElementDecorator(new CompatDecorator());

        return true;
    }
}
