<?php

namespace ipl\Html;

use ArrayAccess;
use ArrayIterator;
use InvalidArgumentException;
use IteratorAggregate;

use function ipl\Stdlib\get_php_type;

/**
 * HTML attributes
 *
 * HTML attributes provide additional information about HTML elements, that configure the elements or adjust their
 * behavior in various ways.
 *
 * Attributes usually come in name-value pairs and are rendered as name="value".
 */
class Attributes implements ArrayAccess, IteratorAggregate
{
    /** @var Attribute[] */
    protected $attributes = [];

    /** @var callable[] */
    protected $callbacks = [];

    /** @var string */
    protected $prefix = '';

    /** @var callable[] */
    protected $setterCallbacks = [];

    /**
     * Create new HTML attributes
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = null)
    {
        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $key => $value) {
            if ($value instanceof Attribute) {
                $this->addAttribute($value);
            } elseif (is_string($key)) {
                $this->add($key, $value);
            } elseif (is_array($value) && count($value) === 2) {
                $this->add(array_shift($value), array_shift($value));
            }
        }
    }

    /**
     * Create new HTML attributes
     *
     * @param array $attributes
     *
     * @return static
     */
    public static function create(array $attributes = null)
    {
        return new static($attributes);
    }

    /**
     * Ensure that the given attributes of mixed type are converted to an instance of attributes
     *
     * The conversion procedure is as follows:
     *
     * If the given attributes is already an instance of Attributes, returns the very same element.
     * If the attributes are given as an array of attribute name-value pairs, they are used to
     * construct and return a new Attributes instance.
     * If the attributes are null, an empty new instance of Attributes is returned.
     *
     * @param array|static|null $attributes
     *
     * @return static
     *
     * @throws InvalidArgumentException In case the given attributes are of an unsupported type
     */
    public static function wantAttributes($attributes)
    {
        if ($attributes instanceof self) {
            return $attributes;
        }

        if (is_array($attributes)) {
            return new static($attributes);
        }

        if ($attributes === null) {
            return new static();
        }

        throw new InvalidArgumentException(sprintf(
            'Attributes instance, array or null expected. Got %s instead.',
            get_php_type($attributes)
        ));
    }

