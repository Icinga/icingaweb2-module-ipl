<?php

namespace ipl\Html\FormElement;

use InvalidArgumentException;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Form;
use ipl\Html\FormDecorator\DecoratorInterface;
use ipl\Html\ValidHtml;
use ipl\Stdlib\EventEmitter;
use ipl\Stdlib\Loader\PluginLoader;

trait FormElementContainer
{
    use PluginLoader;
    use EventEmitter;

    /** @var BaseFormElement[] */
    private $elements = [];

    private $populatedValues = [];

    /** @var DecoratorInterface|BaseHtmlElement|null */
    protected $defaultElementDecorator;

    /**
     * @return BaseFormElement[]
     */
    public function getElements()
    {
        return $this->elements;
    }

    /**
     * Returns the value for the $name element
     *
     * Returns $default in case the element does not exist or has no value
     *
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    public function getValue($name, $default = null)
    {
        if ($this->hasElement($name)) {
            $value = $this->getElement($name)->getValue();
            if ($value !== null) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Returns a name => value array for all but ignored elements
     *
     * @return array
     */
    public function getValues()
    {
        $values = [];
        foreach ($this->getElements() as $element) {
            if (! $element->isIgnored()) {
                $values[$element->getName()] = $element->getValue();
            }
        }

        return $values;
    }

    public function populate($values)
    {
        foreach ($values as $name => $value) {
            $this->populatedValues[$name] = $value;
            if ($this->hasElement($name)) {
                $this->getElement($name)->setValue($value);
            }
        }
    }

    public function addElementLoader($namespace, $classPostfix = null)
    {
        $this->eventuallyRegisterDefaultElementLoader();

        return $this->addPluginLoader('element', $namespace, $classPostfix);
    }

    protected function eventuallyRegisterDefaultElementLoader()
    {
        if (! $this->hasPluginLoadersFor('element')) {
            $this->addPluginLoader('element', __NAMESPACE__, 'Element');
        }

        return $this;
    }

    public function addDecoratorLoader($namespace, $classPostfix = null)
    {
        $this->eventuallyRegisterDefaultDecoratorLoader();

        return $this->addPluginLoader('decorator', $namespace, $classPostfix);
    }

    protected function eventuallyRegisterDefaultDecoratorLoader()
    {
        if (! $this->hasPluginLoadersFor('decorator')) {
            $this->addPluginLoader(
                'decorator',
                'ipl\\Html\\FormDecorator',
                'Decorator'
            );
        }

        return $this;
    }

    /**
     * @param $name
     * @return BaseFormElement
     */
    public function getElement($name)
    {
        if (! \array_key_exists($name, $this->elements)) {
            throw new InvalidArgumentException(sprintf(
                'Trying to get non-existent element "%s"',
                $name
            ));
        }
        return $this->elements[$name];
    }

    /**
     * @param string|BaseFormElement $element
     * @return bool
     */
    public function hasElement($element)
    {
        if (\is_string($element)) {
            return \array_key_exists($element, $this->elements);
        } elseif ($element instanceof BaseFormElement) {
            return \in_array($element, $this->elements, true);
        } else {
            return false;
        }
    }

    /**
     * @param string $name
     * @param string|BaseFormElement $type
     * @param array|null $options
     * @return $this
     */
    public function addElement($type, $name = null, $options = null)
    {
        $this->registerElement($type, $name, $options);
        if ($name === null) {
            $name = $type->getName();
        }

        $element = $this->getElement($name);
        if ($this instanceof BaseHtmlElement) {
            $element = $this->decorate($element);
        }

        $this->add($element);

        return $this;
    }

    /**
     * @param BaseFormElement $element
     * @return BaseFormElement
     */
    protected function decorate(BaseFormElement $element)
    {
        if ($this->hasDefaultElementDecorator()) {
            $decorator = $this->getDefaultElementDecorator();
            if ($decorator instanceof DecoratorInterface) {
                $decorator->decorate($element);
            } elseif ($decorator instanceof BaseHtmlElement) {
                $decorator->wrap($element);
            } else {
                die('WTF');
            }
        }

        return $element;
    }

    /**
     * @param string $name
     * @param string|BaseFormElement $type
     * @param array|null $options
     * @return $this
     */
    public function registerElement($type, $name = null, $options = null)
    {
        if (\is_string($type)) {
            $type = $this->createElement($type, $name, $options);
        // TODO: } elseif ($type instanceof FormElementInterface) {
        } elseif ($type instanceof BaseHtmlElement) {
            if ($name === null) {
                $name = $type->getName();
            }
        } else {
            throw new InvalidArgumentException(sprintf(
                'FormElement or element type is required' // TODO: got %s
            ));
        }

        $this->elements[$name] = $type;

        $this->onElementRegistered($name, $type);
        $this->emit(Form::ON_ELEMENT_REGISTERED, [$name, $type]);

        return $this;
    }

    public function onElementRegistered($name, BaseFormElement $element)
    {
        // TODO: hasSubmitButton is not here
        if ($element instanceof SubmitElement && ! $this->hasSubmitButton()) {
            $this->setSubmitButton($element);
        }

        if (\array_key_exists($name, $this->populatedValues)) {
            $element->setValue($this->populatedValues[$name]);
        }
    }

    /**
     * @param string $type
     * @param string $name
     * @param mixed $attributes
     * @return BaseFormElement
     */
    public function createElement($type, $name, $attributes = null)
    {
        $this->eventuallyRegisterDefaultElementLoader();

        $class = $this->eventuallyGetPluginClass('element', $type);
        /** @var BaseFormElement $element */
        $element = new $class($name);
        if ($attributes !== null) {
            $element->addAttributes($attributes);
        }

        return $element;
    }

    /**
     * @param   Form|SubFormElement $form
     */
    public function addElementsFrom($form)
    {
        foreach ($form->getElements() as $name => $element) {
            $this->addElement($element);
        }
    }

    public function remove(ValidHtml $html)
    {
        // TODO: FormElementInterface?
        if ($html instanceof BaseFormElement) {
            if ($this->hasElement($html)) {
                if ($this->submitButton === $html) {
                    $this->submitButton = null;
                }

                $kill = [];
                foreach ($this->elements as $key => $element) {
                    if ($element === $html) {
                        $kill[] = $key;
                    }
                }

                foreach ($kill as $key) {
                    unset($this->elements[$key]);
                }
            }
        }

        parent::remove($html);
    }

    public function setDefaultElementDecorator($decorator)
    {
        if ($decorator instanceof BaseHtmlElement
            || $decorator instanceof DecoratorInterface
        ) {
            $this->defaultElementDecorator = $decorator;
        } else {
            $this->eventuallyRegisterDefaultDecoratorLoader();
            $this->defaultElementDecorator = $this->loadPlugin('decorator', $decorator);
        }

        return $this;
    }

    public function hasDefaultElementDecorator()
    {
        return $this->defaultElementDecorator !== null;
    }

    /**
     * @return DecoratorInterface
     */
    public function getDefaultElementDecorator()
    {
        return $this->defaultElementDecorator;
    }

    public function isValidEvent($event)
    {
        return \in_array($event, [
            Form::ON_SUCCESS,
            Form::ON_ERROR,
            Form::ON_REQUEST,
            Form::ON_ELEMENT_REGISTERED,
        ]);
    }
}
