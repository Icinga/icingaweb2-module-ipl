<?php

namespace ipl\Html\FormElement;

class SelectElement extends BaseFormElement
{
    protected $tag = 'select';

    /** @var SelectOption[] */
    protected $options = [];

    public function __construct($name, $attributes = null)
    {
        parent::__construct($name, $attributes);
        $this->getAttributes()->registerAttributeCallback(
            'options',
            null,
            [$this, 'setOptions']
        );
        // ZF1 compatibility:
        $this->getAttributes()->registerAttributeCallback(
            'multiOptions',
            null,
            [$this, 'setOptions']
        );
    }

    public function setValue($value)
    {
        if ($value === '') {
            $value = null;
        }

        return parent::setValue($value);
    }

    /**
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $value => $label) {
            $this->options[$value] = new SelectOption($value, $label);
        }

        return $this;
    }

    protected function assemble()
    {
        $currentValue = $this->getValue();
        foreach ($this->options as $value => $option) {
            if ($value  == $currentValue) {
                $option->getAttributes()->set('selected', true);
            }

            $this->add($option);
        }
    }
}
