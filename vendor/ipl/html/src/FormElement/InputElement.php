<?php

namespace ipl\Html\FormElement;

use ipl\Html\Attribute;

abstract class InputElement extends BaseFormElement
{
    protected $tag = 'input';

    /** @var string */
    protected $type;

    protected function registerCallbacks()
    {
        parent::registerCallbacks();
        $this->getAttributes()->registerAttributeCallback(
            'type',
            [$this, 'getTypeAttribute']
        );
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return Attribute
     */
    public function getTypeAttribute()
    {
        return new Attribute('type', $this->getType());
    }
}