    /**
     * Get the collection of attributes as array
     *
     * @return Attribute[]
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Return true if the attribute with the given name exists, false otherwise
     *
     * @param string $name
     *
     * @return bool
     */
    public function has($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * Get the attribute with the given name
     *
     * If the attribute does not yet exist, it is automatically created and registered to this Attributes instance.
     *
     * @param string $name
     *
     * @return Attribute
     *
     * @throws InvalidArgumentException If the attribute does not yet exist and its name contains special characters
     */
    public function get($name)
    {
        if (! $this->has($name)) {
            $this->attributes[$name] = Attribute::createEmpty($name);
        }

        return $this->attributes[$name];
    }

    /**
     * Set the given attribute(s)
     *
     * If the attribute with the given name already exists, it gets overridden.
     *
     * @param string|array|Attribute|self $attribute The attribute(s) to add
     * @param string|bool|array           $value     The value of the attribute
     *
     * @return $this
     *
     * @throws InvalidArgumentException If the attribute name contains special characters
     */
    public function set($attribute, $value = null)
    {
        if ($attribute instanceof self) {
            foreach ($attribute as $a) {
                $this->setAttribute($a);
            }

            return $this;
        }

        if ($attribute instanceof Attribute) {
            $this->setAttribute($attribute);

            return $this;
        }

        if (is_array($attribute)) {
            foreach ($attribute as $name => $value) {
                $this->set($name, $value);
            }

            return $this;
        }

        if (array_key_exists($attribute, $this->setterCallbacks)) {
            $callback = $this->setterCallbacks[$attribute];

            $callback($value);

            return $this;
        }

        $this->attributes[$attribute] = Attribute::create($attribute, $value);

        return $this;
    }

    /**
     * Add the given attribute(s)
     *
     * If an attribute with the same name already exists, the attribute's value will be added to the current value of
     * the attribute.
     *
     * @param string|array|Attribute|self $attribute The attribute(s) to add
     * @param string|bool|array           $value     The value of the attribute
     *
     * @return $this
     *
     * @throws InvalidArgumentException If the attribute does not yet exist and its name contains special characters
     */
    public function add($attribute, $value = null)
    {
        if ($attribute === null) {
            return $this;
        }

        if ($attribute instanceof self) {
            foreach ($attribute as $attr) {
                $this->add($attr);
            }

            return $this;
        }

        if (is_array($attribute)) {
            foreach ($attribute as $name => $value) {
                $this->add($name, $value);
            }

            return $this;
        }

        if ($attribute instanceof Attribute) {
            $this->addAttribute($attribute);

            return $this;
        }

        if (array_key_exists($attribute, $this->setterCallbacks)) {
            $callback = $this->setterCallbacks[$attribute];

            $callback($value);

            return $this;
        }

        if (! array_key_exists($attribute, $this->attributes)) {
            $this->attributes[$attribute] = Attribute::create($attribute, $value);
        } else {
            $this->attributes[$attribute]->addValue($value);
        }

        return $this;
    }

    /**
     * Remove the attribute with the given name or remove the given value from the attribute
     *
     * @param string            $name  The name of the attribute
     * @param null|string|array $value The value to remove if specified
     *
     * @return Attribute|false
     */
    public function remove($name, $value = null)
    {
        if (! $this->has($name)) {
            return false;
        }

        $attribute = $this->attributes[$name];

        if ($value === null) {
            unset($this->attributes[$name]);
        } else {
            $attribute->removeValue($value);
        }

        return $attribute;
    }

    /**
     * Set the specified attribute
     *
     * @param Attribute $attribute
     *
     * @return $this
     */
    public function setAttribute(Attribute $attribute)
    {
        $this->attributes[$attribute->getName()] = $attribute;

        return $this;
    }

    /**
     * Add the specified attribute
     *
     * If an attribute with the same name already exists, the given attribute's value
     * will be added to the current value of the attribute.
     *
     * @param Attribute $attribute
     *
     * @return $this
     */
    public function addAttribute(Attribute $attribute)
    {
        $name = $attribute->getName();

        if ($this->has($name)) {
            $this->attributes[$name]->addValue($attribute->getValue());
        } else {
            $this->attributes[$name] = $attribute;
        }

        return $this;
    }

    /**
     * Get the attributes name prefix
     *
     * @return string|null
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Set the attributes name prefix
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }

    /**
     * Register callback for an attribute
     *
     * @param string   $name            Name of the attribute to register the callback for
     * @param callable $callback        Callback to call when retrieving the attribute
     * @param callable $setterCallback  Callback to call when setting the attribute
     *
     * @return $this
     *
     * @throws InvalidArgumentException If $callback is not callable or if $setterCallback is set and not callable
     */
    public function registerAttributeCallback($name, $callback, $setterCallback = null)
    {
        if ($callback !== null) {
            if (! is_callable($callback)) {
                throw new InvalidArgumentException(__METHOD__ . ' expects a callable callback');
            }

            $this->callbacks[$name] = $callback;
        }

        if ($setterCallback !== null) {
            if (! is_callable($setterCallback)) {
                throw new InvalidArgumentException(__METHOD__ . ' expects a callable setterCallback');
            }

            $this->setterCallbacks[$name] = $setterCallback;
        }

        return $this;
    }

    /**
     * Render attributes to HTML
     *
     * If the value of an attribute is of type boolean, it will be rendered as
     * {@link http://www.w3.org/TR/html5/infrastructure.html#boolean-attributes boolean attribute}.
     *
     * If the value of an attribute is null, it will be skipped.
     *
     * HTML-escaping of the attributes' values takes place automatically using {@link Attribute::escapeValue()}.
     *
     * @return string
     *
     * @throws InvalidArgumentException If the result of a callback is invalid
     */
    public function render()
    {
        $parts = [];
        foreach ($this->callbacks as $name => $callback) {
            $attribute = call_user_func($callback);
            if ($attribute instanceof Attribute) {
                if (! $attribute->isEmpty()) {
                    $parts[] = $attribute->render();
                }
            } elseif ($attribute === null) {
                continue;
            } elseif (is_scalar($attribute)) {
                $parts[] = Attribute::create($name, $attribute)->render();
            } else {
                throw new InvalidArgumentException(sprintf(
                    'A registered attribute callback must return string, null'
                    . ' or an Attribute, got a %s',
                    get_php_type($attribute)
                ));
            }
        }

        foreach ($this->attributes as $attribute) {
            if ($attribute->isEmpty()) {
                continue;
            }

            $parts[] = $attribute->render();
        }

        if (empty($parts)) {
            return '';
        }

        $separator = ' ' . $this->getPrefix();

        return $separator . implode($separator, $parts);
    }

    /**
     * Get whether the attribute with the given name exists
     *
     * @param string $name Name of the attribute
     *
     * @return bool
     */
    public function offsetExists($name)
    {
        return $this->has($name);
    }

    /**
     * Get the attribute with the given name
     *
     * If the attribute does not yet exist, it is automatically created and registered to this Attributes instance.
     *
     * @param string $name Name of the attribute
     *
     * @return Attribute
     *
     * @throws InvalidArgumentException If the attribute does not yet exist and its name contains special characters
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Set the given attribute
     *
     * If the attribute with the given name already exists, it gets overridden.
     *
     * @param string $name  Name of the attribute
     * @param mixed  $value Value of the attribute
     *
     * @throws InvalidArgumentException If the attribute name contains special characters
     */
    public function offsetSet($name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Remove the attribute with the given name
     *
     * @param string $name Name of the attribute
     */
    public function offsetUnset($name)
    {
        $this->remove($name);
    }

    /**
     * Get an iterator for traversing the attributes
     *
     * @return Attribute[]|ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->attributes);
    }
}
